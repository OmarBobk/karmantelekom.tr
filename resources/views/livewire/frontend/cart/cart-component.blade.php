<div
    x-data="{
        showCart: false,
        toggleCart() {
            this.showCart = !this.showCart;
            this.updateBodyScroll();
        },
        updateBodyScroll() {
            document.body.classList.toggle('overflow-hidden', this.showCart);
        },
        init() {
            Livewire.on('cart-updated', () => {
{{--                this.$refresh;--}}
            });
        }
    }"
>
    <!-- Cart Icon -->
    <div class="relative flex items-center gap-x-2">
        <button
            @click="toggleCart()"
            class="p-2 text-gray-700 hover:text-blue-600 hover:bg-gray-100 rounded-xl transition-all duration-200 relative group h-11 w-11 flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shopping-cart-icon lucide-shopping-cart"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
            <div
                x-show="$store.cart.itemsCount > 0"
                x-text="$store.cart.itemsCount"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-50"
                x-transition:enter-end="opacity-100 scale-100"
                class="absolute -top-1 -right-1 bg-blue-500 text-white text-xs font-bold rounded-full h-5 min-w-[1.25rem] px-1 flex items-center justify-center group-hover:bg-blue-600 transition-colors duration-200 z-50"
            ></div>
        </button>
    </div>

    <!-- Cart Modal -->
    <div x-show="showCart"
         class="fixed inset-0 z-50 overflow-hidden"
         style="display: none;">

        <!-- Backdrop -->
        <div
             x-show="showCart"
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-900/80 h-screen"
             @click="toggleCart()"
             aria-hidden="true">
        </div>

        <div
             x-show="showCart"
             class="fixed inset-y-0 right-0 max-w-full flex bg-white shadow-lg h-screen"
             x-transition:enter="transition ease-in-out duration-300 transform"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in-out duration-300 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
        >
            <div class="w-screen max-w-md">
                <div class="h-full flex flex-col bg-white shadow-xl">
                    <div class="flex-1 py-6 overflow-y-auto px-4 sm:px-6">
                        <div class="flex items-start justify-between">
                            <h2 class="text-lg font-medium text-gray-900">Shopping Cart</h2>
                            <button @click="toggleCart()" class="text-gray-400 hover:text-gray-500">
                                <span class="sr-only">Close panel</span>
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="mt-8">
                            <div class="flow-root">

                                <ul role="list" class="-my-6 divide-y divide-gray-200">
                                    <template x-for="item in $store.cart.items" :key="item.product_id">
                                        <li class="py-6 flex items-center">
                                            <div class="flex-shrink-0 w-24 h-24 border border-gray-200 rounded-md overflow-hidden">
                                                <img :src="'/storage/' + item.image" :alt="item.name" class="w-full h-full object-center object-cover">
                                            </div>

                                            <div class="ml-4 flex-1 flex flex-col gap-2">
                                                <div class="flex justify-between text-base font-medium text-gray-900">
                                                    <div class="flex-1 line-clamp-3">
                                                        <span class="text-base font-medium text-gray-900" x-text="item.name"></span>
                                                        <span class="text-sm text-gray-700" x-text="item.description"></span>
                                                    </div>
                                                    <div>
                                                        <p class="ml-4" x-text="`${item.price} TL`"></p>
                                                    </div>
                                                </div>
                                                <div class="flex-1 flex items-end justify-between text-sm">
                                                    <div class="flex items-center">
                                                        <button @click="$store.cart.updateQuantity(item.product_id, item.quantity - 1)"
                                                                class="text-gray-500 hover:text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md p-1 transition-colors duration-200">
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h12" />
                                                            </svg>
                                                        </button>
                                                        <span class="mx-2" x-text="item.quantity"></span>
                                                        <button @click="$store.cart.updateQuantity(item.product_id, item.quantity + 1)"
                                                                class="text-gray-500 hover:text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md p-1 transition-colors duration-200">
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <button @click="$store.cart.removeItem(item.product_id)" class="font-medium text-indigo-600 hover:text-indigo-500">Remove</button>
                                                </div>
                                            </div>
                                        </li>
                                    </template>
                                    <li x-show="$store.cart.items.length === 0" class="py-6">
                                        <p class="text-gray-500 text-center">Your cart is empty</p>
                                    </li>
                                </ul>


                            </div>
                        </div>
                    </div>

                    <div x-show="$store.cart.items.length > 0" class="border-t border-gray-200 py-6 px-4 sm:px-6">
                        <div class="flex justify-between text-base font-medium text-gray-900">
                            <p>Subtotal</p>
                            <p x-text="`${$store.cart.subtotal.toFixed(2)} TL`"></p>
                        </div>
                        <p class="mt-0.5 text-sm text-gray-500">Shipping and taxes calculated at checkout.</p>
                        <div class="mt-6">
                            <button
                                x-data="{loading: false}"
                                :disabled="loading"
                                @click.prevent="loading = true; $store.cart.syncWithServer().then(() => window.location.href = '/checkout')"
                                class="flex justify-center items-center w-full px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                <span x-show="!loading">Checkout</span>
                                <span x-show="loading">Syncing...</span>
                            </button>
                        </div>
                        <div class="mt-6 flex justify-center text-sm text-center text-gray-500">
                            <button @click="$store.cart.clear()"
                                    class="text-indigo-600 font-medium hover:text-indigo-500">
                                Clear Cart<span aria-hidden="true"> &rarr;</span>
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
