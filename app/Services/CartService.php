<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Storefront\BranchOrderHolds;
use App\Actions\Storefront\StorefrontOrderGuard;
use App\Actions\Storefront\StorefrontPricing;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\StorefrontListing;
use App\Models\User;
use App\Services\Purchasing\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class CartService
{
    public function getOrCreateCart(?int $userId = null, ?string $sessionId = null): Cart
    {
        $query = Cart::with(['items.product']);

        if ($userId) {
            return $query->firstOrCreate(['user_id' => $userId]);
        } elseif ($sessionId) {
            return $query->firstOrCreate(['session_id' => $sessionId]);
        }

        throw new InvalidArgumentException('Either user_id or session_id must be provided.');
    }

    /**
     * @throws ValidationException When the product presents linked branch stock, which is display-only.
     */
    public function addToCart(?int $userId, ?string $sessionId, Product $product, int $quantity = 1): CartItem
    {
        StorefrontOrderGuard::assertOrderable([$product->id], 'cart', $userId === null ? null : User::query()->find($userId));

        $cart = $this->getOrCreateCart($userId, $sessionId);

        return $this->withLockedCart($cart, function () use ($cart, $product, $quantity): CartItem {
            $item = $cart->items()->where('product_id', $product->id)->first();

            if ($item) {
                $item->quantity += $quantity;
                $item->subtotal = $item->price * $item->quantity;
                $item->save();
            } else {
                $price = $this->displayPrice($product, $cart->user_id === null ? null : User::query()->find($cart->user_id)) ?? 0;
                $item = $cart->items()->create([
                    'product_id' => $product->id,
                    'price' => $price,
                    'quantity' => $quantity,
                    'subtotal' => $price * $quantity,
                ]);
            }

            return $item;
        });
    }

    public function updateQuantity(?int $userId, ?string $sessionId, int $productId, int $quantity): ?CartItem
    {
        $cart = $this->getOrCreateCart($userId, $sessionId);

        return $this->withLockedCart($cart, function () use ($cart, $productId, $quantity): ?CartItem {
            $item = $cart->items()->where('product_id', $productId)->first();

            if (! $item) {
                return null;
            }

            $item->quantity = $quantity;
            $item->subtotal = $item->price * $quantity;
            $item->save();

            return $item;
        });
    }

    public function removeFromCart(?int $userId, ?string $sessionId, int $productId): bool
    {
        $cart = $this->getOrCreateCart($userId, null);

        return $this->withLockedCart($cart, fn (): bool => $cart->items()->where('product_id', $productId)->delete() > 0);
    }

    public function clearCart(?int $userId, ?string $sessionId): bool
    {
        $cart = $this->getOrCreateCart($userId, null);

        return $this->withLockedCart($cart, fn (): bool => $cart->items()->delete() > 0);
    }

    /**
     * Replace the signed-in buyer's stored cart with the browser's cart, only when the browser
     * holds the stored cart's current revision. A stale tab, for example one still holding items
     * a branch order consumed or missing items another tab added, is refused and gets the stored
     * cart back instead.
     *
     * @param  array<int, array{product_id: int|string, quantity: int|string}>  $items
     * @return array{accepted: bool, revision: string, items: list<array<string, mixed>>|null} Items when the browser must reload them.
     */
    public function syncBrowserCart(int $userId, array $items, ?string $revision): array
    {
        $buyer = User::query()->find($userId);

        return $this->browserChange(
            $userId,
            fn (Cart $cart, string $current): bool => $revision !== null && hash_equals($current, $revision),
            fn (Cart $cart): ?array => $this->syncLockedCart($cart, $buyer, $items),
        );
    }

    /**
     * Move the browser's guest cart into the buyer's stored cart. Only the grant issued at sign-in
     * allows this, and only while the stored cart is still empty and at the granted revision, so
     * an item added since, from any tab, is never replaced.
     *
     * @param  array<int, array{product_id: int|string, quantity: int|string}>  $items
     * @return array{accepted: bool, revision: string, items: list<array<string, mixed>>}
     */
    public function adoptGuestCart(int $userId, array $items, ?string $adoptionGrant): array
    {
        $buyer = User::query()->find($userId);

        return $this->browserChange(
            $userId,
            fn (Cart $cart, string $current): bool => $adoptionGrant !== null && hash_equals($current, $adoptionGrant) && ! $cart->items()->exists(),
            function (Cart $cart) use ($buyer, $items): array {
                $this->syncLockedCart($cart, $buyer, $items);

                return $this->storedItems($cart->load('items.product'));
            },
        );
    }

    /**
     * The adoption grant for a buyer signing in: the stored cart's revision when it is empty,
     * read under the cart lock so the emptiness and the revision describe the same state.
     */
    public function adoptionGrant(int $userId): ?string
    {
        $cart = $this->getOrCreateCart($userId, null);

        return DB::transaction(function () use ($cart): ?string {
            self::lockCart($cart);

            return $cart->items()->exists() ? null : self::revision($cart);
        });
    }

    /**
     * Remove a product the buyer removed in the browser, when the browser holds the current revision.
     *
     * @return array{accepted: bool, revision: string, items: list<array<string, mixed>>|null}
     */
    public function removeFromBrowserCart(int $userId, int $productId, ?string $revision): array
    {
        return $this->browserChange(
            $userId,
            fn (Cart $cart, string $current): bool => $revision !== null && hash_equals($current, $revision),
            function (Cart $cart) use ($productId): null {
                $cart->items()->where('product_id', $productId)->delete();

                return null;
            },
        );
    }

    /**
     * Empty the cart the buyer cleared in the browser, when the browser holds the current revision.
     *
     * @return array{accepted: bool, revision: string, items: list<array<string, mixed>>|null}
     */
    public function clearBrowserCart(int $userId, ?string $revision): array
    {
        return $this->browserChange(
            $userId,
            fn (Cart $cart, string $current): bool => $revision !== null && hash_equals($current, $revision),
            function (Cart $cart): null {
                $cart->items()->delete();

                return null;
            },
        );
    }

    /**
     * The stored cart as the browser loads it, with its revision, both read under the cart lock.
     *
     * @return array{revision: string, items: list<array<string, mixed>>}
     */
    public function browserCart(int $userId): array
    {
        $cart = $this->getOrCreateCart($userId, null);

        return DB::transaction(function () use ($cart): array {
            self::lockCart($cart);

            return ['revision' => self::revision($cart), 'items' => $this->storedItems($cart->load('items.product'))];
        });
    }

    /**
     * The revision a browser must send back with its next change. It names the cart and its
     * change counter, so it differs after any change, even one back to the same contents.
     */
    public static function revision(Cart $cart): string
    {
        return $cart->id.'.'.(int) Cart::query()->whereKey($cart->id)->value('revision');
    }

    /**
     * Advance the cart's revision. Every change to the cart's items must call this inside the
     * transaction that holds the cart lock; withLockedCart() does so for the changes it runs.
     */
    public static function advanceRevision(Cart $cart): void
    {
        Cart::query()->whereKey($cart->id)->increment('revision');
    }

    /**
     * Run a browser cart change under the cart lock, only when the browser's claim about the
     * stored cart still holds. Otherwise nothing changes and the browser reloads the stored cart.
     *
     * @param  callable(Cart, string): bool  $accepts  Given the cart and its current revision.
     * @param  callable(Cart): (list<array<string, mixed>>|null)  $change  Returns items the browser must reload, if any.
     * @return array{accepted: bool, revision: string, items: list<array<string, mixed>>|null}
     */
    private function browserChange(int $userId, callable $accepts, callable $change): array
    {
        $cart = $this->getOrCreateCart($userId, null);

        return DB::transaction(function () use ($cart, $accepts, $change): array {
            self::lockCart($cart);
            $current = self::revision($cart);

            if (! $accepts($cart, $current)) {
                return ['accepted' => false, 'revision' => $current, 'items' => $this->storedItems($cart->load('items.product'))];
            }

            $items = $this->changeLocked($cart, fn (): ?array => $change($cart));

            return ['accepted' => true, 'revision' => self::revision($cart), 'items' => $items];
        });
    }

    /**
     * Lock a cart row inside a transaction. Every cart change and branch-order submission takes
     * this lock before reading the cart's items, so a submission cannot delete items added after
     * it read the cart, and a change cannot revive items a submission consumed.
     */
    public static function lockCart(Cart $cart): void
    {
        Cart::query()->whereKey($cart->id)->lockForUpdate()->first(['id']);
    }

    /**
     * Run a cart change with the cart locked. The lock is the transaction's first statement, so
     * the change reads items committed by whoever held the lock before it.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $change
     * @return TResult
     */
    private function withLockedCart(Cart $cart, callable $change): mixed
    {
        return DB::transaction(function () use ($cart, $change): mixed {
            self::lockCart($cart);

            return $this->changeLocked($cart, $change);
        });
    }

    /**
     * Run a change on a cart this transaction has locked, advancing its revision when the
     * change altered its items.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $change
     * @return TResult
     */
    private function changeLocked(Cart $cart, callable $change): mixed
    {
        $before = self::contents($cart);
        $result = $change();

        if (self::contents($cart) !== $before) {
            self::advanceRevision($cart);
        }

        return $result;
    }

    /**
     * @return list<array{int, int, string}> Product, quantity, and price of each item.
     */
    private static function contents(Cart $cart): array
    {
        return CartItem::query()->where('cart_id', $cart->id)->orderBy('product_id')->get(['product_id', 'quantity', 'price'])
            ->map(fn (CartItem $item): array => [(int) $item->product_id, (int) $item->quantity, (string) $item->price])
            ->all();
    }

    public function getCartTotal(?int $userId, ?string $sessionId): float
    {
        $cart = $this->getOrCreateCart($userId, $sessionId);

        return (float) $cart->items()->sum('subtotal');
    }

    public function getCartItemCount(?int $userId, ?string $sessionId): int
    {
        $cart = $this->getOrCreateCart($userId, $sessionId);

        return (int) $cart->items()->sum('quantity');
    }

    public function syncGuestCartToUser(int $userId, string $sessionId): void
    {
        Log::info('Starting cart sync process', [
            'user_id' => $userId,
            'session_id' => $sessionId,
        ]);

        $guestCart = Cart::with('items')->where('session_id', $sessionId)->first();
        $userCart = Cart::with('items')->where('user_id', $userId)->first();

        if (! $guestCart) {
            Log::info('No guest cart found to sync', ['session_id' => $sessionId]);

            return;
        }

        if (! $userCart) {
            Log::info('No existing user cart found, converting guest cart to user cart', [
                'cart_id' => $guestCart->id,
                'user_id' => $userId,
            ]);

            $guestCart->user_id = $userId;
            $guestCart->session_id = null;
            $guestCart->save();

            return;
        }

        Log::info('Merging guest cart with user cart', [
            'guest_cart_id' => $guestCart->id,
            'user_cart_id' => $userCart->id,
        ]);

        $displayOnly = StorefrontOrderGuard::displayOnlyProductIds($guestCart->items->pluck('product_id'), User::query()->find($userId));

        $this->withLockedCart($userCart, fn () => $this->mergeGuestItems($guestCart, $userCart, $displayOnly));
    }

    /**
     * @param  list<int>  $displayOnly
     */
    private function mergeGuestItems(Cart $guestCart, Cart $userCart, array $displayOnly): void
    {
        foreach ($guestCart->items as $guestItem) {
            if (in_array((int) $guestItem->product_id, $displayOnly, true)) {
                continue;
            }

            $userItem = $userCart->items()->where('product_id', $guestItem->product_id)->first();

            if ($userItem) {
                $newQuantity = $userItem->quantity + $guestItem->quantity;
                Log::info('Merging existing item', [
                    'product_id' => $guestItem->product_id,
                    'old_quantity' => $userItem->quantity,
                    'added_quantity' => $guestItem->quantity,
                    'new_quantity' => $newQuantity,
                ]);

                $userItem->quantity = $newQuantity;
                $userItem->subtotal = $userItem->price * $newQuantity;
                $userItem->save();
            } else {
                Log::info('Adding new item to user cart', [
                    'product_id' => $guestItem->product_id,
                    'quantity' => $guestItem->quantity,
                ]);

                $userCart->items()->create([
                    'product_id' => $guestItem->product_id,
                    'price' => $guestItem->price,
                    'quantity' => $guestItem->quantity,
                    'subtotal' => $guestItem->subtotal,
                ]);
            }
        }

        Log::info('Deleting guest cart after successful sync', ['cart_id' => $guestCart->id]);
        $guestCart->delete();
    }

    /**
     * @throws Throwable
     */
    public function syncCart(int $user_id, array $items)
    {
        // Validate that user_id is provided and not null
        if (! $user_id) {
            Log::warning('Attempted to sync cart without user_id');
            throw new InvalidArgumentException('User ID is required for cart syncing');
        }

        try {
            Log::info('Start Syncing', ['user_id' => $user_id, 'items_count' => count($items)]);
            $cart = $this->getOrCreateCart($user_id, null);
            $buyer = User::query()->find($user_id);

            return $this->withLockedCart($cart, fn () => $this->syncLockedCart($cart, $buyer, $items));
        } catch (Throwable $th) {
            Log::error('Error syncing cart', [
                'user_id' => $user_id,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            // Re-throw the exception so the calling code can handle it
            throw $th;
        }
    }

    /**
     * @param  array<int, array{product_id: int|string, quantity: int|string}>  $items
     * @return list<array<string, mixed>>|null
     */
    private function syncLockedCart(Cart $cart, ?User $buyer, array $items): ?array
    {
        $cart->load('items.product');

        // Linked branch stock that cannot be ordered here is dropped from the request and the
        // stored cart, then the stored cart is sent back so the browser drops it too.
        $displayOnly = StorefrontOrderGuard::displayOnlyProductIds([...array_column($items, 'product_id'), ...$cart->items->pluck('product_id')], $buyer);

        if ($displayOnly !== []) {
            $items = array_values(array_filter($items, fn (array $item): bool => ! in_array((int) $item['product_id'], $displayOnly, true)));
            $cart->items()->whereIn('product_id', $displayOnly)->delete();
            $cart->load('items.product');
        }

        // if the $items is empty,
        // and the cart has items,
        // we need to sync in the opposite direction from db to localStorage.
        if (empty($items) && $cart->items()->exists()) {
            Log::info('No items provided, clearing existing cart items', ['cart_id' => $cart->id]);

            return $this->storedItems($cart);
        }

        DB::transaction(function () use ($cart, $items, $buyer) {

            $cart->items()->delete(); // Clear existing items before syncing

            Log::info('Start Transaction');

            foreach ($items as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];

                // Use a database query instead of a collection to avoid race conditions
                $existingItem = $cart->items()->where('product_id', $productId)->first();

                if ($existingItem) {
                    Log::info('Updating existing cart item', [
                        'product_id' => $productId,
                        'old_quantity' => $existingItem->quantity,
                        'new_quantity' => $quantity,
                    ]);

                    $newQuantity = $quantity;
                    $existingItem->update([
                        'quantity' => $newQuantity,
                        'subtotal' => $existingItem->price * $newQuantity,
                    ]);
                } else {
                    Log::info('Creating new cart item', [
                        'product_id' => $productId,
                        'quantity' => $quantity,
                    ]);

                    // Get product price safely
                    $product = Product::with('prices')->find($productId);
                    $price = $product === null ? null : $this->displayPrice($product, $buyer);

                    if ($price === null) {
                        Log::warning('Product not found or has no prices', ['product_id' => $productId]);

                        continue;
                    }

                    $subtotal = $price * $quantity;

                    try {
                        $cart->items()->create([
                            'product_id' => $productId,
                            'price' => $price,
                            'quantity' => $quantity,
                            'subtotal' => $subtotal,
                        ]);
                    } catch (QueryException $e) {
                        // Handle another process might have created duplicate entry - item
                        if ($e->getCode() == 23000 && str_contains($e->getMessage(), 'Duplicate entry')) {
                            Log::info('Duplicate entry detected, updating existing item', ['product_id' => $productId]);

                            // Try to update the existing item
                            $existingItem = $cart->items()->where('product_id', $productId)->first();
                            if ($existingItem) {
                                $newQuantity = $existingItem->quantity + $quantity;
                                $existingItem->update([
                                    'quantity' => $newQuantity,
                                    'subtotal' => $existingItem->price * $newQuantity,
                                ]);
                            }
                        } else {
                            throw $e; // Re-throw if it's not a duplicate entry error
                        }
                    }
                }
            }
            Log::info('End Transaction');
        });

        return $displayOnly !== [] ? $this->storedItems($cart->load('items.product')) : null;
    }

    /**
     * The unit price the cart shows: a legacy product's storefront price, or for linked branch
     * stock the "from" price in the buyer's channel. Checkout prices branch orders exactly.
     */
    private function displayPrice(Product $product, ?User $buyer): mixed
    {
        if (BranchOrderHolds::enabled() && StorefrontListing::query()->where('product_id', $product->id)->exists()) {
            $channel = StorefrontPricing::orderChannel($buyer, [$product->id]);
            $minor = $channel === null ? null : StorefrontPricing::fromPriceMinor($product->id, $channel);

            return $minor === null ? null : Money::toDecimal($minor);
        }

        return $product->prices->first()?->base_price;
    }

    /**
     * The stored cart in the shape the browser cart loads.
     *
     * @return list<array{product_id: int, quantity: int, price: mixed, subtotal: mixed, name: string, description: string, image: string}>
     */
    private function storedItems(Cart $cart): array
    {
        return $cart->items->map(fn (CartItem $item): array => [
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'price' => $item->price,
            'subtotal' => $item->subtotal,
            'name' => $item->product->name ?? '',
            'description' => $item->product->description ?? '',
            'image' => $item->product->primaryImageUrl ?? '',
        ])->values()->all();
    }
}
