<?php

namespace App\Livewire\Backend\Orders;

use App\Models\Order;
use App\Enums\OrderStatus;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Invoice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Browsershot\Browsershot;
use Throwable;

class OrdersManager extends Component
{
    use WithPagination;

    public array $selectedOrders = [];
    public bool $selectAll = false;
    public string $bulkAction = '';
    public bool $showBulkActionModal = false;
    public string $statusFilter = '';
    public string $salespersonFilter = '';
    public string $search = '';
    public ?Order $selectedOrder = null;
    public bool $showOrderDetailsModal = false;
    public string $fromDate = '';
    public string $toDate = '';
    public string $sortBy = 'id';
    public string $sortDirection = 'asc';

    protected array $queryString = [
        'statusFilter' => ['except' => ''],
        'salespersonFilter' => ['except' => ''],
        'search' => ['except' => ''],
        'fromDate' => ['except' => ''],
        'toDate' => ['except' => ''],
        'sortBy' => ['except' => 'id'],
        'sortDirection' => ['except' => 'asc'],
    ];

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSalespersonFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function clearAllFilters(): void
    {
        $this->reset(['statusFilter', 'salespersonFilter', 'search', 'fromDate', 'toDate', 'sortBy', 'sortDirection']);
        $this->resetPage();
    }

