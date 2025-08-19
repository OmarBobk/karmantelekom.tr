<?php

namespace App\Livewire\Backend;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

class ActivitiesComponent extends Component
{
    use WithPagination;

    // Search and Filter Properties
    public string $search = '';
    public string $activityType = '';
    public string $userFilter = '';
    public string $dateRange = 'today';
    public string $sortField = 'created_at';
    public string $sortDirection = 'DESC';
    public int $perPage = 15;

    // Activity types for filtering
    public array $activityTypes = [
        'user_login' => 'User Login',
        'user_logout' => 'User Logout',
        'user_created' => 'User Created',
        'user_updated' => 'User Updated',
        'user_deleted' => 'User Deleted',
        'shop_created' => 'Shop Created',
        'shop_updated' => 'Shop Updated',
        'shop_deleted' => 'Shop Deleted',
        'shop_assignment' => 'Shop Assignment',
        'order_created' => 'Order Created',
        'order_updated' => 'Order Updated',
        'order_deleted' => 'Order Deleted',
        'role_assigned' => 'Role Assigned',
        'role_removed' => 'Role Removed',
        'email_verified' => 'Email Verified',
        'password_changed' => 'Password Changed',
        'profile_updated' => 'Profile Updated',
    ];

    protected array $queryString = [
        'search' => ['except' => ''],
        'activityType' => ['except' => ''],
        'userFilter' => ['except' => ''],
        'dateRange' => ['except' => 'today'],
        'sortField' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'DESC'],
        'perPage' => ['except' => 15],
    ];

    /**
     * Update search and reset pagination
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Update activity type filter and reset pagination
     */
    public function updatedActivityType(): void
    {
        $this->resetPage();
    }

    /**
     * Update user filter and reset pagination
     */
    public function updatedUserFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Update date range filter and reset pagination
     */
    public function updatedDateRange(): void
    {
        $this->resetPage();
    }

    /**
     * Update per page and reset pagination
     */
    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    /**
     * Sort by field
     */
    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'ASC' ? 'DESC' : 'ASC';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'ASC';
        }

        $this->resetPage();
    }

    /**
     * Clear all filters
     */
    public function clearAllFilters(): void
    {
        $this->search = '';
        $this->activityType = '';
        $this->userFilter = '';
        $this->dateRange = 'today';
        $this->resetPage();
    }

    /**
     * Apply role-based filtering to a query
     */
    private function applyRoleBasedFilter($query): void
    {
        $user = auth()->user();
        if ($user->hasRole('salesperson')) {
            // Salesperson can only see activities related to their assigned shops
            $assignedShopIds = $user->assignedShops->pluck('id')->toArray();
            
            if (!empty($assignedShopIds)) {
                $query->where(function ($q) use ($assignedShopIds, $user) {
                    // Activities directly related to assigned shops
                    $q->where(function ($subQ) use ($assignedShopIds) {
                        $subQ->where('activity_log.subject_type', 'App\\Models\\Shop')
                             ->whereIn('activity_log.subject_id', $assignedShopIds);
                    })
                    // Activities related to orders from assigned shops
                    ->orWhere(function ($subQ) use ($assignedShopIds) {
                        $subQ->where('activity_log.subject_type', 'App\\Models\\Order')
                             ->whereExists(function ($existsQ) use ($assignedShopIds) {
                                 $existsQ->select(DB::raw(1))
                                        ->from('orders')
                                        ->whereColumn('orders.id', 'activity_log.subject_id')
                                        ->whereIn('orders.shop_id', $assignedShopIds);
                             });
                    })
                    // Activities performed by the salesperson themselves
                    ->orWhere('activity_log.causer_id', $user->id);
                });
            } else {
                // If no shops assigned, only show their own activities
                $query->where('activity_log.causer_id', $user->id);
            }
        } elseif ($user->hasRole('shop_owner')) {
            // Shop owner can only see activities related to their owned shop
            $ownedShop = $user->ownedShop;
            if ($ownedShop) {
                $query->where(function ($q) use ($ownedShop, $user) {
                    // Activities directly related to their owned shop
                    $q->where(function ($subQ) use ($ownedShop) {
                        $subQ->where('activity_log.subject_type', 'App\\Models\\Shop')
                             ->where('activity_log.subject_id', $ownedShop->id);
                    })
                    // Activities related to orders from their shop
                    ->orWhere(function ($subQ) use ($ownedShop) {
                        $subQ->where('activity_log.subject_type', 'App\\Models\\Order')
                             ->whereExists(function ($existsQ) use ($ownedShop) {
                                 $existsQ->select(DB::raw(1))
                                        ->from('orders')
                                        ->whereColumn('orders.id', 'activity_log.subject_id')
                                        ->where('orders.shop_id', $ownedShop->id);
                             });
                    })
                    // Activities performed by the shop owner themselves
                    ->orWhere('activity_log.causer_id', $user->id);
                });
            } else {
                // If no shop owned, only show their own activities
                $query->where('activity_log.causer_id', $user->id);
            }
        }
        // Admin users can see all activities (no additional filtering needed)
    }

    /**
     * Get the base query for activities with role-based filtering
     */
    private function getActivitiesQuery()
    {
        $query = DB::table('activity_log')->select([
            'activity_log.*',
            'users.name as user_name',
            'users.email as user_email',
            'users.profile_photo_path'
        ])
        ->leftJoin('users', 'activity_log.causer_id', '=', 'users.id');

        // Apply role-based filtering
        $this->applyRoleBasedFilter($query);

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('activity_log.description', 'like', '%' . $this->search . '%')
                  ->orWhere('activity_log.subject_type', 'like', '%' . $this->search . '%')
                  ->orWhere('activity_log.log_name', 'like', '%' . $this->search . '%')
                  ->orWhere('users.name', 'like', '%' . $this->search . '%')
                  ->orWhere('users.email', 'like', '%' . $this->search . '%');
            });
        }

        // Apply activity type filter
        if ($this->activityType) {
            $query->where('activity_log.log_name', $this->activityType);
        }

        // Apply user filter
        if ($this->userFilter) {
            $query->where('activity_log.causer_id', $this->userFilter);
        }

        // Apply date range filter
        $this->applyDateRangeFilter($query);

        // Apply sorting
        $query->orderBy($this->sortField, $this->sortDirection);

        return $query;
    }

    /**
     * Apply date range filter to query
     */
    private function applyDateRangeFilter($query): void
    {
        switch ($this->dateRange) {
            case 'today':
                $query->whereDate('activity_log.created_at', today());
                break;
            case 'yesterday':
                $query->whereDate('activity_log.created_at', today()->subDay());
                break;
            case 'last_7_days':
                $query->where('activity_log.created_at', '>=', now()->subDays(7));
                break;
            case 'last_30_days':
                $query->where('activity_log.created_at', '>=', now()->subDays(30));
                break;
            case 'this_month':
                $query->whereYear('activity_log.created_at', now()->year)
                      ->whereMonth('activity_log.created_at', now()->month);
                break;
            case 'last_month':
                $query->whereYear('activity_log.created_at', now()->subMonth()->year)
                      ->whereMonth('activity_log.created_at', now()->subMonth()->month);
                break;
            case 'this_year':
                $query->whereYear('activity_log.created_at', now()->year);
                break;
        }
    }

    /**
     * Get paginated activities
     */
    private function getActivities()
    {
        $activities = $this->getActivitiesQuery()->paginate($this->perPage);

        // Enhance descriptions for shop and order activities
        $activities->getCollection()->transform(function ($activity) {
            if ($activity->log_name === 'shop_assignment') {
                $activity->description = $this->buildDetailedShopAssignmentDescription($activity);
            } elseif ($activity->log_name === 'shop_created') {
                $activity->description = $this->buildDetailedShopCreationDescription($activity);
            } elseif ($activity->log_name === 'order_created') {
                $activity->description = $this->buildDetailedOrderCreationDescription($activity);
            } elseif ($activity->log_name === 'order_updated') {
                $activity->description = $this->buildDetailedOrderUpdateDescription($activity);
            }
            return $activity;
        });

        return $activities;
    }

    /**
     * Build detailed description for shop assignment activities
     */
    private function buildDetailedShopAssignmentDescription($activity): string
    {
        $properties = json_decode($activity->properties, true);

        if (!$properties) {
            return $activity->description ?? 'Shop assignment activity';
        }

        $shopName = $properties['shop_name'] ?? 'Unknown Shop';
        $shopPhone = $properties['shop_phone'] ?? 'N/A';
        $shopAddress = $properties['shop_address'] ?? 'N/A';
        $salespersonName = $properties['salesperson_name'] ?? 'Unknown Salesperson';
        $salespersonEmail = $properties['salesperson_email'] ?? 'N/A';
        $assignedByName = $properties['assigned_by_name'] ?? 'System';
        $assignmentType = $properties['assignment_type'] ?? 'new_assignment';
        $previousSalespersonName = $properties['previous_salesperson_name'] ?? null;
        $assignmentTimestamp = $properties['assignment_timestamp'] ?? null;

        // Format timestamp for display
        $timestampText = '';
        if ($assignmentTimestamp) {
            $timestamp = \Carbon\Carbon::parse($assignmentTimestamp);
            $timestampText = " at {$timestamp->format('M j, Y \a\t H:i')}";
        }

        if ($assignmentType === 'reassignment' && $previousSalespersonName) {
            return "🔄 Shop Reassignment{$timestampText}: Shop '{$shopName}' has been reassigned from {$previousSalespersonName} to {$salespersonName} ({$salespersonEmail}) by {$assignedByName}. This change affects the shop's management responsibilities and all future orders will be handled by the new salesperson. Shop Details: 📞 {$shopPhone} | 📍 {$shopAddress}";
        }

        return "✅ New Shop Assignment{$timestampText}: Shop '{$shopName}' has been assigned to {$salespersonName} ({$salespersonEmail}) by {$assignedByName}. The salesperson is now responsible for managing this shop's operations, orders, and customer relationships. Shop Details: 📞 {$shopPhone} | 📍 {$shopAddress}";
    }

    /**
     * Build detailed description for shop creation activities
     */
    private function buildDetailedShopCreationDescription($activity): string
    {
        $properties = json_decode($activity->properties, true);

        if (!$properties) {
            return $activity->description ?? 'Shop creation activity';
        }

        $shopName = $properties['shop_name'] ?? 'Unknown Shop';
        $createdByName = $properties['created_by_name'] ?? 'System';
        $shopPhone = $properties['shop_phone'] ?? 'N/A';
        $shopAddress = $properties['shop_address'] ?? 'N/A';
        $creationTimestamp = $properties['creation_timestamp'] ?? null;

        // Format timestamp for display
        $timestampText = '';
        if ($creationTimestamp) {
            $timestamp = \Carbon\Carbon::parse($creationTimestamp);
            $timestampText = " at {$timestamp->format('M j, Y \a\t H:i')}";
        }

        return "🏪 New Shop Created{$timestampText}: Shop '{$shopName}' has been created by {$createdByName}. This new shop is now available in the system and can be assigned to salespersons for management. Shop Details: 📞 {$shopPhone} | 📍 {$shopAddress}";
    }

    /**
     * Build detailed description for order creation activities
     */
    private function buildDetailedOrderCreationDescription($activity): string
    {
        $properties = json_decode($activity->properties, true);
        $userName = $activity->user_name ?? 'Unknown User';
        $orderId = $properties['order_id'] ?? $activity->subject_id ?? 'Unknown';

        // Extract the original description and add the prefix
        $originalDescription = $activity->description ?? "Order #{$orderId} has been created";

        return "👤 {$userName} - {$originalDescription}";
    }

    /**
     * Build detailed description for order update activities
     */
    private function buildDetailedOrderUpdateDescription($activity): string
    {
        $properties = json_decode($activity->properties, true);
        $userName = $activity->user_name ?? 'Unknown User';
        $orderId = $properties['order_id'] ?? $activity->subject_id ?? 'Unknown';

        // Extract the original description and add the prefix
        $originalDescription = $activity->description ?? "Order #{$orderId} has been updated";

        return "👤 {$userName} - {$originalDescription}";
    }

    /**
     * Get available users for filter
     */
    private function getUsers()
    {
        $user = auth()->user();
        
        if ($user->hasRole('admin')) {
            // Admin can see all users
            return User::orderBy('name')->get();
        } elseif ($user->hasRole('salesperson')) {
            // Salesperson can only see users related to their assigned shops
            $assignedShopIds = $user->assignedShops->pluck('id')->toArray();
            
            if (!empty($assignedShopIds)) {
                return User::where(function ($query) use ($assignedShopIds, $user) {
                    // Users who own the assigned shops
                    $query->whereHas('ownedShop', function ($q) use ($assignedShopIds) {
                        $q->whereIn('id', $assignedShopIds);
                    })
                    // Users who are assigned to the same shops
                    ->orWhereHas('assignedShops', function ($q) use ($assignedShopIds) {
                        $q->whereIn('id', $assignedShopIds);
                    })
                    // The salesperson themselves
                    ->orWhere('id', $user->id);
                })->orderBy('name')->get();
            } else {
                // If no shops assigned, only show themselves
                return User::where('id', $user->id)->orderBy('name')->get();
            }
        } elseif ($user->hasRole('shop_owner')) {
            // Shop owner can only see users related to their owned shop
            $ownedShop = $user->ownedShop;
            
            if ($ownedShop) {
                return User::where(function ($query) use ($ownedShop, $user) {
                    // The salesperson assigned to their shop
                    $query->where('id', $ownedShop->salesperson_id)
                    // The shop owner themselves
                    ->orWhere('id', $user->id);
                })->orderBy('name')->get();
            } else {
                // If no shop owned, only show themselves
                return User::where('id', $user->id)->orderBy('name')->get();
            }
        }
        
        // Default fallback
        return collect();
    }

    /**
     * Get activity statistics
     */
    private function getActivityStats(): array
    {
        $todayQuery = DB::table('activity_log')->whereDate('created_at', today());
        $this->applyRoleBasedFilter($todayQuery);
        $today = $todayQuery->count();

        $thisWeekQuery = DB::table('activity_log')->where('created_at', '>=', now()->startOfWeek());
        $this->applyRoleBasedFilter($thisWeekQuery);
        $thisWeek = $thisWeekQuery->count();

        $thisMonthQuery = DB::table('activity_log')->where('created_at', '>=', now()->startOfMonth());
        $this->applyRoleBasedFilter($thisMonthQuery);
        $thisMonth = $thisMonthQuery->count();

        $totalQuery = DB::table('activity_log');
        $this->applyRoleBasedFilter($totalQuery);
        $total = $totalQuery->count();

        return [
            'today' => $today,
            'this_week' => $thisWeek,
            'this_month' => $thisMonth,
            'total' => $total,
        ];
    }

    /**
     * Get recent activity types
     */
    private function getRecentActivityTypes(): array
    {
        $query = DB::table('activity_log')
            ->select('log_name', DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subDays(7));
        
        $this->applyRoleBasedFilter($query);
        
        return $query->groupBy('log_name')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
    }

    /**
     * Get most active users
     */
    private function getMostActiveUsers(): array
    {
        $query = DB::table('activity_log')
            ->select('users.name', 'users.email', DB::raw('count(activity_log.id) as activity_count'))
            ->join('users', 'activity_log.causer_id', '=', 'users.id')
            ->where('activity_log.created_at', '>=', now()->subDays(30));
        
        $this->applyRoleBasedFilter($query);
        
        return $query->groupBy('users.id', 'users.name', 'users.email')
            ->orderBy('activity_count', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
    }

    #[Layout('layouts.backend')]
    #[Title('Activities')]
    public function render()
    {
        // Check authorization
        $user = auth()->user();
        if (!$user->hasRole(['admin', 'salesperson', 'shop_owner'])) {
            abort(403, 'Unauthorized access to activities.');
        }

        return view('livewire.backend.activities-component', [
            'activities' => $this->getActivities(),
            'users' => $this->getUsers(),
            'stats' => $this->getActivityStats(),
            'recentActivityTypes' => $this->getRecentActivityTypes(),
            'mostActiveUsers' => $this->getMostActiveUsers(),
        ]);
    }
}
