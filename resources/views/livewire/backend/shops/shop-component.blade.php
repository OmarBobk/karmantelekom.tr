<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
            <div class="p-6">
                <!-- Header Section -->
                <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center space-y-4 sm:space-y-0">
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Shops Management</h2>
                    <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
                        @can('create', App\Models\Shop::class)
                            <button
                                type="button"
                                wire:click="create"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                            >
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                New Shop
                            </button>
                        @endcan
                        <div class="relative w-full sm:w-96">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <input type="text"
                                   wire:model.live.debounce.300ms="search"
                                   placeholder="Search shops by name, phone or address..."
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-300 transition duration-150 ease-in-out">
                        </div>
                        <select wire:model.live="perPage" class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-indigo-500">
                            <option value="10">10 per page</option>
                            <option value="25">25 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                    </div>
                </div>

                <!-- Flash Messages -->
                @if (session()->has('success'))
                    <div class="mb-4 p-4 rounded-lg bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session()->has('error'))
                    <div class="mb-4 p-4 rounded-lg bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-200">
                        {{ session('error') }}
                    </div>
                @endif

                <!-- Create Shop Modal -->
                <x-modal wire:model="showCreateModal">
                    <div class="p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            Create New Shop
                        </h2>

                        <form wire:submit="save" class="mt-6 space-y-6">
                            <div>
                                <x-label for="name" value="Shop Name" />
                                <x-input wire:model="name" id="name" type="text" class="mt-1 block w-full" required />
                                <x-input-error for="name" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="phone" value="Phone Number" />
                                <x-input wire:model="phone" id="phone" type="text" class="mt-1 block w-full" required />
                                <x-input-error for="phone" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="address" value="Address" />
                                <x-textarea wire:model="address" id="address" class="mt-1 block w-full" rows="3" required />
                                <x-input-error for="address" class="mt-2" />
                            </div>

                            <div>
                                <div class="flex items-center justify-between">
                                    <x-label value="Links" />
                                    <button
                                        type="button"
                                        wire:click="addLink"
                                        class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-indigo-900 dark:text-indigo-200 dark:hover:bg-indigo-800 transition duration-150 ease-in-out"
                                    >
                                        <svg class="h-3.5 w-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Add Link
                                    </button>
                                </div>

                                <!-- New Link Form -->
                                <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <x-input
                                            wire:model="newLink.type"
                                            type="text"
                                            placeholder="Link Type (e.g., Facebook, Instagram)"
                                            class="mt-1 block w-full"
                                        />
                                        <x-input-error for="newLink.type" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input
                                            wire:model="newLink.url"
                                            type="url"
                                            placeholder="URL"
                                            class="mt-1 block w-full"
                                        />
                                        <x-input-error for="newLink.url" class="mt-2" />
                                    </div>
                                </div>

                                <!-- Added Links -->
                                @if(count($links) > 0)
                                    <div class="mt-4 space-y-2">
                                        @foreach($links as $type => $url)
                                            <div class="flex items-center space-x-2">
                                                <div class="flex-1 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                    <div>
                                                        <x-input
                                                            type="text"
                                                            value="{{ ucwords(str_replace('_', ' ', $type)) }}"
                                                            class="block w-full bg-gray-50 dark:bg-gray-700"
                                                            readonly
                                                        />
                                                    </div>
                                                    <div>
                                                        <x-input
                                                            type="url"
                                                            value="{{ $url }}"
                                                            class="block w-full bg-gray-50 dark:bg-gray-700"
                                                            readonly
                                                        />
                                                    </div>
                                                </div>
                                                <button
                                                    type="button"
                                                    wire:click="removeLink('{{ $type }}')"
                                                    class="inline-flex items-center p-1.5 border border-transparent text-xs font-medium rounded text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800 transition duration-150 ease-in-out"
                                                >
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div>
                                <x-label for="user_id" value="Assign Salesperson" />
                                <x-select wire:model="user_id" id="user_id" class="mt-1 block w-full">
                                    <option value="">Select a salesperson</option>
                                    @foreach($salespeople as $salesperson)
                                        <option value="{{ $salesperson->id }}">{{ $salesperson->name }}</option>
                                    @endforeach
                                </x-select>
                                <x-input-error for="user_id" class="mt-2" />
                            </div>

                            <div class="mt-6 flex justify-end">
                                <x-secondary-button type="button" wire:click="$set('showCreateModal', false)" class="mr-3">
                                    Cancel
                                </x-secondary-button>
                                <x-button>
                                    Create Shop
                                </x-button>
                            </div>
                        </form>
                    </div>
                </x-modal>

                <!-- Edit Shop Modal -->
                <x-modal wire:model="showEditModal">
                    <div class="p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            Edit Shop
                        </h2>

                        <form wire:submit="update" class="mt-6 space-y-6">
                            <div>
                                <x-label for="name" value="Shop Name" />
                                <x-input wire:model="name" id="name" type="text" class="mt-1 block w-full" required />
                                <x-input-error for="name" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="phone" value="Phone Number" />
                                <x-input wire:model="phone" id="phone" type="text" class="mt-1 block w-full" required />
                                <x-input-error for="phone" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="address" value="Address" />
                                <x-textarea wire:model="address" id="address" class="mt-1 block w-full" rows="3" required />
                                <x-input-error for="address" class="mt-2" />
                            </div>

                            <div>
                                <div class="flex items-center justify-between">
                                    <x-label value="Links" />
                                    <button
                                        type="button"
                                        wire:click="addLink"
                                        class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-indigo-900 dark:text-indigo-200 dark:hover:bg-indigo-800 transition duration-150 ease-in-out"
                                    >
                                        <svg class="h-3.5 w-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Add Link
                                    </button>
                                </div>

                                <!-- New Link Form -->
                                <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <x-input
                                            wire:model="newLink.type"
                                            type="text"
                                            placeholder="Link Type (e.g., Facebook, Instagram)"
                                            class="mt-1 block w-full"
                                        />
                                        <x-input-error for="newLink.type" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input
                                            wire:model="newLink.url"
                                            type="url"
                                            placeholder="URL"
                                            class="mt-1 block w-full"
                                        />
                                        <x-input-error for="newLink.url" class="mt-2" />
                                    </div>
                                </div>

                                <!-- Added Links -->
                                @if(count($links) > 0)
                                    <div class="mt-4 space-y-2">
                                        @foreach($links as $type => $url)
                                            <div class="flex items-center space-x-2">
                                                <div class="flex-1 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                    <div>
                                                        <x-input
                                                            type="text"
                                                            value="{{ ucwords(str_replace('_', ' ', $type)) }}"
                                                            class="block w-full bg-gray-50 dark:bg-gray-700"
                                                            readonly
                                                        />
                                                    </div>
                                                    <div>
                                                        <x-input
                                                            type="url"
                                                            value="{{ $url }}"
                                                            class="block w-full bg-gray-50 dark:bg-gray-700"
                                                            readonly
                                                        />
                                                    </div>
                                                </div>
                                                <button
                                                    type="button"
                                                    wire:click="removeLink('{{ $type }}')"
                                                    class="inline-flex items-center p-1.5 border border-transparent text-xs font-medium rounded text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800 transition duration-150 ease-in-out"
                                                >
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div>
                                <x-label for="user_id" value="Assign Salesperson" />
                                <x-select wire:model="user_id" id="user_id" class="mt-1 block w-full">
                                    <option value="">Select a salesperson</option>
                                    @foreach($salespeople as $salesperson)
                                        <option value="{{ $salesperson->id }}">{{ $salesperson->name }}</option>
                                    @endforeach
                                </x-select>
                                <x-input-error for="user_id" class="mt-2" />
                            </div>

                            <div class="mt-6 flex justify-end">
                                <x-secondary-button type="button" wire:click="$set('showEditModal', false)" class="mr-3">
                                    Cancel
                                </x-secondary-button>
                                <x-button>
                                    Update Shop
                                </x-button>
                            </div>
                        </form>
                    </div>
                </x-modal>

                <!-- Shops Table -->
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">#</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer" wire:click="sortBy('name')">
                                    <div class="flex items-center space-x-1">
                                        <span>Name</span>
                                        @if($sortField === 'name')
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" transform="{{ $sortDirection === 'asc' ? 'rotate(180 12 12)' : '' }}"/>
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Phone</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Address</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Links</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Salesperson</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer" wire:click="sortBy('monthly_orders_count')">
                                    <div class="flex items-center space-x-1">
                                        <span>Orders This Month</span>
                                        @if($sortField === 'monthly_orders_count')
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" transform="{{ $sortDirection === 'asc' ? 'rotate(180 12 12)' : '' }}"/>
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($shops as $shop)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-150 ease-in-out">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $loop->iteration + ($shops->currentPage() - 1) * $shops->perPage() }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $shop->name }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            @if($shop->phone)
                                                {{ $shop->phone }}
                                            @else
                                                <span class="text-gray-400 dark:text-gray-500 italic">No phone number</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div x-data="{ expanded: false }" class="text-sm text-gray-500 dark:text-gray-400 max-w-[200px]">
                                            @if($shop->address)
                                                <div
                                                    @click="expanded = !expanded"
                                                    :class="{ 'cursor-pointer hover:text-gray-700 dark:hover:text-gray-300': true }"
                                                    class="transition-colors duration-150 ease-in-out"
                                                >
                                                    <span
                                                        x-show="!expanded"
                                                        class="truncate block"
                                                    >
                                                        {{ $shop->address }}
                                                    </span>
                                                    <span
                                                        x-show="expanded"
                                                        class="block whitespace-normal break-words"
                                                    >
                                                        {{ $shop->address }}
                                                    </span>
                                                </div>
                                            @else
                                                <span class="text-gray-400 dark:text-gray-500 italic">No address</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div x-data="{ showAll: false }" class="text-sm">
                                            @if(count($shop->links) > 0)
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach($shop->links as $title => $value)
                                                        <div x-show="showAll || {{ $loop->index }} < 2">
                                                            <a href="{{ $value }}"
                                                               target="_blank"
                                                               class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 transition-colors duration-150 ease-in-out"
                                                            >
                                                                @switch(strtolower($title))
                                                                    @case('facebook')
                                                                        <svg class="h-3.5 w-3.5 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                                                            <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/>
                                                                        </svg>
                                                                        @break
                                                                    @case('instagram')
                                                                        <svg class="h-3.5 w-3.5 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                                                            <path d="M12 2c2.717 0 3.056.01 4.122.06 1.065.05 1.79.217 2.428.465.66.254 1.216.598 1.772 1.153.509.5.902 1.105 1.153 1.772.247.637.415 1.363.465 2.428.047 1.066.06 1.405.06 4.122 0 2.717-.01 3.056-.06 4.122-.05 1.065-.218 1.79-.465 2.428a4.883 4.883 0 01-1.153 1.772c-.5.509-1.105.902-1.772 1.153-.637.247-1.363.415-2.428.465-1.066.047-1.405.06-4.122.06-2.717 0-3.056-.01-4.122-.06-1.065-.05-1.79-.218-2.428-.465a4.89 4.89 0 01-1.772-1.153 4.904 4.904 0 01-1.153-1.772c-.248-.637-.415-1.363-.465-2.428C2.013 15.056 2 14.717 2 12c0-2.717.01-3.056.06-4.122.05-1.066.217-1.79.465-2.428a4.88 4.88 0 011.153-1.772A4.897 4.897 0 015.45 2.525c.638-.248 1.362-.415 2.428-.465C8.944 2.013 9.283 2 12 2zm0 5a5 5 0 100 10 5 5 0 000-10zm6.5-.25a1.25 1.25 0 10-2.5 0 1.25 1.25 0 002.5 0zM12 9a3 3 0 110 6 3 3 0 010-6z"/>
                                                                        </svg>
                                                                        @break
                                                                    @case('twitter')
                                                                        <svg class="h-3.5 w-3.5 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                                                            <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                                                                        </svg>
                                                                        @break
                                                                    @case('website')
                                                                        <svg class="h-3.5 w-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                                                                        </svg>
                                                                        @break
                                                                    @default
                                                                        <svg class="h-3.5 w-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                                                        </svg>
                                                                @endswitch
                                                                {{ ucfirst(str_replace('_', ' ', $title)) }}
                                                            </a>
                                                        </div>
                                                    @endforeach
                                                </div>

                                                @if(count($shop->links) > 2)
                                                    <button
                                                        x-show="!showAll"
                                                        @click="showAll = true"
                                                        class="mt-2 text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 transition-colors duration-150 ease-in-out"
                                                    >
                                                        +{{ count($shop->links) - 2 }} more
                                                    </button>
                                                    <button
                                                        x-show="showAll"
                                                        @click="showAll = false"
                                                        class="mt-2 text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 transition-colors duration-150 ease-in-out"
                                                    >
                                                        Show less
                                                    </button>
                                                @endif
                                            @else
                                                <span class="text-gray-400 dark:text-gray-500 italic">No links</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            @if($shop->salesperson->id !== 0)
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-8 w-8">
                                                        <img class="h-8 w-8 rounded-full" src="{{ $shop->salesperson->profile_photo_url }}" alt="{{ $shop->salesperson->name }}">
                                                    </div>
                                                    <div class="ml-3">
                                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                            {{ $shop->salesperson->name }}
                                                        </p>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                    Unassigned
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($shop->monthly_orders_count > 0)
                                            <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                {{ $shop->monthly_orders_count }}
                                            </span>
                                        @else
                                            <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                No orders
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center flex-col gap-1">
                                            <a
                                                href="{{ route('subdomain.shop', $shop) }}"
                                                class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800 transition duration-150 ease-in-out"
                                            >
                                                <svg class="h-3.5 w-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </a>
                                            <button
                                                type="button"
                                                wire:click="edit({{ $shop->id }})"
                                                class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-indigo-900 dark:text-indigo-200 dark:hover:bg-indigo-800 transition duration-150 ease-in-out"
                                            >
                                                <svg class="h-3.5 w-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="delete({{ $shop->id }})"
                                                wire:confirm="Are you sure you want to delete this shop?"
                                                class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800 transition duration-150 ease-in-out"
                                            >
                                                <svg class="h-3.5 w-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                        <div class="flex flex-col items-center justify-center py-8">
                                            <svg class="h-12 w-12 text-gray-400 dark:text-gray-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                            </svg>
                                            <p class="text-lg font-medium text-gray-900 dark:text-gray-100">No shops found</p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Try adjusting your search or filter to find what you're looking for.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-4">
                    {{ $shops->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
