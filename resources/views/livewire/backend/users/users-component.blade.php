<div x-data="{
    toasts: [],
    showToast(message, type = 'success') {
        const id = Date.now();
        this.toasts.push({ id, message, type });
        setTimeout(() => {
            this.removeToast(id);
        }, 5000);
    },
    removeToast(id) {
        this.toasts = this.toasts.filter(toast => toast.id !== id);
    }
}"
x-init="
    @if (session()->has('message'))
        showToast('{{ session('message') }}', 'success');
    @endif
    @if (session()->has('error'))
        showToast('{{ session('error') }}', 'error');
    @endif
"
class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 py-8">

    <!-- Custom Styles -->
    <style>


        @keyframes progress {
            from { width: 100%; }
            to { width: 0%; }
        }

        .filter-dropdown {
            backdrop-filter: blur(8px);
            background: rgba(255, 255, 255, 0.95);
        }

        .search-input:focus {
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .table-hover:hover {
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.05) 0%, rgba(99, 102, 241, 0.05) 100%);
        }
    </style>

    <!-- Toast Notification System -->
    <div class="fixed top-4 right-4 z-[60] space-y-2">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="true"
                 x-transition:enter="transform ease-out duration-300 transition"
                 x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
                 x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="max-w-sm w-full bg-white/95 backdrop-blur-sm shadow-2xl rounded-2xl pointer-events-auto ring-1 ring-black/5 overflow-hidden border border-white/20">

                <div class="p-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <template x-if="toast.type === 'success'">
                                <div class="w-8 h-8 bg-gradient-to-r from-green-400 to-emerald-500 rounded-full flex items-center justify-center shadow-lg">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </template>
                            <template x-if="toast.type === 'error'">
                                <div class="w-8 h-8 bg-gradient-to-r from-red-400 to-rose-500 rounded-full flex items-center justify-center shadow-lg">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </template>
                        </div>
                        <div class="ml-3 flex-1">
                            <p x-text="toast.message"
                               :class="toast.type === 'success' ? 'text-gray-800' : 'text-gray-800'"
                               class="text-sm font-semibold leading-5"></p>
                        </div>
                        <div class="ml-4 flex-shrink-0 flex">
                            <button @click="removeToast(toast.id)"
                                    class="bg-transparent rounded-full inline-flex text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Progress bar -->
                <div class="bg-gray-100 h-1.5">
                    <div :class="toast.type === 'success' ? 'bg-gradient-to-r from-green-400 to-emerald-500' : 'bg-gradient-to-r from-red-400 to-rose-500'"
                         class="h-1.5 rounded-b-2xl"
                         style="animation: progress 5s linear forwards;">
                    </div>
                </div>
            </div>
        </template>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Enhanced Header -->
        <div class="mb-10">
            <div class="bg-white/60 backdrop-blur-sm rounded-3xl p-8 shadow-xl border border-white/20">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center space-x-4">
                            <div class="p-3 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-2xl shadow-lg">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold bg-gradient-to-r from-gray-900 via-indigo-800 to-purple-800 bg-clip-text text-transparent">
                                    Users Management
                                </h1>
                                <p class="mt-2 text-sm text-gray-600">
                                    Manage system users, roles, and permissions with advanced controls
                                </p>
                                <div class="mt-3 flex items-center space-x-4 text-sm text-gray-500">
                                    <span class="inline-flex items-center">
                                        <div class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></div>
                                        {{ $users->total() }} Total Users
                                    </span>
                                    <span class="inline-flex items-center">
                                        <div class="w-2 h-2 bg-blue-400 rounded-full mr-2"></div>
                                        {{ count($selectedUsers) }} Selected
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 lg:mt-0 lg:ml-8">
                        <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-3">
                            <!-- Export Button -->
                            <button class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-xl shadow-sm text-sm font-medium text-gray-700 bg-white/80 backdrop-blur-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Export
                            </button>

                            <!-- Add User Button -->
                            <button
                                wire:click="openAddModal"
                                class="inline-flex items-center px-6 py-2.5 border border-transparent rounded-xl shadow-lg text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200 transform hover:scale-105"
                            >
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Add User
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Filters -->
        <div class="bg-white/70 backdrop-blur-lg rounded-2xl shadow-xl mb-8 p-6 border border-white/20">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-xl">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.414A1 1 0 013 6.707V4z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800">Filters & Search</h3>
                </div>

                @if($search || $roleFilter || $verificationFilter)
                    <button
                        wire:click="clearAllFilters"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors duration-200"
                    >
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Clear All
                    </button>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-5">
                <!-- Enhanced Search -->
                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search Users</label>
                    <div class="relative group">
                        <input
                            wire:model.live.debounce.300ms="search"
                            type="text"
                            placeholder="Search by name or email..."
                            class="search-input w-full pl-11 pr-10 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200 bg-white/80 backdrop-blur-sm"
                        />
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400 group-focus-within:text-indigo-500 transition-colors duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        @if($search)
                            <button
                                wire:click="$set('search', '')"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-red-500 transition-colors duration-200"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Enhanced Role Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                    <div class="relative">
                        <select wire:model.live="roleFilter" class="filter-dropdown appearance-none w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                            <option value="">All Roles</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                            @endforeach
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Verification Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <div class="relative">
                        <select wire:model.live="verificationFilter" class="filter-dropdown appearance-none w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                            <option value="">All Status</option>
                            <option value="verified">✓ Verified</option>
                            <option value="unverified">✗ Unverified</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Per Page -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Show</label>
                    <div class="relative">
                        <select wire:model.live="perPage" class="filter-dropdown appearance-none w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                            <option value="10">10 per page</option>
                            <option value="25">25 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions Bar -->
            @if(count($selectedUsers) > 0)
                <div class="mt-6 p-4 bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl border border-indigo-200/50">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
                        <div class="flex items-center space-x-3">
                            <div class="p-2 bg-indigo-500 rounded-lg">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    {{ count($selectedUsers) }} user{{ count($selectedUsers) > 1 ? 's' : '' }} selected
                                </p>
                                <p class="text-xs text-gray-500">Choose an action to apply to selected users</p>
                            </div>
                        </div>

                        <div class="relative">
                            <select
                                wire:model.live="bulkAction"
                                class="filter-dropdown appearance-none pl-4 pr-10 py-2.5 border border-indigo-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200 text-sm"
                            >
                                <option value="">Choose Action...</option>
                                <option value="delete">🗑️ Delete Selected</option>
                                <option value="verify">✅ Verify Email</option>
                                <option value="unverify">❌ Unverify Email</option>
                                <optgroup label="Change Role">
                                    <option value="role_admin">👑 Set as Admin</option>
                                    <option value="role_salesperson">💼 Set as Salesperson</option>
                                    <option value="role_shop_owner">🏪 Set as Shop Owner</option>
                                    <option value="role_customer">👤 Set as Customer</option>
                                </optgroup>
                            </select>

                            <!-- Enhanced loading indicator for bulk actions -->
                            <div wire:loading wire:target="bulkAction"
                                 class="absolute inset-0 bg-gradient-to-r from-violet-50/90 to-purple-50/90 backdrop-blur-sm flex items-center justify-center rounded-lg border border-violet-200/50"
                                 x-data="{}"
                                 x-show="true"
                                 x-transition:enter="ease-out duration-200"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100">

                                <div class="flex items-center space-x-2">
                                    <div class="flex space-x-1">
                                        <div class="w-1.5 h-1.5 bg-violet-400 rounded-full animate-bounce" style="animation-delay: 0s;"></div>
                                        <div class="w-1.5 h-1.5 bg-purple-400 rounded-full animate-bounce" style="animation-delay: 0.1s;"></div>
                                        <div class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce" style="animation-delay: 0.2s;"></div>
                                    </div>
                                    <span class="text-xs font-medium text-violet-600">Processing...</span>
                                </div>
                            </div>

                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Enhanced Table -->
        <div class="bg-white/70 backdrop-blur-lg rounded-2xl shadow-2xl overflow-hidden border border-white/20">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100 backdrop-blur-sm">
                    <tr>
                        <!-- Select All Checkbox -->
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <input
                                type="checkbox"
                                wire:model.live="selectAll"
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            />
                        </th>

                        <!-- Name Column -->
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                            wire:click="sortBy('name')">
                            <div class="flex items-center space-x-1">
                                <span>Name</span>
                                @if($sortField === 'name')
                                    @if($sortDirection === 'ASC')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M5 15l7-7 7 7"/>
                                        </svg>
                                    @else
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    @endif
                                @else
                                    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor"
                                         viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                    </svg>
                                @endif
                            </div>
                        </th>

                        <!-- Email Column -->
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                            wire:click="sortBy('email')">
                            <div class="flex items-center space-x-1">
                                <span>Email</span>
                                @if($sortField === 'email')
                                    @if($sortDirection === 'ASC')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M5 15l7-7 7 7"/>
                                        </svg>
                                    @else
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    @endif
                                @else
                                    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor"
                                         viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                    </svg>
                                @endif
                            </div>
                        </th>

                        <!-- Role Column -->
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Role
                        </th>

                        <!-- Status Column -->
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>

                        <!-- Shop Assignments Column -->
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Shop Assignments
                        </th>

                        <!-- Created At Column -->
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                            wire:click="sortBy('created_at')">
                            <div class="flex items-center space-x-1">
                                <span>Created</span>
                                @if($sortField === 'created_at')
                                    @if($sortDirection === 'ASC')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M5 15l7-7 7 7"/>
                                        </svg>
                                    @else
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    @endif
                                @else
                                    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor"
                                         viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                    </svg>
                                @endif
                            </div>
                        </th>

                        <!-- Actions Column -->
                        <th scope="col"
                            class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($users as $user)
                                                    <tr class="table-hover hover:shadow-md transition-all duration-300 border-b border-gray-50/50">
                            <!-- Select Checkbox -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input
                                    type="checkbox"
                                    wire:model.live="selectedUsers"
                                    value="{{ $user->id }}"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                />
                            </td>

                            <!-- Name -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <img class="h-10 w-10 rounded-full" src="{{ $user->profile_photo_url }}"
                                             alt="{{ $user->name }}">
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $user->name }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Email -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $user->email }}</div>
                            </td>

                            <!-- Role -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($user->roles->isNotEmpty())
                                    @foreach($user->roles as $role)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                @if($role->name === 'admin') bg-red-100 text-red-800
                                                @elseif($role->name === 'salesperson') bg-blue-100 text-blue-800
                                                @elseif($role->name === 'shop_owner') bg-green-100 text-green-800
                                                @elseif($role->name === 'customer') bg-gray-100 text-gray-800
                                                @else bg-gray-100 text-gray-800
                                                @endif
                                            ">
                                                {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                            </span>
                                    @endforeach
                                @else
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            No Role
                                        </span>
                                @endif
                            </td>

                                                        <!-- Status -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($user->email_verified_at)
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                  d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                        Verified
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                  d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                        Unverified
                                    </span>
                                @endif
                            </td>

                            <!-- Shop Assignments -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="space-y-1">
                                    @if($user->hasRole('shop_owner') && $user->ownedShop)
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                            </svg>
                                            <span class="text-sm text-gray-900 font-medium">Owner: {{ $user->ownedShop->name }}</span>
                                        </div>
                                    @endif
                                    
                                    @if($user->hasRole('salesperson') && $user->assignedShops->isNotEmpty())
                                        <div class="space-y-1" x-data="{ expanded: false }">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 text-blue-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                                </svg>
                                                <span class="text-sm text-gray-700 font-medium">Assigned to:</span>
                                            </div>
                                            
                                            @if($user->assignedShops->count() == 1)
                                                <!-- Show all shops if only 1 -->
                                                @foreach($user->assignedShops as $shop)
                                                    <div class="ml-6">
                                                        <span class="text-sm text-gray-600">{{ $shop->name }}</span>
                                                    </div>
                                                @endforeach
                                            @else
                                                <!-- Show first shop and expandable list for 2 or more -->
                                                @foreach($user->assignedShops->take(1) as $shop)
                                                    <div class="ml-6">
                                                        <span class="text-sm text-gray-600">{{ $shop->name }}</span>
                                                    </div>
                                                @endforeach
                                                
                                                <!-- Expandable section -->
                                                <div x-show="!expanded" class="ml-6">
                                                    <button 
                                                        @click="expanded = true"
                                                        class="text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors duration-200"
                                                    >
                                                        +{{ $user->assignedShops->count() - 1 }} more shops
                                                    </button>
                                                </div>
                                                
                                                <div 
                                                    x-show="expanded" 
                                                    x-transition:enter="transition ease-out duration-200"
                                                    x-transition:enter-start="opacity-0 transform -translate-y-2"
                                                    x-transition:enter-end="opacity-100 transform translate-y-0"
                                                    x-transition:leave="transition ease-in duration-150"
                                                    x-transition:leave-start="opacity-100 transform translate-y-0"
                                                    x-transition:leave-end="opacity-0 transform -translate-y-2"
                                                    class="space-y-1"
                                                >
                                                    @foreach($user->assignedShops->slice(1) as $shop)
                                                        <div class="ml-6">
                                                            <span class="text-sm text-gray-600">{{ $shop->name }}</span>
                                                        </div>
                                                    @endforeach
                                                    <div class="ml-6">
                                                        <button 
                                                            @click="expanded = false"
                                                            class="text-sm text-gray-500 hover:text-gray-700 font-medium transition-colors duration-200"
                                                        >
                                                            Show less
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                    
                                    @if((!$user->hasRole('shop_owner') || !$user->ownedShop) && (!$user->hasRole('salesperson') || $user->assignedShops->isEmpty()))
                                        <span class="text-sm text-gray-400 italic">No shop assignments</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Created At -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div>{{ $user->created_at->format('M j, Y') }}</div>
                                <div class="text-xs text-gray-400">{{ $user->created_at->format('H:i') }}</div>
                            </td>

                            <!-- Enhanced Actions -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <!-- Edit Button -->
                                    <button
                                        wire:click="openEditModal({{ $user->id }})"
                                        class="inline-flex items-center p-2 text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 hover:text-blue-700 transition-all duration-200 transform hover:scale-105"
                                        title="Edit User"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>

                                    @if($user->id !== auth()->id())
                                        <!-- Delete Button -->
                                        <button
                                            wire:click="openDeleteModal({{ $user->id }})"
                                            class="inline-flex items-center p-2 text-red-600 bg-red-50 rounded-lg hover:bg-red-100 hover:text-red-700 transition-all duration-200 transform hover:scale-105"
                                            title="Delete User"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    @else
                                        <!-- Self Account Indicator -->
                                        <span class="inline-flex items-center p-2 text-gray-400 bg-gray-50 rounded-lg" title="Cannot delete your own account">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"/>
                                            </svg>
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor"
                                         viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                    </svg>
                                    <h3 class="text-lg font-medium text-gray-900 mb-1">No users found</h3>
                                    <p class="text-gray-500">Try adjusting your search or filter criteria.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        @if($users->hasPages())
            <div class="mt-6">
                {{ $users->links() }}
            </div>
        @endif

        <!-- Enhanced Loading Indicators -->

        <!-- Main Loading Indicator for CRUD Operations (Exclude modal close operations) -->
        <div wire:loading.delay
             wire:target="createUser,updateUser,deleteUser,performBulkAction,sortBy,updatedSearch,updatedRoleFilter,updatedVerificationFilter,updatedPerPage"
             class="fixed inset-0 bg-gradient-to-br from-indigo-900/20 via-purple-900/20 to-pink-900/20 backdrop-blur-sm flex items-center justify-center z-50"
             x-data="{}"
             x-show="true"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">

            <div class="relative">
                <!-- Glassmorphism container with floating animation -->
                <div class="bg-white/10 backdrop-blur-xl rounded-3xl p-8 shadow-2xl border border-white/20 animate-pulse">
                    <div class="flex flex-col items-center space-y-6">

                        <!-- Multi-layered spinner animation -->
                        <div class="relative w-16 h-16">
                            <!-- Outer ring -->
                            <div class="absolute inset-0 border-4 border-blue-200/30 rounded-full animate-spin"></div>
                            <!-- Middle ring -->
                            <div class="absolute inset-2 border-4 border-l-blue-500 border-t-purple-500 border-r-pink-500 border-b-transparent rounded-full animate-spin" style="animation-duration: 1.5s;"></div>
                            <!-- Inner ring -->
                            <div class="absolute inset-4 border-2 border-white/50 rounded-full animate-spin" style="animation-duration: 0.8s; animation-direction: reverse;"></div>
                            <!-- Center dot -->
                            <div class="absolute inset-6 bg-gradient-to-r from-blue-400 to-purple-500 rounded-full animate-ping"></div>
                        </div>

                        <!-- Loading text with gradient -->
                        <div class="text-center">
                            <div class="text-lg font-semibold bg-gradient-to-r from-blue-600 via-purple-600 to-pink-600 bg-clip-text text-transparent">
                                Processing...
                            </div>
                            <div class="text-sm text-gray-600 mt-1 opacity-80">
                                Please wait while we handle your request
                            </div>
                        </div>

                        <!-- Floating particles animation -->
                        <div class="absolute -top-2 -left-2 w-2 h-2 bg-blue-400 rounded-full animate-ping" style="animation-delay: 0.5s;"></div>
                        <div class="absolute -top-1 -right-3 w-1 h-1 bg-purple-400 rounded-full animate-ping" style="animation-delay: 1s;"></div>
                        <div class="absolute -bottom-2 -left-3 w-1.5 h-1.5 bg-pink-400 rounded-full animate-ping" style="animation-delay: 1.5s;"></div>
                        <div class="absolute -bottom-1 -right-2 w-2 h-2 bg-indigo-400 rounded-full animate-ping" style="animation-delay: 2s;"></div>
                    </div>
                </div>

                <!-- Glow effect -->
                <div class="absolute inset-0 bg-gradient-to-r from-blue-400/20 via-purple-400/20 to-pink-400/20 rounded-3xl blur-xl animate-pulse"></div>
            </div>
        </div>

        <!-- Specific Loading for Creating User -->
        <div wire:loading.delay
             wire:target="createUser"
             class="fixed inset-0 bg-gradient-to-br from-green-900/20 via-emerald-900/20 to-teal-900/20 backdrop-blur-sm flex items-center justify-center z-50"
             x-data="{}"
             x-show="true"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">

            <div class="relative">
                <div class="bg-white/10 backdrop-blur-xl rounded-3xl p-8 shadow-2xl border border-white/20">
                    <div class="flex flex-col items-center space-y-6">
                        <!-- User creation icon with animation -->
                        <div class="relative">
                            <div class="w-16 h-16 bg-gradient-to-r from-green-400 to-emerald-500 rounded-full flex items-center justify-center animate-bounce">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                </svg>
                            </div>
                            <div class="absolute inset-0 bg-green-400 rounded-full animate-ping opacity-30"></div>
                        </div>

                        <div class="text-center">
                            <div class="text-lg font-semibold bg-gradient-to-r from-green-600 to-emerald-600 bg-clip-text text-transparent">
                                Creating User...
                            </div>
                            <div class="text-sm text-gray-600 mt-1 opacity-80">
                                Setting up the new account
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Specific Loading for Updating User -->
        <div wire:loading.delay
             wire:target="updateUser"
             class="fixed inset-0 bg-gradient-to-br from-amber-900/20 via-orange-900/20 to-red-900/20 backdrop-blur-sm flex items-center justify-center z-50"
             x-data="{}"
             x-show="true"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">

            <div class="relative">
                <div class="bg-white/10 backdrop-blur-xl rounded-3xl p-8 shadow-2xl border border-white/20">
                    <div class="flex flex-col items-center space-y-6">
                        <!-- Update icon with rotation animation -->
                        <div class="relative">
                            <div class="w-16 h-16 bg-gradient-to-r from-amber-400 to-orange-500 rounded-full flex items-center justify-center">
                                <svg class="w-8 h-8 text-white animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                            </div>
                            <div class="absolute inset-0 bg-amber-400 rounded-full animate-ping opacity-30"></div>
                        </div>

                        <div class="text-center">
                            <div class="text-lg font-semibold bg-gradient-to-r from-amber-600 to-orange-600 bg-clip-text text-transparent">
                                Updating User...
                            </div>
                            <div class="text-sm text-gray-600 mt-1 opacity-80">
                                Saving changes to the account
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Specific Loading for Deleting User -->
        <div wire:loading.delay
             wire:target="deleteUser"
             class="fixed inset-0 bg-gradient-to-br from-red-900/20 via-rose-900/20 to-pink-900/20 backdrop-blur-sm flex items-center justify-center z-50"
             x-data="{}"
             x-show="true"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">

            <div class="relative">
                <div class="bg-white/10 backdrop-blur-xl rounded-3xl p-8 shadow-2xl border border-white/20">
                    <div class="flex flex-col items-center space-y-6">
                        <!-- Delete icon with pulsing animation -->
                        <div class="relative">
                            <div class="w-16 h-16 bg-gradient-to-r from-red-400 to-rose-500 rounded-full flex items-center justify-center animate-pulse">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </div>
                            <div class="absolute inset-0 bg-red-400 rounded-full animate-ping opacity-30"></div>
                        </div>

                        <div class="text-center">
                            <div class="text-lg font-semibold bg-gradient-to-r from-red-600 to-rose-600 bg-clip-text text-transparent">
                                Deleting User...
                            </div>
                            <div class="text-sm text-gray-600 mt-1 opacity-80">
                                Removing account from system
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Actions Loading -->
        <div wire:loading.delay
             wire:target="performBulkAction"
             class="fixed inset-0 bg-gradient-to-br from-violet-900/20 via-purple-900/20 to-indigo-900/20 backdrop-blur-sm flex items-center justify-center z-50"
             x-data="{}"
             x-show="true"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">

            <div class="relative">
                <div class="bg-white/10 backdrop-blur-xl rounded-3xl p-8 shadow-2xl border border-white/20">
                    <div class="flex flex-col items-center space-y-6">
                        <!-- Bulk action icon with multiple dots -->
                        <div class="relative flex space-x-1">
                            <div class="w-3 h-3 bg-violet-400 rounded-full animate-bounce" style="animation-delay: 0s;"></div>
                            <div class="w-3 h-3 bg-purple-400 rounded-full animate-bounce" style="animation-delay: 0.2s;"></div>
                            <div class="w-3 h-3 bg-indigo-400 rounded-full animate-bounce" style="animation-delay: 0.4s;"></div>
                            <div class="w-3 h-3 bg-blue-400 rounded-full animate-bounce" style="animation-delay: 0.6s;"></div>
                        </div>

                        <div class="text-center">
                            <div class="text-lg font-semibold bg-gradient-to-r from-violet-600 to-indigo-600 bg-clip-text text-transparent">
                                Processing Bulk Action...
                            </div>
                            <div class="text-sm text-gray-600 mt-1 opacity-80">
                                Applying changes to selected users
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search/Filter Loading (Subtle) -->
        <div wire:loading
             wire:target="updatedSearch,updatedRoleFilter,updatedVerificationFilter,updatedPerPage"
             class="fixed top-4 right-4 z-50"
             x-data="{}"
             x-show="true"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-x-4"
             x-transition:enter-end="opacity-100 translate-x-0">

            <div class="bg-white/90 backdrop-blur-sm rounded-full px-4 py-2 shadow-lg border border-gray-200/50 flex items-center space-x-2">
                <div class="flex space-x-1">
                    <div class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-pulse" style="animation-delay: 0s;"></div>
                    <div class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-pulse" style="animation-delay: 0.2s;"></div>
                    <div class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-pulse" style="animation-delay: 0.4s;"></div>
                </div>
                <span class="text-sm text-gray-600">Filtering...</span>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    @if($addModalOpen)
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 overflow-y-auto h-full w-full z-50"
             x-data="{}"
             x-show="true"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
        >
            <div class="relative top-20 mx-auto p-5 border w-full max-w-lg bg-white rounded-lg shadow-lg">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Add New User</h3>
                    <button wire:click="closeModals" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit="createUser">
                    <div class="space-y-4">
                        <!-- Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                            <input
                                type="text"
                                id="name"
                                wire:model="name"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                required
                            />
                            @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input
                                type="email"
                                id="email"
                                wire:model="email"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                required
                            />
                            @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                            <input
                                type="password"
                                id="password"
                                wire:model="password"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                required
                            />
                            @error('password') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm
                                Password</label>
                            <input
                                type="password"
                                id="password_confirmation"
                                wire:model="password_confirmation"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                required
                            />
                            @error('password_confirmation') <span
                                class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Role -->
                        <div>
                            <label for="selectedRole" class="block text-sm font-medium text-gray-700">Role</label>
                            <select
                                id="selectedRole"
                                wire:model="selectedRole"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                required
                            >
                                <option value="">Select a role</option>
                                @foreach($roles as $role)
                                    <option
                                        value="{{ $role->name }}">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                                @endforeach
                            </select>
                            @error('selectedRole') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Email Verified -->
                        <div class="flex items-center">
                            <input
                                type="checkbox"
                                id="emailVerified"
                                wire:model="emailVerified"
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            />
                            <label for="emailVerified" class="ml-2 block text-sm text-gray-900">
                                Mark email as verified
                            </label>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button
                            type="button"
                            wire:click="closeModals"
                            class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            Cancel
                        </button>
                                            <button
                        type="submit"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-75 disabled:cursor-not-allowed transition-all duration-200"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="createUser" class="flex items-center">
                            Create User
                        </span>
                        <span wire:loading wire:target="createUser" class="flex items-center space-x-2">
                            <div class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                            <span>Creating...</span>
                        </span>
                    </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Edit User Modal -->
    @if($editModalOpen)
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 overflow-y-auto h-full w-full z-50"
             x-data="{}"
             x-show="true"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
        >
            <div class="relative top-20 mx-auto p-5 border w-full max-w-lg bg-white rounded-lg shadow-lg">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Edit User</h3>
                    <button wire:click="closeModals" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit="updateUser">
                    <div class="space-y-4">
                        <!-- Name -->
                        <div>
                            <label for="edit_name" class="block text-sm font-medium text-gray-700">Name</label>
                            <input
                                type="text"
                                id="edit_name"
                                wire:model="name"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                required
                            />
                            @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="edit_email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input
                                type="email"
                                id="edit_email"
                                wire:model="email"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                required
                            />
                            @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="edit_password" class="block text-sm font-medium text-gray-700">
                                Password
                                <span class="text-sm text-gray-500">(leave blank to keep current)</span>
                            </label>
                            <input
                                type="password"
                                id="edit_password"
                                wire:model="password"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            />
                            @error('password') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label for="edit_password_confirmation" class="block text-sm font-medium text-gray-700">Confirm
                                Password</label>
                            <input
                                type="password"
                                id="edit_password_confirmation"
                                wire:model="password_confirmation"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            />
                            @error('password_confirmation') <span
                                class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Role -->
                        <div>
                            <label for="edit_selectedRole" class="block text-sm font-medium text-gray-700">Role</label>
                            <select
                                id="edit_selectedRole"
                                wire:model="selectedRole"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                required
                            >
                                <option value="">Select a role</option>
                                @foreach($roles as $role)
                                    <option
                                        value="{{ $role->name }}">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                                @endforeach
                            </select>
                            @error('selectedRole') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Email Verified -->
                        <div class="flex items-center">
                            <input
                                type="checkbox"
                                id="edit_emailVerified"
                                wire:model="emailVerified"
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            />
                            <label for="edit_emailVerified" class="ml-2 block text-sm text-gray-900">
                                Mark email as verified
                            </label>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button
                            type="button"
                            wire:click="closeModals"
                            class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            Cancel
                        </button>
                                            <button
                        type="submit"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-75 disabled:cursor-not-allowed transition-all duration-200"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="updateUser" class="flex items-center">
                            Update User
                        </span>
                        <span wire:loading wire:target="updateUser" class="flex items-center space-x-2">
                            <div class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                            <span>Updating...</span>
                        </span>
                    </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($deleteModalOpen)
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 overflow-y-auto h-full w-full z-50"
             x-data="{}"
             x-show="true"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
        >
            <div class="relative top-20 mx-auto p-5 border w-full max-w-md bg-white rounded-lg shadow-lg">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Confirm Delete</h3>
                    <button wire:click="closeModals" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="mb-4">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                    </div>
                    <p class="text-center text-gray-700">
                        Are you sure you want to delete this user? This action cannot be undone.
                    </p>
                </div>

                <div class="flex justify-end space-x-3">
                    <button
                        wire:click="closeModals"
                        class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    >
                        Cancel
                    </button>
                                    <button
                    wire:click="deleteUser"
                    class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-75 disabled:cursor-not-allowed transition-all duration-200"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="deleteUser" class="flex items-center">
                        Delete User
                    </span>
                    <span wire:loading wire:target="deleteUser" class="flex items-center space-x-2">
                        <div class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                        <span>Deleting...</span>
                    </span>
                </button>
                </div>
            </div>
        </div>
    @endif
</div>
