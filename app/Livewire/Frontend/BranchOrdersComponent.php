<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use App\Actions\Storefront\AcceptBranchOrderPrice;
use App\Actions\Storefront\BranchOrderHolds;
use App\Actions\Storefront\CancelBranchOrder;
use App\Models\StorefrontOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The signed-in buyer's storefront orders for branch pickup: status, branch, prices, and the
 * pickup deadline. A pending order can be cancelled, and the exact price proposal shown accepted.
 * A rejected order shows the branch's reason.
 * Every action rechecks ownership and state on the server.
 */
class BranchOrdersComponent extends Component
{
    use WithPagination;

    public const PER_PAGE = 10;

    public function mount(): void
    {
        abort_unless(BranchOrderHolds::enabled(), 404);
    }

    public function cancel(CancelBranchOrder $cancel, int $orderId): void
    {
        abort_unless(BranchOrderHolds::enabled(), 404);

        $cancel->byBuyer(Auth::user(), $orderId);
        $this->notify(__('main.branch_orders.cancelled'));
    }

    /**
     * @param  string  $proposal  Fingerprint of the proposal the page showed; a newer one is refused and shown instead.
     */
    public function acceptPrices(AcceptBranchOrderPrice $accept, int $orderId, string $proposal): void
    {
        abort_unless(BranchOrderHolds::enabled(), 404);

        $accept(Auth::user(), $orderId, $proposal);
        $this->notify(__('main.branch_orders.prices_accepted'));
    }

    #[Layout('layouts.frontend')]
    public function render(): View
    {
        $orders = StorefrontOrder::query()
            ->where('buyer_id', Auth::id())
            ->with(['store:id,name,code', 'shop:id,name', 'lines.product:id,name', 'lines.unit:id,condition', 'lines.proposedUnit:id,condition'])
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return view('livewire.frontend.branch-orders-component', ['orders' => $orders])->title(__('main.branch_orders.title'));
    }

    private function notify(string $message): void
    {
        $this->resetErrorBag();
        $this->dispatch('notify', [['type' => 'alert-success', 'message' => $message, 'sec' => 4000]]);
    }
}
