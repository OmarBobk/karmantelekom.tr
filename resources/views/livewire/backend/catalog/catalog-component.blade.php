<div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Header -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div class="flex-1 min-w-0">
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                        Catalog Management
                    </h2>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-4">
                    <button wire:click="openAddModal" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add Wholesale Product
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-lg mb-6 p-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-6">
                <!-- Search -->
                <div class="relative">
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        placeholder="Search products..."
                        class="w-full px-10 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    @if($search)
                        <button
                            wire:click="$set('search', '')"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    @endif
                </div>

                <!-- Product Filter -->
                <select wire:model.live="product" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Products</option>
                    @foreach($products as $prod)
                        <option value="{{ $prod->id }}">{{ $prod->name }}</option>
                    @endforeach
                </select>

                <!-- Currency Filter -->
                <select wire:model.live="currency" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Currencies</option>
                    @foreach($currencies as $curr)
                        <option value="{{ $curr->id }}">{{ $curr->code }}</option>
                    @endforeach
                </select>

                <!-- Status Filter -->
                <select wire:model.live="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>

                <!-- Sort Field -->
                <select wire:model.live="sortField" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="created_at">Sort By</option>
                    <option value="price">Price</option>
                    <option value="min_qty">Min Quantity</option>
                    <option value="max_qty">Max Quantity</option>
                </select>

                <!-- Bulk Actions -->
                <select wire:model.live="bulkAction" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Bulk Actions</option>
                    <option value="delete">Delete Selected</option>
                    <option value="activate">Enable Visibility</option>
                    <option value="deactivate">Disable Visibility</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Add styled scroll container for mobile -->
            <div class="overflow-x-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100 hover:scrollbar-thumb-gray-400">
                <div class="inline-block min-w-full align-middle">
                    <table class="min-w-full divide-y divide-gray-200">
                        <!-- Table Header -->
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <input type="checkbox" wire:model.live="selectAll" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                </th>
                                <th wire:click="sortBy('product_id')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                                    <div class="flex items-center space-x-1">
                                        <span>Product</span>
                                        @if($sortField === 'product_id')
                                            <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                <th wire:click="sortBy('min_qty')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                                    <div class="flex items-center space-x-1">
                                        <span>Min Qty</span>
                                        @if($sortField === 'min_qty')
                                            <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th wire:click="sortBy('max_qty')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                                    <div class="flex items-center space-x-1">
                                        <span>Max Qty</span>
                                        @if($sortField === 'max_qty')
                                            <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th wire:click="sortBy('price')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                                    <div class="flex items-center space-x-1">
                                        <span>Price</span>
                                        @if($sortField === 'price')
                                            <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>

                        <!-- Table Body -->
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($wholesaleProducts as $wholesaleProduct)
                                <!-- Wholesale Product Row -->
                                <tr class="hover:bg-gray-50 group">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" wire:model.live="selectedProducts" value="{{ $wholesaleProduct->id }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 flex-shrink-0">
                                                @if($wholesaleProduct->product && $wholesaleProduct->product->images->where('is_primary', true)->first())
                                                    <img class="h-10 w-10 rounded-lg object-cover"
                                                        src="{{ Storage::disk('public')->exists($wholesaleProduct->product->images->where('is_primary', true)->first()->image_url)
                                                            ? Storage::url($wholesaleProduct->product->images->where('is_primary', true)->first()->image_url)
                                                            : 'https://placehold.co/100' }}"
                                                        alt="{{ $wholesaleProduct->product->name }}" />
                                                @elseif($wholesaleProduct->product && $wholesaleProduct->product->images->first())
                                                    <img class="h-10 w-10 rounded-lg object-cover"
                                                        src="{{ Storage::disk('public')->exists($wholesaleProduct->product->images->first()->image_url)
                                                            ? Storage::url($wholesaleProduct->product->images->first()->image_url)
                                                            : 'https://placehold.co/100' }}"
                                                        alt="{{ $wholesaleProduct->product->name }}" />
                                                @else
                                                    <div class="h-10 w-10 rounded-lg bg-gray-200 flex items-center justify-center">
                                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                        </svg>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">{{ $wholesaleProduct->product->name ?? 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $wholesaleProduct->product->code ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $wholesaleProduct->min_qty }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $wholesaleProduct->max_qty }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ number_format($wholesaleProduct->price, 2) }} {{ $wholesaleProduct->currency->code ?? '' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-4">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium text-gray-700">Status:</span>
                                                <button wire:click="toggleStatus({{ $wholesaleProduct->id }})"
                                                        class="group relative"
                                                        wire:loading.class="opacity-50"
                                                        wire:target="toggleStatus({{ $wholesaleProduct->id }})">
                                                    <span class="sr-only">Toggle visibility</span>
                                                    <div class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 {{ $wholesaleProduct->is_active ? 'bg-blue-600' : 'bg-gray-200' }}">
                                                        <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $wholesaleProduct->is_active ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                                    </div>
                                                </button>
                                            </div>

                                            <!-- Loading indicator -->
                                            <div wire:loading wire:target="toggleStatus({{ $wholesaleProduct->id }})"
                                                class="absolute -right-6 top-1/2 -translate-y-1/2">
                                                <svg class="animate-spin h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center justify-center space-x-2">
                                            <button wire:click="editProduct({{ $wholesaleProduct->id }})" class="text-blue-600 hover:text-blue-900">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button wire:click="confirmDelete({{ $wholesaleProduct->id }})" class="text-red-600 hover:text-red-900">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Tags Row -->
                                <tr class="bg-gray-50/50 group-hover:bg-gray-50">
                                    <td class="px-6 py-2"></td>
                                    <td colspan="8" class="px-6 py-2">
                                        <div class="flex flex-wrap gap-1">
                                            @forelse($wholesaleProduct->tags as $tag)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                      style="color: {{ $tag->text_color }};
                                                             background-color: {{ $tag->background_color }};
                                                             border: 1px solid {{ $tag->border_color }};">
                                                    @if($tag->icon)
                                                        <span class="mr-1">{{ $tag->icon }}</span>
                                                    @endif
                                                    {{ $tag->name }}
                                                </span>
                                            @empty
                                                <span class="text-xs text-gray-500">No tags</span>
                                            @endforelse
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-4 text-center">
                                        <div class="flex flex-col items-center justify-center space-y-2">
                                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                            </svg>
                                            <span class="text-gray-500">No wholesale products found</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $wholesaleProducts->links() }}
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    @if($showDeleteModal)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50">
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                    <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                            </div>
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <h3 class="text-base font-semibold leading-6 text-gray-900">
                                    Delete Wholesale Product
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        Are you sure you want to delete this wholesale product? This action cannot be undone.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                            <button
                                type="button"
                                wire:click="deleteProduct"
                                class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto"
                            >
                                Delete
                            </button>
                            <button
                                type="button"
                                wire:click="$set('showDeleteModal', false)"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto"
                            >
                                Cancel
                            </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Edit Modal -->
    <div x-data="{
        init() {
            $watch('$wire.editModalOpen', value => {
                if (value) {
                    document.body.classList.add('overflow-hidden');
                } else {
                    document.body.classList.remove('overflow-hidden');
                }
            });
        }
    }"
    x-show="$wire.editModalOpen"
    class="fixed inset-0 z-[41] overflow-y-auto"
    x-cloak>
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

        <!-- Modal Content -->
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl sm:p-6"
                @click.away="$wire.editModalOpen = false">

                <!-- Modal Header -->
                <div class="flex items-center justify-between mb-4 border-b pb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Edit Wholesale Product</h3>
                    <button @click="$wire.editModalOpen = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Form Content -->
                <form wire:submit.prevent="updateProduct" class="space-y-6 overflow-auto">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Left Column -->
                        <div class="space-y-6 ml-2">
                            <!-- Product -->
                            <div>
                                <label for="edit-product" class="block text-sm font-medium text-gray-700">Product *</label>
                                <div x-data="{
                                    open: false,
                                    search: '',
                                    selectedProduct: @entangle('editForm.product_id'),
                                    products: @js($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'code' => $p->code])),
                                    get filteredProducts() {
                                        if (!this.search) return this.products;
                                        const searchLower = this.search.toLowerCase();
                                        return this.products.filter(p =>
                                            p.name.toLowerCase().includes(searchLower) ||
                                            p.code.toLowerCase().includes(searchLower)
                                        );
                                    },
                                    get selectedProductName() {
                                        if (!this.selectedProduct) return 'Select a product';
                                        const product = this.products.find(p => p.id == this.selectedProduct);
                                        return product ? `${product.name} (${product.code})` : 'Select a product';
                                    }
                                }" class="relative">
                                    <button type="button"
                                        @click="open = !open"
                                        class="mt-1 relative w-full cursor-default rounded-md border border-gray-300 bg-white py-2 pl-3 pr-10 text-left shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 sm:text-sm">
                                        <span class="block truncate" x-text="selectedProductName"></span>
                                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                                            <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    </button>
                                    <div x-show="open"
                                        @click.away="open = false"
                                        x-cloak
                                        class="absolute z-10 mt-1 w-full rounded-md bg-white shadow-lg">
                                        <div class="p-2">
                                            <input type="text"
                                                x-model="search"
                                                placeholder="Search products..."
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                        </div>
                                        <ul class="max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                            <template x-for="product in filteredProducts" :key="product.id">
                                                <li @click="selectedProduct = product.id; open = false"
                                                    class="relative cursor-pointer select-none py-2 pl-3 pr-9 hover:bg-gray-100"
                                                    :class="{ 'bg-blue-50': selectedProduct == product.id }">
                                                    <div class="flex items-center">
                                                        <span class="block truncate" x-text="`${product.name} (${product.code})`"></span>
                                                    </div>
                                                    <span x-show="selectedProduct == product.id" class="absolute inset-y-0 right-0 flex items-center pr-4 text-blue-600">
                                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                        </svg>
                                                    </span>
                                                </li>
                                            </template>
                                            <li x-show="filteredProducts.length === 0" class="relative cursor-default select-none py-2 pl-3 pr-9 text-gray-500">
                                                <div class="flex items-center">
                                                    <span class="block truncate">No products found</span>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                @error('editForm.product_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Price -->
                            <div>
                                <label for="edit-price" class="block text-sm font-medium text-gray-700">Price *</label>
                                <input type="number"
                                    wire:model="editForm.price"
                                    step="0.01"
                                    min="0"
                                    id="edit-price"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                @error('editForm.price')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Currency -->
                            <div>
                                <label for="edit-currency" class="block text-sm font-medium text-gray-700">Currency *</label>
                                <select
                                    wire:model="editForm.currency_id"
                                    id="edit-currency"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                    <option value="">Select a currency</option>
                                    @foreach($currencies as $currency)
                                        <option value="{{ $currency->id }}">{{ $currency->code }} ({{ $currency->symbol }})</option>
                                    @endforeach
                                </select>
                                @error('editForm.currency_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Status -->
                            <div class="col-span-6 sm:col-span-3">
                                <label class="block text-sm font-medium text-gray-700">Visibility</label>
                                <div class="mt-2 space-y-4">
                                    <div class="flex items-center gap-2">
                                        <label for="editForm.is_active" class="text-sm font-medium text-gray-700">Status:</label>
                                        <button type="button"
                                                wire:click="toggleEditFormVisibility"
                                                class="group relative"
                                                wire:loading.class="opacity-50"
                                                wire:target="toggleEditFormVisibility">
                                            <span class="sr-only">Toggle visibility</span>
                                            <div class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 {{ $editForm['is_active'] ? 'bg-blue-600' : 'bg-gray-200' }}">
                                                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $editForm['is_active'] ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                                @error('editForm.is_active') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="space-y-6 mr-2">
                            <!-- Min Quantity -->
                            <div>
                                <label for="edit-min-qty" class="block text-sm font-medium text-gray-700">Minimum Quantity *</label>
                                <input type="number"
                                    wire:model="editForm.min_qty"
                                    min="1"
                                    id="edit-min-qty"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                @error('editForm.min_qty')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Max Quantity -->
                            <div>
                                <label for="edit-max-qty" class="block text-sm font-medium text-gray-700">Maximum Quantity *</label>
                                <input type="number"
                                    wire:model="editForm.max_qty"
                                    min="1"
                                    id="edit-max-qty"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                @error('editForm.max_qty')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Tags -->
                            <div x-data="{ open: false }" class="relative">
                                <label for="tags" class="block text-sm font-medium text-gray-700">Tags</label>
                                <div class="relative mt-1">
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        class="relative w-full cursor-default rounded-md border border-gray-300 bg-white py-2 pl-3 pr-10 text-left shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 sm:text-sm"
                                    >
                                        <span class="flex flex-wrap gap-1">
                                            @if(!empty($editForm['tags']))
                                                @foreach($allTags->whereIn('id', $editForm['tags']) as $tag)
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                        style="color: {{ $tag->text_color }}; background-color: {{ $tag->background_color }}; border: 1px solid {{ $tag->border_color }};"
                                                    >
                                                        @if($tag->icon)
                                                            <span class="mr-1">{{ $tag->icon }}</span>
                                                        @endif
                                                        {{ $tag->name }}
                                                    </span>
                                                @endforeach
                                            @else
                                                <span class="text-gray-500">Select tags...</span>
                                            @endif
                                        </span>
                                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                                            <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    </button>

                                    <div
                                        x-show="open"
                                        @click.away="open = false"
                                        class="absolute z-10 mt-1 w-full rounded-md bg-white shadow-lg"
                                    >
                                        <ul class="max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                            @foreach($allTags as $tag)
                                                <li
                                                    class="relative cursor-pointer select-none py-2 pl-3 pr-9 hover:bg-gray-100"
                                                    wire:click="toggleTag({{ $tag->id }})"
                                                >
                                                    <div class="flex items-center">
                                                        <span
                                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                            style="color: {{ $tag->text_color }}; background-color: {{ $tag->background_color }}; border: 1px solid {{ $tag->border_color }};"
                                                        >
                                                            @if($tag->icon)
                                                                <span class="mr-1">{{ $tag->icon }}</span>
                                                            @endif
                                                            {{ $tag->name }}
                                                        </span>
                                                    </div>

                                                    @if(in_array($tag->id, $editForm['tags']))
                                                        <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-blue-600">
                                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                            </svg>
                                                        </span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                                @error('editForm.tags')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button"
                            @click="$wire.editModalOpen = false"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </button>
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Action Confirmation Modal -->
    @if($showBulkActionModal)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50">
            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-yellow-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <h3 class="text-base font-semibold leading-6 text-gray-900">
                                    Confirm Action
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        {{ $bulkActionMessage }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                            <button
                                type="button"
                                wire:click="confirmBulkAction"
                                class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto"
                            >
                                Confirm
                            </button>
                            <button
                                type="button"
                                wire:click="cancelBulkAction"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Add Product Modal -->
    <div x-data="{
            init() {
                $watch('$wire.addModalOpen', value => {
                    if (value) {
                        document.body.classList.add('overflow-hidden');
                    } else {
                        document.body.classList.remove('overflow-hidden');
                    }
                });
            }
        }"
        x-show="$wire.addModalOpen"
        class="fixed inset-0 z-[41] overflow-y-auto"
        x-cloak>
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

        <!-- Modal Content -->
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl sm:p-6"
                @click.away="$wire.addModalOpen = false">

                <!-- Modal Header -->
                <div class="flex items-center justify-between mb-4 border-b pb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Add New Wholesale Product</h3>
                    <button @click="$wire.addModalOpen = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Form Content -->
                <form wire:submit.prevent="createProduct" class="space-y-6 overflow-auto">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Left Column -->
                        <div class="space-y-6 ml-2">
                            <!-- Product -->
                            <div>
                                <label for="add-product" class="block text-sm font-medium text-gray-700">Product *</label>
                                <div x-data="{
                                    open: false,
                                    search: '',
                                    selectedProduct: @entangle('addForm.product_id'),
                                    products: @js($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'code' => $p->code])),
                                    get filteredProducts() {
                                        if (!this.search) return this.products;
                                        const searchLower = this.search.toLowerCase();
                                        return this.products.filter(p =>
                                            p.name.toLowerCase().includes(searchLower) ||
                                            p.code.toLowerCase().includes(searchLower)
                                        );
                                    },
                                    get selectedProductName() {
                                        if (!this.selectedProduct) return 'Select a product';
                                        const product = this.products.find(p => p.id == this.selectedProduct);
                                        return product ? `${product.name} (${product.code})` : 'Select a product';
                                    }
                                }" class="relative">
                                    <button type="button"
                                        @click="open = !open"
                                        class="mt-1 relative w-full cursor-default rounded-md border border-gray-300 bg-white py-2 pl-3 pr-10 text-left shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 sm:text-sm">
                                        <span class="block truncate" x-text="selectedProductName"></span>
                                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                                            <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    </button>
                                    <div x-show="open"
                                        @click.away="open = false"
                                        x-cloak
                                        class="absolute z-10 mt-1 w-full rounded-md bg-white shadow-lg">
                                        <div class="p-2">
                                            <input type="text"
                                                x-model="search"
                                                placeholder="Search products..."
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                        </div>
                                        <ul class="max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                            <template x-for="product in filteredProducts" :key="product.id">
                                                <li @click="selectedProduct = product.id; open = false"
                                                    class="relative cursor-pointer select-none py-2 pl-3 pr-9 hover:bg-gray-100"
                                                    :class="{ 'bg-blue-50': selectedProduct == product.id }">
                                                    <div class="flex items-center">
                                                        <span class="block truncate" x-text="`${product.name} (${product.code})`"></span>
                                                    </div>
                                                    <span x-show="selectedProduct == product.id" class="absolute inset-y-0 right-0 flex items-center pr-4 text-blue-600">
                                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                        </svg>
                                                    </span>
                                                </li>
                                            </template>
                                            <li x-show="filteredProducts.length === 0" class="relative cursor-default select-none py-2 pl-3 pr-9 text-gray-500">
                                                <div class="flex items-center">
                                                    <span class="block truncate">No products found</span>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                @error('addForm.product_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Price -->
                            <div>
                                <label for="add-price" class="block text-sm font-medium text-gray-700">Price *</label>
                                <input type="number"
                                    wire:model="addForm.price"
                                    step="0.01"
                                    min="0"
                                    id="add-price"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                @error('addForm.price')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Currency -->
                            <div>
                                <label for="add-currency" class="block text-sm font-medium text-gray-700">Currency *</label>
                                <select
                                    wire:model="addForm.currency_id"
                                    id="add-currency"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                    <option value="">Select a currency</option>
                                    @foreach($currencies as $currency)
                                        <option value="{{ $currency->id }}">{{ $currency->code }} ({{ $currency->symbol }})</option>
                                    @endforeach
                                </select>
                                @error('addForm.currency_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Status -->
                            <div class="col-span-6 sm:col-span-3">
                                <label class="block text-sm font-medium text-gray-700">Visibility</label>
                                <div class="mt-2 space-y-4">
                                    <div class="flex items-center gap-2">
                                        <label for="addForm.is_active" class="text-sm font-medium text-gray-700">Status:</label>
                                        <button type="button"
                                                wire:click="toggleAddFormVisibility"
                                                class="group relative">
                                            <div class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 {{ $addForm['is_active'] ? 'bg-blue-600' : 'bg-gray-200' }}">
                                                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $addForm['is_active'] ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                                @error('addForm.is_active') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="space-y-6 mr-2">
                            <!-- Min Quantity -->
                            <div>
                                <label for="add-min-qty" class="block text-sm font-medium text-gray-700">Minimum Quantity *</label>
                                <input type="number"
                                    wire:model="addForm.min_qty"
                                    min="1"
                                    id="add-min-qty"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                @error('addForm.min_qty')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Max Quantity -->
                            <div>
                                <label for="add-max-qty" class="block text-sm font-medium text-gray-700">Maximum Quantity *</label>
                                <input type="number"
                                    wire:model="addForm.max_qty"
                                    min="1"
                                    id="add-max-qty"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                @error('addForm.max_qty')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Tags -->
                            <div x-data="{ open: false }" class="relative">
                                <label for="add-tags" class="block text-sm font-medium text-gray-700">Tags</label>
                                <div class="relative mt-1">
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        class="relative w-full cursor-default rounded-md border border-gray-300 bg-white py-2 pl-3 pr-10 text-left shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 sm:text-sm"
                                    >
                                        <span class="flex flex-wrap gap-1">
                                            @if(!empty($addForm['tags']))
                                                @foreach($allTags->whereIn('id', $addForm['tags']) as $tag)
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                        style="color: {{ $tag->text_color }}; background-color: {{ $tag->background_color }}; border: 1px solid {{ $tag->border_color }};"
                                                    >
                                                        @if($tag->icon)
                                                            <span class="mr-1">{{ $tag->icon }}</span>
                                                        @endif
                                                        {{ $tag->name }}
                                                    </span>
                                                @endforeach
                                            @else
                                                <span class="text-gray-500">Select tags...</span>
                                            @endif
                                        </span>
                                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                                            <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    </button>

                                    <div
                                        x-show="open"
                                        @click.away="open = false"
                                        class="absolute z-10 mt-1 w-full rounded-md bg-white shadow-lg"
                                    >
                                        <ul class="max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                            @foreach($allTags as $tag)
                                                <li
                                                    class="relative cursor-pointer select-none py-2 pl-3 pr-9 hover:bg-gray-100"
                                                    wire:click="toggleAddTag({{ $tag->id }})"
                                                >
                                                    <div class="flex items-center">
                                                        <span
                                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                            style="color: {{ $tag->text_color }}; background-color: {{ $tag->background_color }}; border: 1px solid {{ $tag->border_color }};"
                                                        >
                                                            @if($tag->icon)
                                                                <span class="mr-1">{{ $tag->icon }}</span>
                                                            @endif
                                                            {{ $tag->name }}
                                                        </span>
                                                    </div>

                                                    @if(in_array($tag->id, $addForm['tags']))
                                                        <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-blue-600">
                                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                            </svg>
                                                        </span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                                @error('addForm.tags')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button"
                            wire:click="$set('addModalOpen', false)"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </button>
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Create Wholesale Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