    public function sortByColumn(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function getOrdersProperty(): LengthAwarePaginator
    {
        try {
            $user_id = auth()->id();
            $query = Order::query()
                ->with(['salesperson', 'items', 'shop'])
                ->when($this->search, function ($query) {
                    $search = $this->search;
                    $query->where(function ($q) use ($search) {
                        $q->where('id', $search)
                            ->orWhereHas('shop', fn($q2) => $q2->where('name', 'like', "%$search%"))
                            ->orWhereHas('salesperson', fn($q3) => $q3->where('name', 'like', "%$search%"));
                    });
                })
                ->whereHas('shop', function ($query) {
                    if (!auth()->user()->hasRole('admin')) {
                        $query->where('user_id', auth()->id());
                    }
                })
                ->when($this->statusFilter, function ($query) {
                    $query->where('status', $this->statusFilter);
                })
                ->when($this->salespersonFilter && auth()->user()->hasRole('admin'), function ($query) {
                    $query->whereHas('shop', function ($q) {
                        $q->where('user_id', $this->salespersonFilter);
                    });
                })
                ->when($this->fromDate, function ($query) {
                    $query->whereDate('created_at', '>=', \Carbon\Carbon::parse($this->fromDate)->toDateString());
                })
                ->when($this->toDate, function ($query) {
                    $query->whereDate('created_at', '<=', \Carbon\Carbon::parse($this->toDate)->toDateString());
                });

            // Apply sorting
            switch ($this->sortBy) {
                case 'shop':
                    $query->join('shops', 'orders.shop_id', '=', 'shops.id')
                          ->orderBy('shops.name', $this->sortDirection)
                          ->select('orders.*');
                    break;
                case 'salesperson':
                    $query->join('users', 'orders.user_id', '=', 'users.id')
                          ->orderBy('users.name', $this->sortDirection)
                          ->select('orders.*');
                    break;
                case 'total':
                    $query->orderBy('total_price', $this->sortDirection);
                    break;
                case 'status':
                    $query->orderBy('status', $this->sortDirection);
                    break;
                case 'created_at':
                    $query->orderBy('created_at', $this->sortDirection);
                    break;
                default:
                    $query->orderBy('id', 'desc');
                    break;
            }

            return $query->paginate(10);
        } catch (Throwable $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage()
            ]);
            return Order::query()->whereRaw('0=1')->paginate(10);
        }
    }

    public function getSalespeopleProperty()
    {
        return User::whereHas('roles', function ($query) {
            $query->where('name', 'salesperson');
        })->orderBy('name')->get();
    }

    #[Layout('layouts.backend')]
    #[Title('Orders Manager')]
    public function render()
    {
        $statuses = OrderStatus::cases();

        return view('livewire.backend.orders.orders-manager', [
            'orders' => $this->orders,
            'statuses' => $statuses,
            'availableStatuses' => $this->getAvailableStatuses(),
            'salespeople' => $this->salespeople,
        ]);
    }

    public function updatedSelectAll($value): void
    {
        if ($value) {
            $this->selectedOrders = Order::pluck('id')->toArray();
        } else {
            $this->selectedOrders = [];
        }
    }

    public function confirmBulkAction(): void
    {
        $this->validate([
            'bulkAction' => 'required|in:delete,export',
        ]);

        $this->showBulkActionModal = true;
    }

    public function performBulkAction(): void
    {
        if ($this->bulkAction === 'delete') {
            Order::whereIn('id', $this->selectedOrders)->delete();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Selected orders deleted successfully.'
            ]);
        } elseif ($this->bulkAction === 'export') {
            // Implement export logic here
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Export initiated for selected orders.'
            ]);
        }

        $this->reset(['selectedOrders', 'selectAll', 'bulkAction', 'showBulkActionModal']);
    }

    public function cancelBulkAction(): void
    {
        $this->reset(['bulkAction', 'showBulkActionModal']);
    }

    public function showOrderDetails($orderId): void
    {
        $this->selectedOrder = Order::with(['items.product', 'shop', 'salesperson'])
            ->findOrFail($orderId);
        $this->showOrderDetailsModal = true;
    }

    public function closeOrderDetailsModal(): void
    {
        $this->showOrderDetailsModal = false;
        $this->selectedOrder = null;
    }

    public function updateOrderStatus($orderId, $newStatus): void
    {
        try {
            // Validate the new status
            if (!in_array($newStatus, OrderStatus::values())) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Invalid order status provided.'
                ]);
                return;
            }

            $order = Order::findOrFail($orderId);

            // Check if user has permission to update this order
            if (!auth()->user()->can('update', $order)) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'You do not have permission to update this order.'
                ]);
                return;
            }

            $oldStatus = $order->status->value;
            $originalData = $order->toArray();

            $order->update(['status' => $newStatus]);

            // Dispatch OrderUpdated event
            \App\Events\OrderUpdated::dispatch($order, $originalData, auth()->id());

            // Refresh selectedOrder if it's currently being viewed
            if ($this->selectedOrder && $this->selectedOrder->id === $order->id) {
                $this->selectedOrder = $order->fresh(['items.product', 'shop', 'salesperson']);
            }

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Order #{$order->id} status updated from " . ucfirst($oldStatus) . " to " . ucfirst($newStatus)
            ]);

        } catch (Throwable $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to update order status: ' . $e->getMessage()
            ]);
        }
    }

    public function getAvailableStatuses(): array
    {
        return OrderStatus::cases();
    }

    public function exportOrderToPdf($orderId)
    {
        try {

            $order = Order::with(['items.product', 'shop', 'salesperson'])
                ->findOrFail($orderId);

            $customer = new Buyer([
                'serial' => $order->id,
                'date' => $order->updated_at->format('d M Y'),
                'invoice_records' => $order->items,
                'total' => $order->total_price
            ]);

            $invoice = Invoice::make()
                ->template('indirimgo')
                ->buyer($customer)
                ->discountByPercent(10)
                ->taxRate(18)
                ->addItem((new InvoiceItem())->pricePerUnit(2));


            $html = view('vendor.invoices.templates.indirimgo', compact('invoice'))->render();

            return response()->stream(function () use ($html) {
                print Browsershot::html($html)
                    ->format('A4')
                    ->noSandbox()
                    ->waitUntilNetworkIdle()
                    ->showBackground()
                    ->pdf();
            }, 200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="invoice.pdf"',
                ]);

//            return  $invoice->stream();

//
//            $this->dispatch('notify', [
//                'type' => 'success',
//                'message' => 'PDF download started. Please check your downloads folder.'
//            ]);
//
//            // Dispatch browser event to trigger PDF download
//            $this->dispatch('download-pdf', url: route('subdomain.orders.pdf', $order->id));

        } catch (Throwable $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to export order: ' . $e->getMessage()
            ]);

            dd($e->getMessage());
        }
    }
}
