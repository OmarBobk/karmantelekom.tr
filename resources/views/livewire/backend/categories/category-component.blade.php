<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div class="flex-1 min-w-0">
                    <h2 class="text-3xl font-bold leading-7 text-gray-900 sm:text-4xl sm:truncate">
                        Categories Management
                    </h2>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-4">
                    <button wire:click="$set('addModalOpen', true)"
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 ease-in-out">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Add Category
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm mb-6 p-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                <!-- Search -->
                <div class="relative">
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        placeholder="Search categories..."
                        class="w-full px-10 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out"
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
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 transition duration-150 ease-in-out"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    @endif
                </div>

                <!-- Status Filter -->
                <select wire:model.live="status"
                        class="w-full px-3 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>

                <!-- Date Filter -->
                <select wire:model.live="dateFilter"
                        class="w-full px-3 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out">
                    <option value="">All Time</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="year">This Year</option>
                </select>

                <!-- Order By Filter -->
                <select wire:model.live="orderBy"
                        class="w-full px-3 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out">
                    <option value="newest">Newest to Oldest</option>
                    <option value="oldest">Oldest to Newest</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left">
                            <input type="checkbox" wire:model.live="selectAll"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 transition duration-150 ease-in-out"/>
                        </th>
                        <th wire:click="sortBy('name')"
                            class="px-6 py-4 text-left text-sm font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700 transition duration-150 ease-in-out">
                            <div class="flex items-center space-x-1">
                                <span>Category Name</span>
                                @if($sortField === 'name')
                                    <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none"
                                         stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M5 15l7-7 7 7"/>
                                    </svg>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-4 text-left text-sm font-medium text-gray-500 uppercase tracking-wider">
                            Parent Category
                        </th>
                        <th class="px-6 py-4 text-center text-sm font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-4 text-center text-sm font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($categories as $category)
                        <tr class="hover:bg-gray-50 transition duration-150 ease-in-out">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" wire:model.live="selectedCategories" value="{{ $category->id }}"
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 transition duration-150 ease-in-out"/>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex items-center" style="margin-left: {{ $category->level * 20 }}px">
                                        @if($category->children->isNotEmpty())
                                            <button x-data="{ expanded: @entangle('expandedCategories') }"
                                                    @click="$wire.toggleCategory({{ $category->id }})"
                                                    class="text-gray-400 hover:text-gray-500 mr-2">
                                                <svg class="w-4 h-4 transition-transform duration-200"
                                                     :class="{ 'transform rotate-90': expanded.includes({{ $category->id }}) }"
                                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </button>
                                            <div class="text-sm font-medium text-gray-900 border-blue-300 border-l-4 pl-3">{{ $category->name }}</div>
                                        @else
                                            <div class="w-4 mr-2"></div>
                                            <div class="text-sm font-medium text-gray-900 ">{{ $category->name }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $category->parent ? $category->parent->name : 'None' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center justify-center">
                                    <button wire:click="toggleStatus({{ $category->id }})"
                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 {{ $category->status ? 'bg-indigo-600' : 'bg-gray-200' }}"
                                            role="switch"
                                            aria-checked="{{ $category->status ? 'true' : 'false' }}">
                                        <span class="sr-only">Toggle status</span>
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition-transform {{ $category->status ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </button>
                                    <label for="status"
                                           class="ml-2 block text-sm text-gray-900">{{ $category->status ? 'Active' : 'Inactive' }}</label>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center justify-center space-x-3">
                                    <button wire:click="editCategory({{ $category->id }})"
                                            class="text-indigo-600 hover:text-indigo-900 transition duration-150 ease-in-out">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button
                                        @click="$dispatch('confirmCategoryDelete', { category: {{ $category->id }} })"
                                        class="text-red-600 hover:text-red-900 transition duration-150 ease-in-out">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center">
                                <div class="flex flex-col items-center justify-center space-y-3">
                                    <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor"
                                         viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                    </svg>
                                    <span class="text-gray-500 text-lg">No categories found</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $categories->links() }}
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    @if($showDeleteModal)
        <div x-data="{show: @entangle('showDeleteModal')}"
            x-show="show"
            class="fixed inset-0 z-50 overflow-hidden"
            x-cloak>
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div
                        class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6"
                        @click.away="$wire.showDeleteModal = false">
                        <div class="absolute right-0 top-0 pr-4 pt-4">
                            <button type="button" wire:click="$set('showDeleteModal', false)"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none transition duration-150 ease-in-out">
                                <span class="sr-only">Close</span>
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                     stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                <h3 class="text-xl font-semibold leading-6 text-gray-900">
                                    Confirm Deletion
                                </h3>
                                <div class="mt-4">
                                    <p class="text-gray-700">
                                        Deleting <span class="font-semibold">{{ $categoryToDelete->name }}</span> Category will also
                                        delete the following categories and products:
                                    </p>
                                    <div class="mt-4">
                                        <h4 class="font-semibold mb-2">Categories to be deleted:</h4>
                                        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full text-sm">
                                                    <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-6 py-4 text-center text-sm font-medium text-gray-500 uppercase tracking-wider">
                                                            ID
                                                        </th>
                                                        <th class="px-6 py-4 text-center text-sm font-medium text-gray-500 uppercase tracking-wider">
                                                            Name
                                                        </th>
                                                    </tr>
                                                    </thead>
                                                    <tbody class="bg-white divide-y divide-gray-200">
                                                    @forelse($categoriesToDelete as $cat)
                                                        <tr class="hover:bg-gray-50 transition duration-150 ease-in-out">
                                                            <td class="px-6 py-4 text-center whitespace-nowrap text-sm text-gray-500">
                                                                {{ $cat->id }}
                                                            </td>
                                                            <td class="px-6 py-4 text-center whitespace-nowrap text-sm text-gray-500">
                                                                {{ $cat->name }}
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <td colspan="2" class="px-2 py-1 text-gray-400 text-center">
                                                            <div class="flex flex-col items-center justify-center space-y-3">
                                                                <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor"
                                                                     viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                          d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                                                </svg>
                                                                <span class="text-gray-500 text-lg">No Categories found</span>
                                                            </div>
                                                        </td>
                                                    @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <h4 class="font-semibold mb-2">Products to be deleted:</h4>
                                        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full text-sm">
                                                    <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-6 py-4 text-center text-sm font-medium text-gray-500 uppercase tracking-wider">
                                                            ID
                                                        </th>
                                                        <th class="px-6 py-4 text-center text-sm font-medium text-gray-500 uppercase tracking-wider">
                                                            Name
                                                        </th>
                                                    </tr>
                                                    </thead>
                                                    <tbody class="bg-white divide-y divide-gray-200">
                                                    @forelse($productsToDelete as $product)
                                                        <tr class="hover:bg-gray-50 transition duration-150 ease-in-out">
                                                            <td class="px-6 py-4 text-center whitespace-nowrap text-sm text-gray-500">
                                                                {{ $product->id }}
                                                            </td>
                                                            <td class="px-6 py-4 text-center whitespace-nowrap text-sm text-gray-500">
                                                                {{ $product->name }}
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="2" class="px-2 py-1 text-gray-400 text-center">
                                                                <div class="flex flex-col items-center justify-center space-y-3">
                                                                    <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor"
                                                                         viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                              d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                                                    </svg>
                                                                    <span class="text-gray-500 text-lg">No Products found</span>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                                        <button type="button"
                                                wire:click="$set('showDeleteModal', false)"
                                                class="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600">
                                            Cancel
                                        </button>
                                        <button wire:click="deleteCategory" wire:loading.attr="disabled"
                                                class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
                                                aria-label="Confirm delete">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Add Category Modal -->
    <div x-data="{ open: @entangle('addModalOpen') }"
         x-show="open"
         x-cloak
         x-init="$watch('open', value => {
             if (value) {
                 document.body.style.overflow = 'hidden';
             } else {
                 document.body.style.overflow = '';
             }
         })"
         class="fixed inset-0 z-50 overflow-hidden"
         @refresh-parent-categories.window="$wire.$refresh()">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div
                    class="relative transform bg-blue-100 rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6"
                    @click.away="$wire.resetAddForm()">
                    <div class="absolute right-0 top-0 pr-4 pt-4">
                        <button type="button" wire:click="resetAddForm"
                                class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none transition duration-150 ease-in-out">
                            <span class="sr-only">Close</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                 stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 class="text-xl font-semibold leading-6 text-gray-900">Add New Category</h3>
                            <div class="mt-4">
                                <form wire:submit.prevent="createCategory" class="space-y-4">
                                    <div>
                                        <label for="name" class="block text-sm font-medium text-gray-700">Category
                                            Name</label>
                                        <input type="text"
                                               wire:model="addForm.name"
                                               id="name"
                                               class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm transition duration-150 ease-in-out">
                                        @error('addForm.name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <!-- Category Parent -->
                                    <div>
                                        <label for="parent_id" class="block text-sm font-medium text-gray-700 mb-1">Parent
                                            Category</label>
                                        <x-category-tree-dropdown
                                            :categories="$this->getCategoryTree()"
                                            :selectedCategory="$addForm['parent_id']"
                                            wireModel="addForm.parent_id"
                                            :is_parent_seletcable="true"
                                        />
                                        @error('addForm.parent_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="flex items-center">
                                        <button type="button"
                                                wire:click="toggleAddFormStatus()"
                                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 {{ $addForm['status'] ? 'bg-indigo-600' : 'bg-gray-200' }}"
                                                role="switch"
                                                aria-checked="{{ $addForm['status'] ? 'true' : 'false' }}">
                                            <span class="sr-only">Toggle status</span>
                                            <span
                                                class="inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition-transform {{ $addForm['status'] ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                        </button>
                                        <label for="status" class="ml-2 block text-sm text-gray-900">Active</label>
                                    </div>

                                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                                        <button type="submit"
                                                class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto transition duration-150 ease-in-out">
                                            Create Category
                                        </button>
                                        <button type="button"
                                                wire:click="$set('addModalOpen', false)"
                                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto transition duration-150 ease-in-out">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div x-data="{ open: @entangle('editModalOpen') }"
         x-show="open"
         x-cloak
         x-init="$watch('open', value => {
             if (value) {
                 document.body.style.overflow = 'hidden';
             } else {
                 document.body.style.overflow = '';
             }
         })"
         class="fixed inset-0 z-50 overflow-hidden"
         @refresh-parent-categories.window="$wire.$refresh()">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div
                    class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6"
                    @click.away="open = false">
                    <div class="absolute right-0 top-0 pr-4 pt-4">
                        <button type="button" wire:click="$set('editModalOpen', false)"
                                class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none transition duration-150 ease-in-out">
                            <span class="sr-only">Close</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                 stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 class="text-xl font-semibold leading-6 text-gray-900">Edit Category</h3>
                            <div class="mt-4">
                                <form wire:submit.prevent="updateCategory" class="space-y-4">
                                    <div>
                                        <label for="edit_name" class="block text-sm font-medium text-gray-700">Category
                                            Name</label>
                                        <input type="text"
                                               wire:model="editForm.name"
                                               id="edit_name"
                                               class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm transition duration-150 ease-in-out">
                                        @error('editForm.name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Category Parent -->
                                    <div>
                                        <label for="parent_id" class="block text-sm font-medium text-gray-700 mb-1">Parent
                                            Category</label>
                                        <x-category-tree-dropdown
                                            :categories="$this->getCategoryTree()"
                                            :selectedCategory="$editForm['parent_id']"
                                            wireModel="editForm.parent_id"
                                            :is_parent_seletcable="true"
                                        />
                                        @error('editForm.parent_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="flex items-center">
                                        <button type="button"
                                                wire:click="toggleEditFormStatus()"
                                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 {{ $editForm['status'] ? 'bg-indigo-600' : 'bg-gray-200' }}"
                                                role="switch"
                                                aria-checked="{{ $editForm['status'] ? 'true' : 'false' }}">
                                            <span class="sr-only">Toggle status</span>
                                            <span
                                                class="inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition-transform {{ $editForm['status'] ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                        </button>
                                        <label for="edit_status" class="ml-2 block text-sm text-gray-900">Active</label>
                                    </div>

                                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                                        <button type="submit"
                                                class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto transition duration-150 ease-in-out">
                                            Update Category
                                        </button>
                                        <button type="button"
                                                wire:click="$set('editModalOpen', false)"
                                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto transition duration-150 ease-in-out">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
