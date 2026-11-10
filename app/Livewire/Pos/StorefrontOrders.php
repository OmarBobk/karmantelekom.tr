<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Actions\Storefront\BranchOrderHolds;
use App\Actions\Storefront\CancelBranchOrder;
use App\Actions\Storefront\ConfirmBranchOrder;
use App\Actions\Storefront\QuoteBranchOrder;
use App\Actions\Storefront\RequestBranchOrderPriceChange;
use App\Enums\StorefrontOrderStatus;
use App\Enums\UnitStatus;
use App\Livewire\Pos\Concerns\UsesPosLocale;
use App\Models\InventoryBalance;
use App\Models\InventoryUnit;
use App\Models\Store;
use App\Models\StorefrontOrder;
use App\Models\StorefrontOrderLine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Storefront orders one branch fulfills: pending orders to confirm with exact devices and a
 * pickup window, to reprice, or to reject with a reason the buyer sees; confirmed orders to
 * collect at the counter or cancel.
 * A confirmed order whose held stock is short after a count or device change is flagged for
 * review, and the counter refuses its pickup until the stock is restored or the order cancelled.
 * Every action re-authorizes and rechecks on the server.
 */
class StorefrontOrders extends Component
{
    use UsesPosLocale;
    use WithPagination;

    public const PER_PAGE = 20;

    #[Locked]
    public int $storeId;

    #[Url(except: 'open')]
    public string $tab = 'open';

    #[Locked]
    public ?int $confirmingId = null;

    /** @var array<int|string, int|string|null> Chosen device by order line id. */
    public array $deviceChoices = [];

    public string $pickupHours = '';

    #[Locked]
    public ?int $cancellingId = null;

    /** Whether the reason form rejects a pending order rather than cancelling a confirmed one. */
    #[Locked]
    public bool $rejecting = false;

    public string $reason = '';

    public function mount(Store $store): void
    {
        $this->storeId = $store->id;
        $this->authorizePage();
        $this->tab = $this->tab === 'closed' ? 'closed' : 'open';
    }

    public function updatedTab(): void
    {
        $this->tab = $this->tab === 'closed' ? 'closed' : 'open';
        $this->closeForms();
        $this->resetPage();
    }

    public function openConfirm(int $orderId): void
    {
        $store = $this->authorizePage();
        $order = StorefrontOrder::query()->where('store_id', $store->id)->with('lines.productDefinition')->findOrFail($orderId);

        $this->closeForms();
        $this->confirmingId = $order->id;
        $this->pickupHours = (string) StorefrontOrder::DEFAULT_PICKUP_HOURS;
        $taken = [];

        foreach ($order->lines->filter(fn (StorefrontOrderLine $line): bool => $line->tracksUnits()) as $line) {
            $choice = $line->proposed_unit_id ?? QuoteBranchOrder::eligibleDevices($store->id, $line->productDefinition)->whereNotIn('units.id', $taken)->value('units.id');
            $this->deviceChoices[$line->id] = $choice;
            $taken[] = $choice;
        }
    }

    public function confirm(ConfirmBranchOrder $confirm): void
    {
        if ($this->confirmingId === null) {
            return;
        }

        $confirm(auth()->user(), $this->authorizePage(), $this->confirmingId, $this->deviceChoices, $this->pickupHours);
        $this->done(__('storefront.orders.confirmed_message'));
    }

    public function requestPrices(RequestBranchOrderPriceChange $request): void
    {
        if ($this->confirmingId === null) {
            return;
        }

        $request(auth()->user(), $this->authorizePage(), $this->confirmingId, $this->deviceChoices);
        $this->done(__('storefront.orders.prices_requested_message'));
    }

    public function openCancel(int $orderId): void
    {
        $store = $this->authorizePage();
        $order = StorefrontOrder::query()->where('store_id', $store->id)->findOrFail($orderId);

        $this->closeForms();
        $this->cancellingId = $order->id;
        $this->rejecting = $order->isPending();
    }

