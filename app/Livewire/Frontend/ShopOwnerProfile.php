<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

class ShopOwnerProfile extends Component
{
    public string $activeTab = 'basic';
    public ?Shop $shop = null;
    public array $metrics = [];
    public array $recentOrders = [];
    public array $topProducts = [];
    
    // Modal and form properties
    public bool $showEditModal = false;
    public bool $showPasswordModal = false;
    public string $shopName = '';
    public string $shopAddress = '';
    public string $shopPhone = '';
    public string $shopTaxNumber = '';
    public string $ownerName = '';
    
    // Password change properties
    public string $currentPassword = '';
    public string $newPassword = '';
    public string $confirmPassword = '';

    public function mount(): void
    {
        $user = Auth::user();
        
        if (!$user || !$user->isShopOwner()) {
            abort(403, 'Access denied. Only shop owners can view this page.');
        }

        $this->shop = $user->ownedShop;
        
        if (!$this->shop) {
            abort(404, 'Shop not found.');
        }

        try {
            $this->loadMetrics();
            $this->loadRecentOrders();
            $this->loadTopProducts();
            $this->loadShopData();
        } catch (\Exception $e) {
            logger()->error('Error loading shop owner profile data: ' . $e->getMessage());
            // Continue with empty data rather than crashing
        }
    }

    private function loadMetrics(): void
    {
        $this->metrics = [
            'total_orders' => $this->shop->orders()->count(),
            'total_revenue' => $this->shop->orders()->sum('total_price') ?? 0,
            'pending_orders' => $this->shop->orders()->where('status', 'pending')->count(),
            'completed_orders' => $this->shop->orders()->whereIn('status', ['delivered', 'completed'])->count(),
            'avg_order_value' => $this->shop->orders()->avg('total_price') ?? 0,
            'total_products' => \App\Models\OrderItem::whereIn('order_id', $this->shop->orders()->pluck('id'))->distinct('product_id')->count(),
        ];
    }

    private function loadRecentOrders(): void
    {
        $this->recentOrders = $this->shop->orders()
            ->with(['customer', 'items.product'])
            ->latest()
            ->take(5)
            ->get()
            ->toArray();
    }

    private function loadTopProducts(): void
    {
        $this->topProducts = \App\Models\OrderItem::query()
            ->select('product_id', \Illuminate\Support\Facades\DB::raw('SUM(quantity) as total_quantity'))
            ->whereIn('order_id', function ($query) {
                $query->select('id')
                    ->from('orders')
                    ->where('shop_id', $this->shop->id);
            })
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->with('product:id,name,price')
            ->limit(5)
            ->get()
            ->toArray();
    }

    private function loadShopData(): void
    {
        $this->shopName = $this->shop->name;
        $this->shopAddress = $this->shop->address;
        $this->shopPhone = $this->shop->phone ?? '';
        $this->shopTaxNumber = $this->shop->tax_number ?? '';
        $this->ownerName = Auth::user()->name;
    }

    public function openEditModal(): void
    {
        $this->loadShopData();
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->resetValidation();
    }

    public function openPasswordModal(): void
    {
        $this->showPasswordModal = true;
        $this->resetPasswordFields();
    }

    public function closePasswordModal(): void
    {
        $this->showPasswordModal = false;
        $this->resetPasswordFields();
        $this->resetValidation();
    }

    private function resetPasswordFields(): void
    {
        $this->currentPassword = '';
        $this->newPassword = '';
        $this->confirmPassword = '';
    }

    public function saveShopInfo(): void
    {
        $this->validate([
            'shopName' => 'required|string|max:255',
            'shopAddress' => 'required|string|max:500',
            'shopPhone' => 'nullable|string|max:20',
            'shopTaxNumber' => 'nullable|string|max:50',
            'ownerName' => 'required|string|max:255',
        ]);

        try {
            $updateData = [
                'name' => $this->shopName,
                'address' => $this->shopAddress,
                'phone' => $this->shopPhone,
                'tax_number' => $this->shopTaxNumber,
            ];

            // Update shop information
            $this->shop->update($updateData);

            // Update user name
            Auth::user()->update(['name' => $this->ownerName]);

            $this->closeEditModal();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Shop information updated successfully!',
                'sec' => 3000
            ]);

        } catch (\Exception $e) {
            logger()->error('Error updating shop information: ' . $e->getMessage());
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to update shop information. Please try again.',
                'sec' => 3000
            ]);
        }
    }

    public function changePassword(): void
    {
        $this->validate([
            'currentPassword' => 'required|string',
            'newPassword' => 'required|string|min:8|confirmed:confirmPassword',
            'confirmPassword' => 'required|string|min:8',
        ], [
            'newPassword.confirmed' => 'The new password confirmation does not match.',
            'newPassword.min' => 'The new password must be at least 8 characters.',
        ]);

        try {
            $user = Auth::user();
            
            // Verify current password
            if (!Hash::check($this->currentPassword, $user->password)) {
                $this->addError('currentPassword', 'The current password is incorrect.');
                return;
            }

            // Update password
            $user->update([
                'password' => Hash::make($this->newPassword)
            ]);

            $this->closePasswordModal();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Password changed successfully!',
                'sec' => 3000
            ]);

        } catch (\Exception $e) {
            logger()->error('Error changing password: ' . $e->getMessage());
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to change password. Please try again.',
                'sec' => 3000
            ]);
        }
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    #[Layout('layouts.frontend')]
    #[Title('Shop Owner Profile')]
    public function render()
    {
        return view('livewire.frontend.shop-owner-profile');
    }
}
