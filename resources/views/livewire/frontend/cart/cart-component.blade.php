<div
    x-data="{
        showCart: false,
        loading: false,
        whatsappNumber: @js(App\Facades\Settings::get('whatsapp_number', '905353402539')),
        minOrderAmount: 1000,
        toggleCart() {
            this.showCart = !this.showCart;
            this.updateBodyScroll();
        },
        updateBodyScroll() {
            document.body.classList.toggle('overflow-hidden', this.showCart);
        },
        composeWhatsAppMessage() {
            const CRLF = '\r\n';
            const lines = [];
            lines.push('*Yeni Sipariş*');
            lines.push('----------------');
            this.$store.cart.items.forEach((item, index) => {
                const lineTotal = (Number(item.price) * Number(item.quantity));
                console.log(item);
                lines.push(`${index + 1}. *${item.name}*: `);
                lines.push(`       Fiyat: ${item.quantity} * ${Number(Math.trunc(item.price))} TL  = ${Math.trunc(lineTotal)} TL`);
                lines.push('');
            });
            lines.push('----------------');
            lines.push(`*Ara Toplam:* ${Math.trunc(this.$store.cart.subtotal)} TL`);
            return lines.join(CRLF);
        },
        async orderViaWhatsApp() {
            if (this.$store.cart.items.length === 0) return;

            // Check minimum order amount
            if (this.$store.cart.subtotal < this.minOrderAmount) {
                        window.Livewire.dispatch('notify', [{
                            type: 'alert-danger',
                            message: `Minimum sipariş tutarı ${this.minOrderAmount} TL'dir. Lütfen sepetinizi ${(this.minOrderAmount - this.$store.cart.subtotal).toFixed(2)} TL daha ekleyin.`,
                            sec: 4000
                        }]);
                return;
            }

            try {
                this.loading = true;
                await this.$store.cart.syncWithServer();
                const msg = this.composeWhatsAppMessage();
                const url = 'https://wa.me/' + this.whatsappNumber + '?text=' + encodeURIComponent(msg);
                window.open(url, '_blank');
                this.$store.cart.clear();
            } finally {
                setTimeout(() => { this.loading = false; }, 800);
            }
        },
        init() {
            Livewire.on('cart-updated', () => {
            });
        }
    }"
>
    <!-- Cart Icon -->
    <div class="relative flex items-center gap-x-2 hover:bg-gray-100 p-1 rounded-lg">
        <button
            @click="toggleCart()"
            class="pt-1 text-gray-700 hover:text-blue-600 rounded-xl transition-all duration-200 relative group h-11 w-11 flex items-center justify-center">
            <svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                 viewBox="0 0 1312.4 637.2" style="enable-background:new 0 0 1312.4 637.2;height:100%;width: 100%;" xml:space="preserve">
