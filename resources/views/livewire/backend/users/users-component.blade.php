<div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Flash Messages -->
        @if (session()->has('message'))
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative"
                 role="alert">
                <span class="block sm:inline">{{ session('message') }}</span>
                <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                    <svg class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg"
                         viewBox="0 0 20 20"><title>Close</title><path
                            d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
                </span>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
                <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                    <svg class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg"
                         viewBox="0 0 20 20"><title>Close</title><path
                            d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
                </span>
            </div>
        @endif

        <!-- Header -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div class="flex-1 min-w-0">
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                        Users Management
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Manage system users and their roles
                    </p>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-4">
                    <button
                        wire:click="openAddModal"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Add User
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-lg mb-6 p-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
                <!-- Search -->
                <div class="relative">
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        placeholder="Search users..."
                        class="w-full px-10 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    @if($search)
                        <button
                            wire:click="$set('search', '')"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    @endif
                </div>

                <!-- Role Filter -->
                <select wire:model.live="roleFilter"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Roles</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                    @endforeach
                </select>

                <!-- Verification Filter -->
                <select wire:model.live="verificationFilter"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Verification Status</option>
                    <option value="verified">Verified</option>
                    <option value="unverified">Unverified</option>
                </select>

                <!-- Per Page -->
                <select wire:model.live="perPage"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="10">10 per page</option>
                    <option value="25">25 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
                </select>

                <!-- Bulk Actions -->
                <div class="relative">
                    <select
                        wire:model.live="bulkAction"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        @disabled(count($selectedUsers) === 0)
                    >
                        <option value="">Bulk Actions ({{ count($selectedUsers) }})</option>
                        @if(count($selectedUsers) > 0)
                            <option value="delete">Delete Selected</option>
                            <option value="verify">Verify Email</option>
                            <option value="unverify">Unverify Email</option>
                            <optgroup label="Change Role">
                                <option value="role_admin">Set as Admin</option>
                                <option value="role_salesperson">Set as Salesperson</option>
                                <option value="role_shop_owner">Set as Shop Owner</option>
                                <option value="role_customer">Set as Customer</option>
                            </optgroup>
                        @endif
                    </select>

                    <!-- Loading indicator for bulk actions -->
                    <div wire:loading wire:target="bulkAction"
                         class="absolute inset-0 bg-white bg-opacity-50 flex items-center justify-center rounded-md">
                        <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                             viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
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
                        <tr class="hover:bg-gray-50 transition-colors duration-200">
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

                            <!-- Created At -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div>{{ $user->created_at->format('M j, Y') }}</div>
                                <div class="text-xs text-gray-400">{{ $user->created_at->format('H:i') }}</div>
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <button
                                        wire:click="openEditModal({{ $user->id }})"
                                        class="text-blue-600 hover:text-blue-900 transition-colors duration-200"
                                        title="Edit User"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>

                                    @if($user->id !== auth()->id())
                                        <button
                                            wire:click="openDeleteModal({{ $user->id }})"
                                            class="text-red-600 hover:text-red-900 transition-colors duration-200"
                                            title="Delete User"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    @else
                                        <span class="text-gray-400" title="Cannot delete your own account">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                     viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2"
                                                          d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"/>
                                                </svg>
                                            </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
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

        <!-- Loading Indicator -->
        <div wire:loading.delay class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 flex items-center space-x-3">
                <svg class="animate-spin h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-gray-700">Loading...</span>
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
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="createUser">Create User</span>
                            <span wire:loading wire:target="createUser">Creating...</span>
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
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="updateUser">Update User</span>
                            <span wire:loading wire:target="updateUser">Updating...</span>
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
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="deleteUser">Delete User</span>
                        <span wire:loading wire:target="deleteUser">Deleting...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
