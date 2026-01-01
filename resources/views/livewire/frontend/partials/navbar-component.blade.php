<div>
    <nav
        x-data="categoryNav()"
        x-init="init()"
        class="border-b pb-1 border-gray-100 bg-white"
    >
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="relative">
                <!-- Left button (desktop only) -->
                <button
                    type="button"
                    class="hidden lg:flex absolute left-0 top-1/2 -translate-y-1/2 z-20 h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-white shadow-sm hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed"
                    @click="scrollBy(-320)"
                    :disabled="atStart"
                    aria-label="Scroll left"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M12.78 15.53a.75.75 0 0 1-1.06 0l-5-5a.75.75 0 0 1 0-1.06l5-5a.75.75 0 1 1 1.06 1.06L8.31 10l4.47 4.47a.75.75 0 0 1 0 1.06z" clip-rule="evenodd"/>
                    </svg>
                </button>

                <!-- Right button (desktop only) -->
                <button
                    type="button"
                    class="hidden lg:flex absolute right-[-2rem] top-1/2 -translate-y-1/2 z-20 h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-white shadow-sm hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed"
                    @click="scrollBy(320)"
                    :disabled="atEnd"
                    aria-label="Scroll right"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M7.22 4.47a.75.75 0 0 1 1.06 0l5 5a.75.75 0 0 1 0 1.06l-5 5a.75.75 0 1 1-1.06-1.06L11.69 10 7.22 5.53a.75.75 0 0 1 0-1.06z" clip-rule="evenodd"/>
                    </svg>
                </button>

                <!-- Scroll container -->
                <div
                    x-ref="scroller"
                    @scroll="update()"
                    class="overflow-x-auto scrollbar-hide"
                >
                    <!-- Add side padding on desktop so arrows don't overlap items -->
                    <div class="flex h-12 items-center gap-3 whitespace-nowrap lg:px-12">
                        <a
                            href="{{ route((($isCatalog) ? 'catalog' : 'products'), ['category' => 'all']) }}"
                            class="inline-flex items-center rounded-xl border border-gray-200 px-4 py-2 text-md
                            font-medium text-gray-700 hover:text-blue-600 hover:border-blue-200 hover:bg-blue-50 transition
                            {{ request()->routeIs('products') && request('category') === 'all'
                                ? 'border-blue-300 bg-blue-500 text-white'
                                : 'border-gray-200 text-gray-700 hover:text-blue-600 hover:border-blue-200 hover:bg-blue-50'
                            }}
                            "
                        >
                            {{__('main.all')}}
                        </a>

                        @foreach($categories as $category)
                            @php
                                $isParentActive =
                                    $this->currentCategory &&
                                    (
                                        $this->currentCategory->id === $category->id ||
                                        $this->currentCategory->parent_id === $category->id
                                    );
                            @endphp
                            <div
                                x-data="dropdownFixed()"
                                class="relative"
                                @keydown.escape.window="open = false"
                            >
                                <button
                                    x-ref="btn"
                                    type="button"
                                    @click="toggle()"
                                    class="inline-flex items-center gap-1 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold  transition
                                    {{ $isParentActive
                                        ? 'border-blue-300 bg-blue-500 text-white'
                                        : 'border-gray-200 text-gray-700 hover:text-blue-600 hover:border-blue-200 hover:bg-blue-50'
                                    }}
                                    "
                                >
                                    <span class="truncate max-w-[160px] font-medium text-md">
                                        {{ $category->translated_name }}
                                    </span>

                                    @if($category->children->isNotEmpty())
                                        <svg class="h-5 w-5  shrink-0
                                        {{ $isParentActive
                                            ? 'border-blue-300 bg-blue-500 text-gray-100'
                                            : 'border-gray-200 text-gray-400 hover:text-blue-600 hover:border-blue-200 hover:bg-blue-50'
                                        }}
                                        " xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.23 8.27a.75.75 0 010-1.06z" clip-rule="evenodd" />
                                        </svg>
                                    @endif
                                </button>

                                @if($category->children->isNotEmpty())
                                    <div
                                        x-show="open"
                                        x-cloak
                                        x-transition.opacity
                                        @click.away="open = false"
                                        class="fixed z-50 w-60 rounded-xl bg-white shadow-lg ring-1 ring-black/5 overflow-hidden"
                                        :style="`top:${top}px; left:${left}px;`"
                                    >
                                        <div class="py-2">
                                            @foreach($category->children as $child)
                                                <a
                                                    href="{{ route((($isCatalog) ? 'catalog' : 'products'), ['category' => $child->slug]) }}"
                                                    class="block px-4 py-2 text-sm hover:bg-gray-50 hover:text-blue-600 font-medium text-gray-700 text-md"
                                                >
                                                    {{ $child->translated_name }}
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Optional fade edges (desktop only) -->
                <div class="pointer-events-none hidden lg:block absolute inset-y-0 left-0 w-10 bg-gradient-to-r from-white to-transparent"></div>
                <div class="pointer-events-none hidden lg:block absolute inset-y-0 right-[-2rem] w-10 bg-gradient-to-l from-white to-transparent"></div>
            </div>
        </div>

        <script>
            function categoryNav() {
                return {
                    atStart: true,
                    atEnd: false,

                    init() {
                        this.update();
                        // keep buttons correct on resize
                        window.addEventListener('resize', () => this.update());
                    },

                    scrollBy(px) {
                        this.$refs.scroller.scrollBy({ left: px, behavior: 'smooth' });
                        // update after scroll animation starts
                        setTimeout(() => this.update(), 80);
                    },

                    update() {
                        const el = this.$refs.scroller;
                        const max = el.scrollWidth - el.clientWidth;
                        // small tolerance for float rounding
                        this.atStart = el.scrollLeft <= 2;
                        this.atEnd = el.scrollLeft >= (max - 2);
                    }
                }
            }

            function dropdownFixed() {
                return {
                    open: false,
                    top: 0,
                    left: 0,

                    toggle() {
                        if (this.open) {
                            this.open = false;
                            return;
                        }

                        // calculate dropdown position
                        const r = this.$refs.btn.getBoundingClientRect();
                        this.top = r.bottom + 8;

                        // keep dropdown inside viewport (no overflow on right)
                        const dropdownWidth = 240; // w-60 => 15rem => 240px
                        const padding = 12;
                        const maxLeft = window.innerWidth - dropdownWidth - padding;
                        this.left = Math.min(r.left, maxLeft);

                        this.open = true;
                    }
                }
            }
        </script>
    </nav>
</div>