<style type="text/css">
    .st0{fill:#282828;}
    .st1{fill:none;stroke:#2563EB;stroke-width:42.46;stroke-linecap:round;stroke-miterlimit:10;}
    .st2{fill:none;stroke:#282828;stroke-width:64;stroke-linecap:round;stroke-linejoin:bevel;}
</style>
                <path class="st0" d="M173.9,16.8l0.3,0.2c0,0,0.1,0.2,0.2,0.3l0.3,0.3l1.2,1.6l2.9,3.6c2,2.5,4.1,5.1,6.2,7.7
	c4.3,5.2,8.7,10.4,13.2,15.6c9,10.4,18.4,20.5,27.9,30.5c19.1,19.9,39,38.9,59.6,56.9c41.2,35.9,85.2,67.7,131.4,93.8
	c45.5,26,94.1,46.2,144.6,60.1c49.9,13.5,101.3,20.3,153,20.2c51.6-0.1,102.9-7,152.7-20.6c12.4-3.5,24.8-7.2,37-11.5
	s24.4-8.9,36.4-13.9c6-2.6,12-5.1,17.9-7.9c6-2.6,11.9-5.5,17.8-8.4c11.8-5.8,23.4-12,34.9-18.5c46.1-26.1,89.9-57.8,131-93.7
	c20.6-17.9,40.5-36.9,59.7-56.8c4.8-5,9.5-10,14.2-15s9.3-10.2,13.8-15.4s9-10.4,13.3-15.5c2.2-2.6,4.3-5.2,6.3-7.7l2.9-3.6l1.3-1.6
	l0.3-0.3c0.1-0.1,0.2-0.3,0.2-0.3l0.3-0.2l12.6-8.7c13-9,30.9-5.7,39.8,7.4c5.3,7.7,6.5,17.5,3.2,26.2l-1.5,4l-1.2,3.3
	c-0.8,2-1.6,4-2.4,5.8c-1.6,3.8-3.2,7.4-4.9,11c-3.3,7.1-6.8,14-10.3,20.9c-7.2,13.6-14.9,27-23,40s-16.8,25.7-25.8,38.2
	c-4.5,6.2-9.2,12.4-13.9,18.4c-4.8,6.1-9.6,12.1-14.5,18c-39.9,48.1-86,90.7-137.1,126.6c-52.3,36.8-110,65.5-170.9,85
	c-7.7,2.3-15.4,4.8-23.2,6.8c-7.8,2.2-15.6,4.1-23.4,6c-3.9,0.9-7.9,1.7-11.8,2.6l-5.9,1.3l-6,1.1l-11.9,2.1l-12,1.9
	c-2,0.3-4,0.6-6,0.9l-6,0.8c-4,0.5-8,1.1-12,1.5l-12,1.2c-2,0.2-4,0.4-6,0.5l-6,0.4c-16.1,1.3-32.2,1.6-48.2,1.7
	c-16.1-0.2-32.1-0.7-48.2-2.1c-8-0.5-16-1.5-24-2.3c-4-0.5-8-1.1-12-1.6l-6-0.8c-2-0.3-4-0.6-6-1l-11.9-2c-4-0.7-7.9-1.5-11.9-2.3
	l-5.9-1.2l-5.9-1.3c-3.9-0.9-7.9-1.7-11.8-2.7c-7.8-2-15.6-3.9-23.4-6.1c-7.8-2.1-15.4-4.6-23.1-6.9
	c-60.7-19.8-118.1-48.5-170.3-85.2c-12.9-9-25.5-18.5-37.6-28.3c-12.2-9.8-24-20-35.5-30.6c-22.8-21-44.2-43.4-64.1-67.2
	c-19.8-23.5-38-48.3-54.6-74.2c-8.3-12.9-16.1-26.2-23.4-39.7c-3.6-6.8-7.2-13.7-10.6-20.8c-1.7-3.6-3.3-7.2-5-10.9l-1.2-2.8l-1.2-3
	l-1.3-3.2l-1.5-4c-5.7-14.8,1.7-31.5,16.5-37.2c9-3.5,19.2-2.2,27.1,3.5L173.9,16.8z"/>
                <circle class="st1" cx="523.4" cy="549.9" r="66"/>
                <circle class="st1" cx="909.7" cy="549.9" r="66"/>
                <line class="st2" x1="146.1" y1="29.3" x2="29.3" y2="29.3"/>
</svg>

            <div
                x-show="$store.cart.itemsCount > 0"
                x-text="$store.cart.itemsCount"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-50"
                x-transition:enter-end="opacity-100 scale-100"
                class="absolute top-[-7%] right-[21%] bg-blue-500 text-white text-xs font-bold rounded-full h-5 min-w-[1.25rem] px-1 flex items-center justify-center group-hover:bg-blue-600 transition-colors duration-200 z-50"
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
             class="fixed inset-0 bg-gray-900/80"
             @click="toggleCart()"
             aria-hidden="true">
        </div>

        <div
             x-show="showCart"
             class="fixed inset-y-0 right-0 max-w-full flex bg-white shadow-lg"
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
                            <h2 class="text-lg font-medium text-gray-900">{{__('cart.shopping_cart')}}</h2>
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
                                                    <button @click="$store.cart.removeItem(item.product_id)" class="font-medium text-indigo-600 hover:text-indigo-500">{{__('cart.remove')}}</button>
                                                </div>
                                            </div>
                                        </li>
                                    </template>
                                    <li x-show="$store.cart.items.length === 0" class="py-6">
                                        <p class="text-gray-500 text-center">{{__('cart.your_cart_is_empty')}}</p>
                                    </li>
                                </ul>


                            </div>
                        </div>
                    </div>

                    <div x-show="$store.cart.items.length > 0" class="border-t border-gray-200 py-6 px-4 sm:px-6">
                        <div class="flex justify-between text-base font-medium text-gray-900">
                            <p>{{__('cart.subtotal')}}</p>
                            <p x-text="`${$store.cart.subtotal.toFixed(2)} TL`"></p>
                        </div>
                        <p class="mt-0.5 text-sm text-gray-500">{{__('cart.shipping_and_taxes_calculated_at_checkout')}}</p>

                        <!-- Minimum Order Warning -->
                        <div x-show="$store.cart.subtotal < minOrderAmount" class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-800">
                                        Minimum sipariş tutarı <span class="font-semibold">1000 TL</span>'dir.
                                        <span x-text="`${(minOrderAmount - $store.cart.subtotal).toFixed(2)} TL`"></span> daha ekleyin.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <button
                                :disabled="loading || $store.cart.subtotal < minOrderAmount"
                                @click.prevent="orderViaWhatsApp()"
                                class="flex justify-center items-center w-full px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white transition-colors duration-200"
                                :class="$store.cart.subtotal < minOrderAmount ? 'bg-gray-400 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700'">
                                <span x-show="!loading && $store.cart.subtotal >= minOrderAmount">{{__('cart.order_now')}}</span>
                                <span x-show="!loading && $store.cart.subtotal < minOrderAmount">Minimum 1000 TL gerekli</span>
                                <span x-show="loading">{{__('cart.preparing')}}...</span>
                            </button>
                        </div>
                        <div class="mt-6 flex justify-center text-sm text-center text-gray-500">
                            <button @click="$store.cart.clear()"
                                    class="text-indigo-600 font-medium hover:text-indigo-500">
                                {{__('cart.clear_cart')}}<span aria-hidden="true"> &rarr;</span>
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
