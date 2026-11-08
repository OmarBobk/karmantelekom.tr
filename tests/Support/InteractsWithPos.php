<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Inventory\RecordOpeningBalance;
use App\Actions\Pos\FinalizeCheckout;
use App\Actions\Pos\RecordCardApproval;
use App\Actions\Pos\ReviewCheckout;
use App\Actions\Pos\StartTerminalPayment;
use App\Enums\TrackingMode;
use App\Models\CardApproval;
use App\Models\CardTerminal;
use App\Models\CheckoutAttempt;
use App\Models\Customer;
use App\Models\InventoryUnit;
use App\Models\ProductDefinition;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\OperationalStoreSetupSeeder;
use Illuminate\Support\Str;

/**
 * A KT01 counter: an owner, a cashier with counter access, a priced cable in stock, and a
 * priced used phone with two IMEIs.
 */
trait InteractsWithPos
{
    use InteractsWithDeviceLifecycle;
    use InteractsWithOperationalInventory;

    protected User $owner;

    protected Store $store;

    protected ProductDefinition $cable;

    protected ProductDefinition $usedPhone;

    protected InventoryUnit $phone;

    protected string $phoneImei;

    protected string $phoneSecondImei;

    protected function setUpCounter(int $cableStock = 10): void
    {
        $this->setUpOperationalRoles();
        $this->owner = $this->createOwner(['name' => 'Omar']);
        $this->store = Store::factory()->create(['code' => 'KT01', 'name' => 'Karman Fatih']);

        $this->cable = ProductDefinition::factory()->priced('49.90')->create([
            'name' => 'USB-C cable',
            'tracking_mode' => TrackingMode::QUANTITY,
            'barcode' => '8690000000017',
            'sku' => 'CBL-USBC-1M',
        ]);
        app(RecordOpeningBalance::class)($this->owner, $this->store, $this->cable, $cableStock, null, (string) Str::uuid());

        $this->usedPhone = ProductDefinition::factory()->create(['name' => 'iPhone 13', 'model' => '128 GB', 'tracking_mode' => TrackingMode::USED_DEVICE]);
        $this->phoneImei = self::imei('35693803564380');
        $this->phoneSecondImei = self::imei('35693803564398');
        $this->phone = $this->registerDevice($this->owner, $this->store, $this->usedPhone, $this->phoneImei, null, $this->phoneSecondImei);
        $this->phone->update(['selling_price' => '12500.00']);
    }

    protected function createCashier(?Store $store = null, bool $withPos = true, string $name = 'Cashier'): User
    {
        $cashier = User::factory()->create(['name' => $name])->assignRole(OperationalStoreSetupSeeder::EMPLOYEE_ROLE);
        ($store ?? $this->store)->employees()->attach($cashier);

        if ($withPos) {
            $cashier->givePermissionTo(OperationalStoreSetupSeeder::USE_POS_PERMISSION);
        }

        return $cashier->fresh();
    }

    protected function createTerminal(?Store $store = null): CardTerminal
    {
        return CardTerminal::factory()->create(['store_id' => ($store ?? $this->store)->id]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function cableLine(int $quantity = 2, array $extra = []): array
    {
        return ['d'.$this->cable->id => ['definition_id' => $this->cable->id, 'unit_id' => null, 'quantity' => $quantity, ...$extra]];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function phoneLine(?InventoryUnit $unit = null, array $extra = []): array
    {
        $unit ??= $this->phone;

        return ['u'.$unit->id => ['definition_id' => $unit->product_definition_id, 'unit_id' => $unit->id, 'quantity' => 1, ...$extra]];
    }

    /**
     * @param  array<string, array<string, mixed>>  $cart
     */
    protected function review(User $actor, array $cart, ?CheckoutAttempt $attempt = null, ?Store $store = null): CheckoutAttempt
    {
        return app(ReviewCheckout::class)($actor, $store ?? $this->store, $cart, $attempt?->key, $attempt?->revision);
    }

    protected function startTerminalPayment(User $actor, CheckoutAttempt $attempt, CardTerminal $terminal, string $amount, ?string $requestKey = null): CheckoutAttempt
    {
        return app(StartTerminalPayment::class)($actor, $attempt->store, $attempt->key, $attempt->revision, $requestKey ?? (string) Str::uuid(), $terminal->id, $amount)->attempt;
    }

    /**
     * Start the terminal payment unless one is already started, then record its approval for that start.
     */
    protected function approveCard(User $actor, CheckoutAttempt $attempt, CardTerminal $terminal, string $amount, string $reference = 'A12345'): CardApproval
    {
        $paymentKey = $attempt->fresh()->terminal_payment_key
            ?? $this->startTerminalPayment($actor, $attempt, $terminal, $amount)->terminal_payment_key;

        return app(RecordCardApproval::class)($actor, $attempt->store, $attempt->key, $attempt->revision, $paymentKey, $terminal->id, $reference, $amount);
    }

    protected function finalize(User $actor, CheckoutAttempt $attempt, ?string $cash = null, ?int $cashIntakeId = null): Sale
    {
        return app(FinalizeCheckout::class)($actor, $attempt->store, $attempt->key, $attempt->revision, $cash, $cashIntakeId);
    }

    /**
     * An owner-reviewed and completed cable sale for a counter customer of this store, with the
     * given amount left on credit.
     */
    protected function creditSale(Customer $customer, int $cables, string $credit, ?string $cash = null): Sale
    {
        $attempt = app(ReviewCheckout::class)($this->owner, $this->store, $this->cableLine($cables), null, null, $customer->id, $credit);

        return $this->finalize($this->owner, $attempt, $cash);
    }

    protected function cableStock(?Store $store = null): int
    {
        return (int) ($store ?? $this->store)->inventoryBalances()->where('product_definition_id', $this->cable->id)->value('quantity');
    }
}
