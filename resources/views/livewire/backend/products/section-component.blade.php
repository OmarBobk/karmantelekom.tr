<div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-3xl font-bold text-gray-900">Product Sections</h3>
                    <p class="mt-1 text-gray-600">Manage your product sections and their contents</p>
                </div>
                <div class="mt-4 md:mt-0">
                    <button wire:click="create" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Add Section
                    </button>
                </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="mb-6">
            <div class="relative">
                <input
                    wire:model.live.debounce.300ms="searchTerm"
                    type="text"
                    class="w-full pl-10 pr-4 py-2 text-sm text-gray-900 bg-white border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Search sections..."
                >
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Sections Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto min-w-full">
                <div class="inline-block min-w-full align-middle">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Position</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Order</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Scrollable</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Products</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($sections as $section)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ $section->name }}</div>
                                        @if($section->description)
                                            <div class="text-sm text-gray-500">{{ Str::limit($section->description, 50) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize {{ $section->position->getBackgroundColor() }}">
                                            {{ str_replace('.', ' → ', $section->position->value) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $section->order }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button wire:click="toggleActive({{ $section->id }})"
                                                class="group relative"
                                                wire:loading.class="opacity-50"
                                                wire:target="toggleActive({{ $section->id }})">
                                            <span class="sr-only">Toggle section status</span>
                                            <div class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 {{ $section->is_active ? 'bg-blue-600' : 'bg-gray-200' }}">
                                                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $section->is_active ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                            </div>

                                            <!-- Loading indicator -->
                                            <div wire:loading wire:target="toggleActive({{ $section->id }})"
                                                class="absolute -right-6 top-1/2 -translate-y-1/2">
                                                <svg class="animate-spin h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </div>

                                            <span class="absolute left-1/2 -translate-x-1/2 -bottom-8 hidden group-hover:block bg-gray-900 text-white text-xs rounded py-1 px-2 whitespace-nowrap">
                                                {{ $section->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button wire:click="toggleScrollable({{ $section->id }})"
                                                class="group relative"
                                                wire:loading.class="opacity-50"
                                                wire:target="toggleScrollable({{ $section->id }})">
                                            <span class="sr-only">Toggle section scrollable</span>
                                            <div class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 {{ $section->scrollable ? 'bg-blue-600' : 'bg-gray-200' }}">
                                                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $section->scrollable ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                            </div>

                                            <span class="absolute left-1/2 -translate-x-1/2 -bottom-8 hidden group-hover:block bg-gray-900 text-white text-xs rounded py-1 px-2 whitespace-nowrap">
                                                {{ $section->scrollable ? 'Scrollable' : 'Not Scrollable' }}
                                            </span>
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div x-data="{ showAllProducts: false }" class="relative">
                                            <div class="flex flex-wrap"
                                                x-transition:enter="transition ease-out duration-300"
                                                x-transition:enter-start="opacity-0 scale-95"
                                                x-transition:enter-end="opacity-100 scale-100"
                                                x-transition:leave="transition ease-in duration-200"
                                                x-transition:leave-start="opacity-100 scale-100"
                                                x-transition:leave-end="opacity-0 scale-95">

                                                <!-- First 3 products -->
                                                <div x-show="!showAllProducts">
                                                    @foreach($section->products->take(3) as $product)
                                                        <div class="inline-block h-8 w-8 rounded-full ring-2 ring-white overflow-hidden bg-gray-100">
                                                            @if($product->images->where('is_primary', true)->first())
                                                                <img src="{{ Storage::url($product->images->where('is_primary', true)->first()->image_url) }}"
                                                                    alt="{{ $product->name }}"
                                                                    class="h-full w-full object-cover">
                                                            @else
                                                                <div class="h-full w-full flex items-center justify-center bg-gray-300 text-gray-500">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                                    </svg>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>

                                                <!-- All products -->
                                                <div x-show="showAllProducts">
                                                    @foreach($section->products as $product)
                                                        <div class="inline-block h-8 w-8 rounded-full ring-2 ring-white overflow-hidden bg-gray-100">
                                                            @if($product->images->where('is_primary', true)->first())
                                                                <img src="{{ Storage::url($product->images->where('is_primary', true)->first()->image_url) }}"
                                                                    alt="{{ $product->name }}"
                                                                    class="h-full w-full object-cover">
                                                            @else
                                                                <div class="h-full w-full flex items-center justify-center bg-gray-300 text-gray-500">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                                    </svg>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>

                                                @if($section->products->count() > 3)
                                                    <button @click="showAllProducts = !showAllProducts"
                                                            class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 ring-2 ring-white hover:bg-gray-200 transition-colors duration-200">
                                                        <span x-text="showAllProducts ? '-' : '+{{ $section->products->count() - 3 }}'"
                                                            class="text-xs font-medium text-gray-500"></span>
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end space-x-2">
                                            <button wire:click="edit({{ $section->id }})" class="text-blue-600 hover:text-blue-900">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button wire:click="delete({{ $section->id }})"
                                                    wire:confirm="Are you sure you want to delete this section?"
                                                    class="text-red-600 hover:text-red-900">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        No sections found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $sections->links(data: ['scrollTo' => false]) }}
            </div>
        </div>

        <!-- Section Modal -->
        <div x-data="{ show: @entangle('showModal') }"
            x-show="show"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="show"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"
                    @click="show = false">
                </div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div x-show="show"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="inline-block w-full max-w-2xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white rounded-lg shadow-xl">

                    <div class="absolute top-0 right-0 pt-4 pr-4">
                        <button @click="show = false" type="button" class="text-gray-400 hover:text-gray-500">
                            <span class="sr-only">Close</span>
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="mb-4">
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ $editingSection ? 'Edit Section' : 'Create New Section' }}
                        </h3>
                    </div>

                    <form wire:submit="save" class="space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" wire:model="name" id="name"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            @error('name') <span class="mt-1 text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea wire:model="description" id="description" rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"></textarea>
                            @error('description') <span class="mt-1 text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="position" class="block text-sm font-medium text-gray-700">Position</label>
                                <select wire:model="position" id="position"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                    @foreach($this->availablePositions as $positionOption)
                                        <option value="{{ $positionOption->value }}">
                                            {{ str_replace('.', ' → ', ucfirst($positionOption->value)) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('position') <span class="mt-1 text-sm text-red-600">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="order" class="block text-sm font-medium text-gray-700">Section Order</label>
                                <input type="number" wire:model="order" id="order"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                @error('order') <span class="mt-1 text-sm text-red-600">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">


                            <div>
                                <label class="block text-sm font-medium text-gray-700">Status</label>
                                <div class="mt-2">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-600">Active</span>
                                    </label>
                                </div>
                                @error('is_active') <span class="mt-1 text-sm text-red-600">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Scrollable</label>
                                <div class="mt-2">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" wire:model="scrollable" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-600">Enable Scrolling</span>
                                    </label>
                                </div>
                                @error('scrollable') <span class="mt-1 text-sm text-red-600">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Products</label>

                            <!-- Selected Products Chips -->
                            <div class="mb-4">
                                <div class="text-sm text-gray-500 mb-2">Selected Products ({{ count($selectedProducts) }})</div>
                                <div class="flex flex-wrap gap-2 mb-4 {{ empty($selectedProducts) ? 'hidden' : '' }}">
                                    @foreach($this->selectedProductsList as $product)
                                        <div class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm bg-blue-50 text-blue-700 border border-blue-200 group hover:bg-blue-100 transition-colors duration-200">
                                            <div class="flex items-center gap-2">
                                                @if($product->images->where('is_primary', true)->first())
                                                    <img src="{{ Storage::url($product->images->where('is_primary', true)->first()->image_url) }}"
                                                        alt="{{ $product->name }}"
                                                        class="h-5 w-5 rounded-full object-cover">
                                                @else
                                                    <div class="h-5 w-5 rounded-full bg-gray-200 flex items-center justify-center">
                                                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                        </svg>
                                                    </div>
                                                @endif
                                                <div x-data="{ expanded: false }" class="cursor-pointer">
                                                    <span x-show="!expanded"
                                                        @click="expanded = true"
                                                        class="hover:text-blue-800">
                                                        {{ \Illuminate\Support\Str::words($product->name, 2, '...') }}
                                                    </span>
                                                    <span x-show="expanded"
                                                        @click="expanded = false"
                                                        @click.away="expanded = false"
                                                        class="hover:text-blue-800">
                                                        {{ $product->name }}
                                                    </span>
                                                </div>
                                                <span class="border-l border-blue-200 pl-2">{{ $product->code }}</span>
                                                <div class="flex items-center gap-2 text-xs text-blue-600">
                                                    <span class="border-l border-blue-200 pl-2">Order: {{ $productOrders[$product->id] ?? 0 }}</span>
                                                </div>
                                            </div>
                                            <button type="button"
                                                    wire:click="removeProduct('{{ $product->id }}')"
                                                    class="ml-1 text-blue-400 hover:text-blue-600">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                                @if(empty($selectedProducts))
                                    <div class="text-sm text-gray-500 italic mb-4">No products selected</div>
                                @endif
                            </div>

                            <!-- Search Box -->
                            <div class="mb-4">
                                <div class="relative">
                                    <input
                                        wire:model.live.debounce.300ms="productSearchTerm"
                                        type="text"
                                        class="w-full pl-10 pr-4 py-2 text-sm text-gray-900 bg-white border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Search products by name, code, or serial..."
                                    >
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Products Table -->
                            <div class="border border-gray-300 rounded-lg overflow-hidden">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    <input type="checkbox"
                                                        wire:model.live="selectAllProducts"
                                                        class="rounded border-gray-300 text-blue-600">
                                                </th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Image</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($this->filteredProducts as $product)
                                                <tr class="hover:bg-gray-50" wire:key="product-{{ $product->id }}">
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <input type="checkbox"
                                                            wire:model.live="selectedProducts"
                                                            value="{{ $product->id }}"
                                                            class="rounded border-gray-300 text-blue-600">
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        @if($product->images->where('is_primary', true)->first())
                                                            <button type="button"
                                                                x-on:click.stop="$dispatch('open-image-modal', {
                                                                    url: '{{ Storage::url($product->images->where('is_primary', true)->first()->image_url) }}',
                                                                    alt: '{{ $product->name }}'
                                                                })"
                                                                class="h-10 w-10 rounded-lg overflow-hidden">
                                                                <img src="{{ Storage::url($product->images->where('is_primary', true)->first()->image_url) }}"
                                                                    alt="{{ $product->name }}"
                                                                    class="h-full w-full object-cover">
                                                            </button>
                                                        @else
                                                            <div class="h-10 w-10 rounded-lg bg-gray-100 flex items-center justify-center">
                                                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                                </svg>
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900">{{ $product->name }}</div>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <div class="text-sm text-gray-500">{{ $product->code }}</div>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <input type="number"
                                                            wire:model="productOrders.{{ $product->id }}"
                                                            class="w-20 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                            min="0">
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="px-4 py-3 border-t border-gray-200">
                                    {{ $this->filteredProducts->links(data: ['scrollTo' => false]) }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 sm:mt-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    class="inline-flex justify-center w-full px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto">
                                {{ $editingSection ? 'Update Section' : 'Create Section' }}
                            </button>
                            <button type="button"
                                    @click="show = false"
                                    class="mt-3 inline-flex justify-center w-full px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Full Screen Image Modal -->
        <div x-data="{
            showFullImage: false,
            imageUrl: '',
            imageAlt: '',
            init() {
                this.$watch('showFullImage', value => {
                    if (value) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow = 'auto';
                    }
                });
            }
        }"
            @open-image-modal.window="
                showFullImage = true;
                imageUrl = $event.detail.url;
                imageAlt = $event.detail.alt;
            "
            x-show="showFullImage"
            x-cloak
            @keydown.escape.window="showFullImage = false"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-75"
            @click="showFullImage = false">

            <div class="relative max-w-7xl mx-auto p-4" @click.stop>
                <button type="button"
                        @click="showFullImage = false"
                        class="flex justify-end w-full text-white hover:text-gray-300 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                <img :src="imageUrl"
                    :alt="imageAlt"
                    class="max-h-[90vh] max-w-full object-contain">
            </div>
        </div>
    </div>
</div>
