<div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Header -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div class="flex-1 min-w-0">
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                        Products Management
                    </h2>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-4">
                    <button wire:click="$set('addModalOpen', true)" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add Product
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

                <!-- Category Filter -->
                <select wire:model.live="category" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>

                <!-- Status Filter -->
                <select wire:model.live="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>

                <!-- Date Field Filter -->
                <select wire:model.live="dateField" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Sort by Date</option>
                    @foreach($dateFields as $field)
                        <option value="{{ $field }}">{{ ucfirst(str_replace('_', ' ', $field)) }}</option>
                    @endforeach
                </select>

                <!-- Date Direction Filter -->
                <select wire:model.live="dateDirection" @class([
                    'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                    'opacity-50 cursor-not-allowed' => !$dateField
                ]) @disabled(!$dateField)>
                    <option value="DESC">Newest First</option>
                    <option value="ASC">Oldest First</option>
                </select>

                <!-- Bulk Actions -->
                <select wire:model.live="bulkAction" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Bulk Actions</option>
                    <option value="delete">Delete Selected</option>
                    <option value="activate">Enable Visibility</option>
                    <option value="deactivate">Disable Visibility</option>
                </select>

                <!-- Add a loading indicator -->
                <div wire:loading wire:target="bulkAction" class="absolute inset-0 bg-white bg-opacity-50 flex items-center justify-center">
                    <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
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
                                <th wire:click="sortBy('name')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                                    <div class="flex items-center space-x-1">
                                        <span>Product Name</span>
                                        @if($sortField === 'name')
                                            <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Description
                                </th>
                                <th wire:click="sortBy('code')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                                    <div class="flex items-center space-x-1">
                                        <span>Code</span>
                                        @if($sortField === 'code')
                                            <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th wire:click="sortBy('serial')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                                    <div class="flex items-center space-x-1">
                                        <span>Serial</span>
                                        @if($sortField === 'serial')
                                            <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
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
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>

                        <!-- Table Body -->
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($products as $product)
                                <!-- Product Row -->
                                <tr class="hover:bg-gray-50 group">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" wire:model.live="selectedProducts" value="{{ $product->id }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 flex-shrink-0">
                                                <button
                                                    type="button"
                                                    wire:click="viewProductImage('{{ $product->images->where('is_primary', true)->first() ? $product->images->where('is_primary', true)->first()->image_url : 'https://placehold.co/100' }}', '{{ $product->name }}')"
                                                    class="block relative rounded-lg overflow-hidden hover:opacity-75 transition-opacity"
                                                >

                                                    <img class="h-10 w-10 rounded-lg object-containe"
                                                        src="{{ $product->images->where('is_primary', true)->first()
                                                            ? (Storage::disk('public')->exists($product->images->where('is_primary', true)->first()->image_url)
                                                                ? Storage::url($product->images->where('is_primary', true)->first()->image_url)
                                                                : 'https://placehold.co/100')
                                                            : ($product->images->first()
                                                                ? (Storage::disk('public')->exists($product->images->first()->image_url)
                                                                    ? Storage::url($product->images->first()->image_url)
                                                                    : 'https://placehold.co/100')
                                                                : 'https://placehold.co/100') }}"
                                                        alt="{{ $product->name }}" />
                                                </button>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">{{ $product->name }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 text-left max-w-[10rem]">
                                            <div class="line-clamp-3" x-data="{ expanded: false }" @click="expanded = !expanded" :class="{ 'line-clamp-none': expanded }">
                                                <span class="text-sm text-gray-500">{!! $product->description !!}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $product->code }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $product->serial ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $product->category->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-4">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium text-gray-700">Status:</span>
                                                <button wire:click="toggleStatus({{ $product->id }})"
                                                        class="group relative"
                                                        wire:loading.class="opacity-50"
                                                        wire:target="toggleStatus({{ $product->id }})">
                                                    <span class="sr-only">Toggle visibility</span>
                                                    <div class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 {{ $product->is_active ? 'bg-blue-600' : 'bg-gray-200' }}">
                                                        <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $product->is_active ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                                    </div>
                                                </button>
                                            </div>

                                            <!-- Loading indicator -->
                                            <div wire:loading wire:target="toggleStatus({{ $product->id }})"
                                                class="absolute -right-6 top-1/2 -translate-y-1/2">
                                                <svg class="animate-spin h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div class="flex flex-col space-y-1">
                                            @foreach($product->prices as $price)
                                                <span class="text-gray-600">
                                                    {{ $price->currency->code }}: {{ number_format($price->base_price, 2) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center justify-center space-x-2">
                                            <button wire:click="editProduct({{ $product->id }})" class="text-blue-600 hover:text-blue-900">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button wire:click="confirmDelete({{ $product->id }})" class="text-red-600 hover:text-red-900">
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
                                    <td colspan="9" class="px-6 py-2">
                                        <div class="flex flex-wrap gap-1">
                                            @forelse($product->tags as $tag)
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
                                    <td colspan="8" class="px-6 py-4 text-center">
                                        <div class="flex flex-col items-center justify-center space-y-2">
                                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                            </svg>
                                            <span class="text-gray-500">No products found</span>
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
                {{ $products->links() }}
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
                                    Delete Product
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        Are you sure you want to delete this product? This action cannot be undone.
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
        imagePreview: null,
        init() {
            $watch('$wire.editModalOpen', value => {
                if (value) {
                    document.body.classList.add('overflow-hidden');
                } else {
                    document.body.classList.remove('overflow-hidden');
                }
            });
        },
        addPrice() {
            $wire.editForm.prices.push({
                price: '',
                currency: 'TL',
                price_type: 'wholesale'
            });
        },
        removePrice(index) {
            $wire.editForm.prices.splice(index, 1);
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
                    <h3 class="text-lg font-semibold text-gray-900">Edit Product</h3>
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
                            <!-- Name -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Product Name (English)</label>
                                <input type="text"
                                    wire:model.blur="editForm.name"
                                    @blur="$wire.generateSlug('edit')"
                                    id="name"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                @error('editForm.name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Slug -->
                            <div>
                                <label for="slug" class="block text-sm font-medium text-gray-700">Slug (English)</label>
                                <input type="text"
                                    wire:model="editForm.slug"
                                    id="slug"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    readonly>
                                @error('editForm.slug')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Turkish Name -->
                            <div>
                                <label for="tr-name" class="block text-sm font-medium text-gray-700">Product Name (Turkish)</label>
                                <input type="text"
                                    wire:model.blur="editForm.tr_name"
                                    @blur="$wire.generateSlug('edit', 'tr')"
                                    id="tr-name"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                @error('editForm.tr_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Turkish Slug -->
                            <div>
                                <label for="tr-slug" class="block text-sm font-medium text-gray-700">Slug (Turkish)</label>
                                <input type="text"
                                    wire:model="editForm.tr_slug"
                                    id="tr-slug"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    readonly>
                                @error('editForm.tr_slug')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Arabic Name -->
                            <div>
                                <label for="ar-name" class="block text-sm font-medium text-gray-700">Product Name (Arabic)</label>
                                <input type="text"
                                    wire:model.blur="editForm.ar_name"
                                    @blur="$wire.generateSlug('edit', 'ar')"
                                    id="ar-name"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                @error('editForm.ar_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Arabic Slug -->
                            <div>
                                <label for="ar-slug" class="block text-sm font-medium text-gray-700">Slug (Arabic)</label>
                                <input type="text"
                                    wire:model="editForm.ar_slug"
                                    id="ar-slug"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    readonly>
                                @error('editForm.ar_slug')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Description -->
                            <div>
                                <label for="edit-description" class="block text-sm font-medium text-gray-700">Description (English)</label>
                                <div wire:ignore>
                                    <textarea
                                        id="edit-description"
                                        rows="3"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    ></textarea>
                                </div>
                                @error('editForm.description')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Turkish Description -->
                            <div>
                                <label for="edit-tr-description" class="block text-sm font-medium text-gray-700">Description (Turkish)</label>
                                <div wire:ignore>
                                    <textarea
                                        id="edit-tr-description"
                                        rows="3"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    ></textarea>
                                </div>
                                @error('editForm.tr_description')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Arabic Description -->
                            <div>
                                <label for="edit-ar-description" class="block text-sm font-medium text-gray-700">Description (Arabic)</label>
                                <div wire:ignore>
                                    <textarea
                                        id="edit-ar-description"
                                        rows="3"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    ></textarea>
                                </div>
                                @error('editForm.ar_description')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Code & Serial -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="code" class="block text-sm font-medium text-gray-700">Product Code</label>
                                    <input type="text"
                                        wire:model="editForm.code"
                                        id="code"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                    @error('editForm.code')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="serial" class="block text-sm font-medium text-gray-700">Serial Number</label>
                                    <input type="text"
                                        wire:model="editForm.serial"
                                        id="serial"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                    @error('editForm.serial')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
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
                            <!-- Category -->
                            <div>
                                <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                                <x-category-tree-dropdown
                                    :categories="$this->getCategoryTree()"
                                    :selectedCategory="$editForm['category_id']"
                                    wireModel="editForm.category_id"
                                />
                                @error('editForm.category_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Prices -->
                            <div x-data="{
                                tryPrice: @entangle('editForm.prices.0.price').live,
                                usdPrice: @entangle('editForm.prices.1.price').live,
                                exchangeRate: @entangle('exchangeRate').live,
                                calculateUSD() {
                                    if (this.tryPrice && this.exchangeRate) {
                                        this.usdPrice = (parseFloat(this.tryPrice) * this.exchangeRate).toFixed(2);
                                    }
                                },
                                calculateTRY() {
                                    if (this.usdPrice && this.exchangeRate) {
                                        this.tryPrice = (parseFloat(this.usdPrice) / this.exchangeRate).toFixed(2);
                                    }
                                }
                            }">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-sm font-medium text-gray-700">Prices</label>
                                </div>
                                <div class="space-y-4">
                                    <!-- Price (TRY) -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Price (TRY) - Optional</label>
                                        <div class="flex items-center gap-2">
                                            <input type="number"
                                                wire:model="editForm.prices.0.price"
                                                @input="calculateUSD()"
                                                step="0.01"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                placeholder="Optional (defaults to 0)">
                                            <span class="mt-1 block w-24 px-3 py-2 bg-gray-100 rounded-md border border-gray-300 text-gray-700 sm:text-sm">
                                                TRY
                                            </span>
                                        </div>
                                        @error('editForm.prices.0.price')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Price (USD) -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Price (USD) - Optional</label>
                                        <div class="flex items-center gap-2">
                                            <input type="number"
                                                wire:model="editForm.prices.1.price"
                                                @input="calculateTRY()"
                                                step="0.01"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                placeholder="Optional (defaults to 0)">
                                            <span class="mt-1 block w-24 px-3 py-2 bg-gray-100 rounded-md border border-gray-300 text-gray-700 sm:text-sm">
                                                USD
                                            </span>
                                        </div>
                                        @error('editForm.prices.1.price')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Images -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Product Images</label>

                                <!-- Current Images Grid -->
                                <div class="grid grid-cols-3 gap-4 mb-4">
                                    @foreach($currentImages as $image)
                                        <div class="relative group aspect-square">
                                            <img
                                                src="{{ $image['url'] ? Storage::url($image['url']) : 'https://placehold.co/100' }}"
                                                alt="Product image"
                                                class="h-full w-full rounded-lg object-cover">
                                            <div class="absolute inset-0 bg-black bg-opacity-50 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                                <button type="button"
                                                    wire:click="setPrimaryImage({{ $image['id'] }})"
                                                    class="p-1 text-white hover:text-yellow-400 {{ $image['is_primary'] ? 'text-yellow-400' : '' }}">
                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                                    </svg>
                                                </button>
                                                <button type="button"
                                                    wire:click="viewImage('{{ $image['url'] }}', '{{ $editForm['name'] }}')"
                                                    class="p-1 text-white hover:text-blue-400">
                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </button>
                                                <button type="button"
                                                    wire:click="confirmImageDelete({{ $image['id'] }}, '{{ $editForm['name'] }}')"
                                                    class="p-1 text-white hover:text-red-400">
                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                            @if($image['is_primary'])
                                                <div class="absolute -top-2 -right-2 bg-yellow-400 rounded-full p-1">
                                                    <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                <!-- New Image Upload Area -->
                                <div class="mt-2 space-y-4">
                                    <div
                                        x-data="{
                                            isDropping: false,
                                            handleDrop(e) {
                                                e.preventDefault();
                                                const input = this.$refs.fileInput;
                                                const files = e.dataTransfer.files;
                                                input.files = files;
                                                const event = new Event('change', { bubbles: true });
                                                input.dispatchEvent(event);
                                                this.isDropping = false;
                                            }
                                        }"
                                        class="relative"
                                        @dragover.prevent="isDropping = true"
                                        @dragleave.prevent="isDropping = false"
                                        @drop="handleDrop($event)"
                                    >
                                        <label
                                            for="images-{{ $iteration }}"
                                            :class="{ 'bg-blue-50 border-blue-300': isDropping }"
                                            class="relative block w-full rounded-lg border-2 border-dashed border-gray-300 p-4 text-center hover:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 cursor-pointer transition-colors duration-200"
                                        >
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <span class="mt-2 block text-sm font-medium text-gray-900">
                                            Drop multiple images here or click to upload
                                        </span>
                                            <span class="mt-1 block text-xs text-gray-500">
                                                You can select multiple images at once (PNG, JPG, GIF up to 2MB each)
                                            </span>
                                    </label>
                                        <input
                                            x-ref="fileInput"
                                            id="images-{{ $iteration }}"
                                            wire:model.defer="newImages"
                                            type="file"
                                            multiple
                                            accept="image/*"
                                            class="hidden"
                                        >
                                    </div>

                                    <!-- Preview Grid for New Images -->
                                    @if($newImages)
                                        <div class="mb-2 text-sm text-gray-600">
                                            {{ count($newImages) }} {{ Str::plural('image', count($newImages)) }} selected
                                        </div>
                                        <div class="grid grid-cols-3 gap-4">
                                            @foreach($newImages as $index => $image)
                                                @if($image)
                                                <div class="relative aspect-square group">
                                                    <img src="{{ $image->temporaryUrl() }}"
                                                        alt="Upload preview"
                                                        class="h-full w-full rounded-lg object-cover">

                                                    <!-- Upload Progress Overlay -->
                                                    @if(isset($uploadProgress['newImages.' . $index]))
                                                    <div class="absolute inset-0 bg-black bg-opacity-50 rounded-lg flex items-center justify-center">
                                                        <div class="text-white text-center">
                                                            @if(isset($uploadProgress['newImages.' . $index]['error']))
                                                                <div class="text-red-500 text-sm">
                                                                    {{ $uploadProgress['newImages.' . $index]['error'] }}
                                                                </div>
                                                            @else
                                                                <div class="relative w-16 h-16 mx-auto">
                                                                    <!-- Progress Circle -->
                                                                    <svg class="transform -rotate-90 w-full h-full" viewBox="0 0 100 100">
                                                                        <circle
                                                                            class="text-gray-400 stroke-current"
                                                                            stroke-width="10"
                                                                            fill="transparent"
                                                                            r="45"
                                                                            cx="50"
                                                                            cy="50"
                                                                        />
                                                                        <circle
                                                                            class="text-blue-600 progress-ring stroke-current"
                                                                            stroke-width="10"
                                                                            fill="transparent"
                                                                            r="45"
                                                                            cx="50"
                                                                            cy="50"
                                                                            style="stroke-dasharray: 283; stroke-dashoffset: {{ 283 - ($uploadProgress['newImages.' . $index]['progress'] * 283 / 100) }}"
                                                                        />
                                                                    </svg>
                                                                    <div class="absolute inset-0 flex items-center justify-center">
                                                                        <span class="text-white text-sm">
                                                                            {{ $uploadProgress['newImages.' . $index]['progress'] }}%
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @endif

                                                    <!-- Remove Button -->
                                                    <button
                                                        type="button"
                                                        wire:click="removeTemporaryImage({{ $index }})"
                                                        class="absolute top-2 right-2 p-1 rounded-full bg-red-500 text-white opacity-0 group-hover:opacity-100 transition-opacity"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </div>
                                                @endif
                                            @endforeach
                                </div>
                                    @endif

                                @error('newImages.*')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                </div>
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
    <style>
        .progress-ring {
            transition: stroke-dashoffset 0.35s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
    </style>

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

    <!-- Add the Full Size Image Modal -->
    @if($showProductImageModal && $viewingProductImage)
        <div class="fixed inset-0 bg-black bg-opacity-75 z-[60]" wire:click.self="closeProductImageView">
            <div class="relative max-w-7xl mx-auto p-4 w-full h-full flex items-center justify-center" @click.stop>
                <!-- Close button -->
                <button
                    wire:click.stop="closeProductImageView"
                    class="absolute top-4 right-4 text-white hover:text-gray-300 z-10"
                >
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Image -->
                <div class="relative w-full h-full flex items-center justify-center">
                    <img
                        src="{{ Storage::url($viewingProductImage['url']) }}"
                        alt="Full size product image"
                        class="max-h-full max-w-full object-contain"
                    >
                    <!-- <img class="max-h-full max-w-full object-contain"
                        src="{{ $product->images->where('is_primary', true)->first()
                            ? Storage::url($product->images->where('is_primary', true)->first()->image_url)
                            : ($product->images->first()
                                ? Storage::url($product->images->first()->image_url)
                                : 'https://placehold.co/100') }}"
                        alt="Full size product image" /> -->
                </div>
            </div>
        </div>
    @endif

    <!-- Add the Image Modal -->
    @if($showImageModal && $viewingImage)
        <!-- Stop propagation of clicks on the overlay to prevent closing the edit modal -->
        <div class="fixed inset-0 bg-black bg-opacity-75 z-[60]"
            wire:click.stop.self="closeImageView"
            @click.stop>
            <div class="relative max-w-7xl mx-auto p-4 w-full h-full flex items-center justify-center">
                <!-- Close button -->
                <button
                    wire:click.stop="closeImageView"
                    class="absolute top-4 right-4 text-white hover:text-gray-300 z-10"
                >
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Image -->
                <div class="relative w-full h-full flex items-center justify-center">
                    <img
                        src="{{ Storage::url($viewingImage['url']) }}"
                        alt="Full size product image"
                        class="max-h-full max-w-full object-contain"
                    >
                </div>
            </div>
        </div>
    @endif

    <!-- Add the Image Delete Confirmation Modal -->
    @if($showImageDeleteModal && $imageToDelete)
        <div class="fixed inset-0 bg-black bg-opacity-75 z-[70]" wire:click.stop>
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
                                Delete Image
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    Are you sure you want to delete this image? This action cannot be undone.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                        <button
                            type="button"
                            wire:click="deleteImage"
                            class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto"
                        >
                            Delete
                        </button>
                        <button
                            type="button"
                            wire:click="cancelImageDelete"
                            class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif


    <!-- Add Product Modal -->
    <div x-data="{
            imagePreview: null,
            init() {
                $watch('$wire.addModalOpen', value => {
                    if (value) {
                        document.body.classList.add('overflow-hidden');
                    } else {
                        document.body.classList.remove('overflow-hidden');
                    }
                });
            },
            addPrice() {
                $wire.addForm.prices.push({
                    price: '',
                    currency: 'TL',
                    price_type: 'retail'
                });
            },
            removePrice(index) {
                $wire.addForm.prices.splice(index, 1);
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
                    <h3 class="text-lg font-semibold text-gray-900">Add New Product</h3>
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
                            <!-- Name -->
                            <div>
                                <label for="add-name" class="block text-sm font-medium text-gray-700">Product Name (English)</label>
                                <input type="text"
                                    wire:model.blur="addForm.name"
                                    @blur="$wire.generateSlug('add')"
                                    id="add-name"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                @error('addForm.name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Slug -->
                            <div>
                                <label for="add-slug" class="block text-sm font-medium text-gray-700">Slug (English)</label>
                                <input type="text"
                                    wire:model="addForm.slug"
                                    id="add-slug"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    readonly>
                                @error('addForm.slug')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Turkish Name -->
                            <div>
                                <label for="add-tr-name" class="block text-sm font-medium text-gray-700">Product Name (Turkish)</label>
                                <input type="text"
                                    wire:model.blur="addForm.tr_name"
                                    @blur="$wire.generateSlug('add', 'tr')"
                                    id="add-tr-name"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                @error('addForm.tr_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Turkish Slug -->
                            <div>
                                <label for="add-tr-slug" class="block text-sm font-medium text-gray-700">Slug (Turkish)</label>
                                <input type="text"
                                    wire:model="addForm.tr_slug"
                                    id="add-tr-slug"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    readonly>
                                @error('addForm.tr_slug')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Arabic Name -->
                            <div>
                                <label for="add-ar-name" class="block text-sm font-medium text-gray-700">Product Name (Arabic)</label>
                                <input type="text"
                                    wire:model.blur="addForm.ar_name"
                                    @blur="$wire.generateSlug('add', 'ar')"
                                    id="add-ar-name"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                @error('addForm.ar_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Arabic Slug -->
                            <div>
                                <label for="add-ar-slug" class="block text-sm font-medium text-gray-700">Slug (Arabic)</label>
                                <input type="text"
                                    wire:model="addForm.ar_slug"
                                    id="add-ar-slug"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    readonly>
                                @error('addForm.ar_slug')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Description -->
                            <div>
                                <label for="add-description" class="block text-sm font-medium text-gray-700">Description (English)</label>
                                <textarea
                                    wire:model="addForm.description"
                                    id="add-description"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"></textarea>
                                @error('addForm.description')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Turkish Description -->
                            <div>
                                <label for="add-tr-description" class="block text-sm font-medium text-gray-700">Description (Turkish)</label>
                                <textarea
                                    wire:model="addForm.tr_description"
                                    id="add-tr-description"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"></textarea>
                                @error('addForm.tr_description')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Arabic Description -->
                            <div>
                                <label for="add-ar-description" class="block text-sm font-medium text-gray-700">Description (Arabic)</label>
                                <textarea
                                    wire:model="addForm.ar_description"
                                    id="add-ar-description"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"></textarea>
                                @error('addForm.ar_description')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Code & Serial -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="add-code" class="block text-sm font-medium text-gray-700">Product Code</label>
                                    <input type="text"
                                        wire:model="addForm.code"
                                        id="add-code"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                    @error('addForm.code')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="add-serial" class="block text-sm font-medium text-gray-700">Serial Number</label>
                                    <input type="text"
                                        wire:model="addForm.serial"
                                        id="add-serial"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                    @error('addForm.serial')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
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
                            <!-- Category -->
                            <div>
                                <label for="add-category" class="block text-sm font-medium text-gray-700">Category</label>
                                <x-category-tree-dropdown
                                    :categories="$this->getCategoryTree()"
                                    :selectedCategory="$addForm['category_id']"
                                    wireModel="addForm.category_id"
                                />
                                @error('addForm.category_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Prices -->
                            <div x-data="{
                                tryPrice: @entangle('addForm.prices.0.price').live,
                                usdPrice: @entangle('addForm.prices.1.price').live,
                                exchangeRate: @entangle('exchangeRate').live,
                                calculateUSD() {
                                    if (this.tryPrice && this.exchangeRate) {
                                        this.usdPrice = (parseFloat(this.tryPrice) * this.exchangeRate).toFixed(2);
                                    }
                                },
                                calculateTRY() {
                                    if (this.usdPrice && this.exchangeRate) {
                                        this.tryPrice = (parseFloat(this.usdPrice) / this.exchangeRate).toFixed(2);
                                    }
                                }
                            }">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-sm font-medium text-gray-700">Prices</label>
                                </div>
                                <div class="space-y-4">
                                    <!-- Price (TRY) -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Price (TRY) - Optional</label>
                                        <div class="flex items-center gap-2">
                                            <input type="number"
                                                wire:model="addForm.prices.0.price"
                                                @input="calculateUSD()"
                                                step="0.01"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                placeholder="Optional (defaults to 0)">
                                            <span class="mt-1 block w-24 px-3 py-2 bg-gray-100 rounded-md border border-gray-300 text-gray-700 sm:text-sm">
                                                TRY
                                            </span>
                                        </div>
                                        @error('addForm.prices.0.price')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Price (USD) -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Price (USD) - Optional</label>
                                        <div class="flex items-center gap-2">
                                            <input type="number"
                                                wire:model="addForm.prices.1.price"
                                                @input="calculateTRY()"
                                                step="0.01"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                placeholder="Optional (defaults to 0)">
                                            <span class="mt-1 block w-24 px-3 py-2 bg-gray-100 rounded-md border border-gray-300 text-gray-700 sm:text-sm">
                                                USD
                                            </span>
                                        </div>
                                        @error('addForm.prices.1.price')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Images -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Product Images</label>
                                <div class="mt-2 space-y-4">
                                    <div
                                        x-data="{
                                            isDropping: false,
                                            handleDrop(e) {
                                                e.preventDefault();
                                                const input = this.$refs.fileInput;
                                                const files = e.dataTransfer.files;
                                                input.files = files;
                                                const event = new Event('change', { bubbles: true });
                                                input.dispatchEvent(event);
                                                this.isDropping = false;
                                            }
                                        }"
                                        class="relative"
                                        @dragover.prevent="isDropping = true"
                                        @dragleave.prevent="isDropping = false"
                                        @drop="handleDrop($event)"
                                    >
                                        <label
                                            for="add-images-{{ $iteration }}"
                                            :class="{ 'bg-blue-50 border-blue-300': isDropping }"
                                            class="relative block w-full rounded-lg border-2 border-dashed border-gray-300 p-4 text-center hover:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 cursor-pointer transition-colors duration-200"
                                        >
                                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            <span class="mt-2 block text-sm font-medium text-gray-900">
                                                Drop multiple images here or click to upload
                                            </span>
                                            <span class="mt-1 block text-xs text-gray-500">
                                                You can select multiple images at once (PNG, JPG, GIF up to 2MB each)
                                            </span>
                                    </label>
                                        <input
                                            x-ref="fileInput"
                                            id="add-images-{{ $iteration }}"
                                            wire:model.defer="newProductImages"
                                            type="file"
                                            multiple
                                            accept="image/*"
                                            class="hidden"
                                        >
                                    </div>

                                    <!-- Preview Grid for New Images -->
                                    @if($newProductImages)
                                        <div class="mb-2 text-sm text-gray-600">
                                            {{ count($newProductImages) }} {{ Str::plural('image', count($newProductImages)) }} selected
                                        </div>
                                        <div class="grid grid-cols-3 gap-4">
                                            @foreach($newProductImages as $index => $image)
                                                <div class="relative aspect-square group">
                                                    <img src="{{ $image->temporaryUrl() }}"
                                                        alt="Upload preview"
                                                        class="h-full w-full rounded-lg object-cover">

                                                    <!-- Remove Button -->
                                                    <button
                                                        type="button"
                                                        wire:click="removeTemporaryImage({{ $index }}, 'add')"
                                                        class="absolute top-2 right-2 p-1 rounded-full bg-red-500 text-white opacity-0 group-hover:opacity-100 transition-opacity"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                @error('newProductImages.*')
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
                            Create Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')

        <script>
            document.addEventListener('livewire:init', () => {
                const {
                    ClassicEditor,
                    Essentials,
                    Bold,
                    Italic,
                    Font,
                    Paragraph
                } = CKEDITOR;

                let enEditor = null;
                let trEditor = null;
                let arEditor = null;

                const createEditor = (selector, initialValueGetter, valueSetter, existingInstanceRefSetter) => {
                    const textarea = document.querySelector(selector);

                    if (!textarea) {
                        return null;
                    }

                    const existingInstance = existingInstanceRefSetter('get');
                    if (existingInstance) {
                        existingInstance.destroy().catch(() => {});
                        existingInstanceRefSetter('set', null);
                    }

                    return ClassicEditor.create(textarea, {
                        licenseKey: 'eyJhbGciOiJFUzI1NiJ9.eyJleHAiOjE3OTYxNjk1OTksImp0aSI6IjFiYjE0ZTNhLWYwNjYtNDJkZi04ZTk0LTU1MTBlMjRjYzI3YyIsInVzYWdlRW5kcG9pbnQiOiJodHRwczovL3Byb3h5LWV2ZW50LmNrZWRpdG9yLmNvbSIsImRpc3RyaWJ1dGlvbkNoYW5uZWwiOlsiY2xvdWQiLCJkcnVwYWwiXSwiZmVhdHVyZXMiOlsiRFJVUCIsIkUyUCIsIkUyVyJdLCJyZW1vdmVGZWF0dXJlcyI6WyJQQiIsIlJGIiwiU0NIIiwiVENQIiwiVEwiLCJUQ1IiLCJJUiIsIlNVQSIsIkI2NEEiLCJMUCIsIkhFIiwiUkVEIiwiUEZPIiwiV0MiLCJGQVIiLCJCS00iLCJGUEgiLCJNUkUiXSwidmMiOiIxNWE1MjQ4OCJ9.lkrgGKKiwWH94_jtv0BsO5pFm857YRhGEC_SSVFBwQYvbnE0D2-17qRSmArpwYaYQONiNNLgnBBi1k0X-Vqkzg',
                        plugins: [Essentials, Bold, Italic, Font, Paragraph],
                        toolbar: [
                            'undo', 'redo', '|', 'bold', 'italic', '|',
                            'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor'
                        ]
                    })
                        .then(editor => {
                            existingInstanceRefSetter('set', editor);
                            editor.setData(initialValueGetter() ?? '');

                            editor.model.document.on('change:data', () => {
                                valueSetter(editor.getData());
                            });
                        })
                        .catch(error => {
                            console.error(error);
                        });
                };

                const initEditEditors = () => {
                    createEditor(
                        '#edit-description',
                        () => @this.get('editForm.description'),
                        value => @this.set('editForm.description', value),
                        (action, value = null) => {
                            if (action === 'get') return enEditor;
                            if (action === 'set') enEditor = value;
                        }
                    );

                    createEditor(
                        '#edit-tr-description',
                        () => @this.get('editForm.tr_description'),
                        value => @this.set('editForm.tr_description', value),
                        (action, value = null) => {
                            if (action === 'get') return trEditor;
                            if (action === 'set') trEditor = value;
                        }
                    );

                    createEditor(
                        '#edit-ar-description',
                        () => @this.get('editForm.ar_description'),
                        value => @this.set('editForm.ar_description', value),
                        (action, value = null) => {
                            if (action === 'get') return arEditor;
                            if (action === 'set') arEditor = value;
                        }
                    );
                };

                // Initialize when Livewire asks for it (when edit modal opens)
                Livewire.on('initEditDescriptionEditor', () => {
                    // Small timeout to ensure the modal DOM is fully rendered and visible
                    setTimeout(initEditEditors, 50);
                });
            });
        </script>
    @endpush

</div>
