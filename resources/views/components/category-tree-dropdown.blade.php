@props(['categories', 'selectedCategory' => null, 'wireModel' => 'category_id', 'is_parent_seletcable' => false])

<div x-data="{
    open: false,
    search: '',
    selectedCategory: @entangle($wireModel),
    categories: @js($categories),
    filteredCategories: [],
    isParentSelectable: {{ $is_parent_seletcable ? 'true' : 'false' }},
    init() {
        this.filteredCategories = this.categories;
        this.$watch('search', value => {
            this.filterCategories(value);
        });
    },
    filterCategories(search) {
        if (!search) {
            this.filteredCategories = this.categories;
            return;
        }

        const searchLower = search.toLowerCase();
        this.filteredCategories = this.categories.map(category => {
            const matches = category.name.toLowerCase().includes(searchLower);
            const children = category.children.map(child => {
                const childMatches = child.name.toLowerCase().includes(searchLower);
                const grandChildren = child.children.filter(grandChild =>
                    grandChild.name.toLowerCase().includes(searchLower)
                );
                return {
                    ...child,
                    children: grandChildren,
                    show: childMatches || grandChildren.length > 0
                };
            }).filter(child => child.show);

            return {
                ...category,
                children,
                show: matches || children.length > 0
            };
        }).filter(category => category.show);
    },
    getSelectedCategoryName() {
        if (!this.selectedCategory) return 'Select Category';
        const findCategory = (categories, id) => {
            for (const category of categories) {
                if (category.id == id) return category.name;
                if (category.children) {
                    const found = findCategory(category.children, id);
                    if (found) return found;
                }
            }
            return null;
        };
        return findCategory(this.categories, this.selectedCategory) || 'Select Category';
    },
    hasChildren(category) {
        return category.children && category.children.length > 0;
    }
}" class="relative">
    <button @click="open = !open" type="button" class="relative w-full cursor-default rounded-md border border-gray-300 bg-white py-2 pl-3 pr-10 text-left shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 sm:text-sm">
        <span class="block truncate" x-text="getSelectedCategoryName()"></span>
        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
            <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </span>
    </button>

    <div x-show="open" @click.away="open = false" class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
        <div class="sticky top-0 z-10 bg-white px-3 py-2">
            <input x-model="search" type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Search categories...">
        </div>

        <template x-for="category in filteredCategories" :key="category.id">
            <div>
                <div
                    @click="(isParentSelectable || !hasChildren(category)) && (selectedCategory = category.id, open = false)"
                    class="relative py-2 pl-3 pr-9"
                    :class="{
                        'cursor-pointer hover:bg-gray-100': isParentSelectable || !hasChildren(category),
                        'cursor-not-allowed text-gray-400': !isParentSelectable && hasChildren(category),
                        'bg-blue-50': selectedCategory == category.id
                    }"
                >
                    <span class="block truncate" x-text="category.name"></span>
                    <template x-if="hasChildren(category)">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-2 text-gray-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </template>
                </div>

                <template x-if="category.children.length > 0">
                    <div class="pl-4">
                        <template x-for="child in category.children" :key="child.id">
                            <div>
                                <div @click="!hasChildren(child) && (selectedCategory = child.id, open = false)"
                                     class="relative py-2 pl-3 pr-9"
                                     :class="{
                                        'cursor-pointer hover:bg-gray-100': !hasChildren(child),
                                        'cursor-not-allowed text-gray-400': hasChildren(child),
                                        'bg-blue-50': selectedCategory == child.id
                                     }">
                                    <span class="block truncate" x-text="child.name"></span>
                                    <template x-if="hasChildren(child)">
                                        <span class="absolute inset-y-0 right-0 flex items-center pr-2 text-gray-400">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </span>
                                    </template>
                                </div>

                                <template x-if="child.children.length > 0">
                                    <div class="pl-4">
                                        <template x-for="grandChild in child.children" :key="grandChild.id">
                                            <div @click="selectedCategory = grandChild.id; open = false"
                                                 class="relative cursor-pointer select-none py-2 pl-3 pr-9 hover:bg-gray-100"
                                                 :class="{ 'bg-blue-50': selectedCategory == grandChild.id }">
                                                <span class="block truncate" x-text="grandChild.name"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