    public function cancel(CancelBranchOrder $cancel): void
    {
        if ($this->cancellingId === null) {
            return;
        }

        if ($this->rejecting) {
            $cancel->rejectByBranch(auth()->user(), $this->authorizePage(), $this->cancellingId, $this->reason);
            $this->done(__('storefront.orders.rejected_message'));

            return;
        }

        $cancel->byBranch(auth()->user(), $this->authorizePage(), $this->cancellingId, $this->reason);
        $this->done(__('storefront.orders.cancelled_message'));
    }

    public function closeForms(): void
    {
        $this->confirmingId = null;
        $this->cancellingId = null;
        $this->rejecting = false;
        $this->deviceChoices = [];
        $this->pickupHours = '';
        $this->reason = '';
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $store = $this->authorizePage();
        $open = $this->tab === 'open';

        $orders = StorefrontOrder::query()
            ->where('store_id', $store->id)
            ->whereIn('status', $open ? [StorefrontOrderStatus::PENDING, StorefrontOrderStatus::CONFIRMED] : StorefrontOrderStatus::closed())
            ->with(['buyer:id,name', 'shop:id,name', 'sale:id,number', 'lines.productDefinition:id,name,model,tracking_mode', 'lines.unit', 'lines.unit.identifiers'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [StorefrontOrderStatus::PENDING->value])
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        return view('livewire.pos.storefront-orders', [
            'store' => $store,
            'open' => $open,
            'orders' => $orders,
            'short' => $this->shortOrders($store, $orders->getCollection()),
            'eligibleDevices' => $this->eligibleDevices($store, $orders->getCollection()),
            'canManage' => auth()->user()->can('manageStorefrontOrders', $store),
            ...$this->localeViewData(),
        ])->layout('layouts.pos', ['posStore' => $store])->title(__('storefront.orders.title'));
    }

    /**
     * Ids of confirmed orders whose held stock is short: the branch balance is below what all
     * orders hold of a product, or a held device left the branch or stopped being available.
     *
     * @param  Collection<int, StorefrontOrder>  $orders
     * @return list<int>
     */
    private function shortOrders(Store $store, Collection $orders): array
    {
        $confirmed = $orders->filter(fn (StorefrontOrder $order): bool => $order->displayStatus() === StorefrontOrderStatus::CONFIRMED);
        $definitionIds = $confirmed->flatMap(fn (StorefrontOrder $order) => $order->lines->reject(fn (StorefrontOrderLine $line): bool => $line->tracksUnits())->pluck('product_definition_id'))->unique()->values()->all();
        $held = BranchOrderHolds::heldQuantities($store->id, $definitionIds);
        $balances = InventoryBalance::query()->where('store_id', $store->id)->whereIn('product_definition_id', $definitionIds)->pluck('quantity', 'product_definition_id');

        return $confirmed->filter(fn (StorefrontOrder $order): bool => $order->lines->contains(fn (StorefrontOrderLine $line): bool => $line->tracksUnits()
            ? $line->unit === null || $line->unit->store_id !== $store->id || $line->unit->status !== UnitStatus::AVAILABLE
            : (int) ($balances[$line->product_definition_id] ?? 0) < ($held[$line->product_definition_id] ?? 0)))
            ->modelKeys();
    }

    /**
     * Devices the branch may hold for the order being confirmed, by order line id.
     *
     * @param  Collection<int, StorefrontOrder>  $orders
     * @return array<int, Collection<int, InventoryUnit>>
     */
    private function eligibleDevices(Store $store, Collection $orders): array
    {
        $order = $orders->firstWhere('id', $this->confirmingId);

        if ($order === null) {
            return [];
        }

        $devices = [];

        foreach ($order->lines->filter(fn (StorefrontOrderLine $line): bool => $line->tracksUnits()) as $line) {
            $devices[$line->id] = QuoteBranchOrder::eligibleDevices($store->id, $line->productDefinition)->with('identifiers')->get();
        }

        return $devices;
    }

    private function done(string $message): void
    {
        $this->closeForms();
        $this->dispatch('notify', ['type' => 'success', 'message' => $message]);
    }

    private function authorizePage(): Store
    {
        abort_unless(BranchOrderHolds::enabled(), 404);

        $store = Store::query()->findOrFail($this->storeId);
        $this->authorize('usePos', $store);

        return $store;
    }
}
