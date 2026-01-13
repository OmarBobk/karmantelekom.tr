    <!DOCTYPE html>
    <html dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}"
          lang="{{ strtolower(str_replace('_', '-', app()->getLocale())) }}"
          class="light" style="border:none">
        <head>
            <meta name="cart-context"
                  content="{{ request()->routeIs('catalog*') ? 'catalog' : 'products' }}">
            <!-- <script type="text/javascript">
                var _iub = _iub || [];
                _iub.csConfiguration = {"siteId":4147810,"cookiePolicyId":69197729,"lang":"en","storage":{"useSiteId":true}};
            </script>
            <script type="text/javascript" src="https://cs.iubenda.com/autoblocking/4147810.js"></script>
            <script type="text/javascript" src="//cdn.iubenda.com/cs/gpp/stub.js"></script>
            <script type="text/javascript" src="//cdn.iubenda.com/cs/iubenda_cs.js" charset="UTF-8" async></script> -->

            <!-- Google tag (gtag.js) -->
            <script async src="https://www.googletagmanager.com/gtag/js?id=G-YMHHC2JM4D"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());

                gtag('config', 'G-YMHHC2JM4D');
            </script>

            <link rel="icon" type="image/png" href="{{ asset('assets/images/karmantelekom_logo.png') }}">
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="csrf-token" content="{{ csrf_token() }}">
            <!-- Add user info to layout -->
            <meta name="user-id" content="{{ auth()->id() }}">
            <meta name="user-role" content="{{ auth()->check() ? (auth()->user()->roles->first()?->name ?? 'user') : 'user' }}">
            <title>{{ $title ?? config('app.name') }}</title>
            @vite(['resources/css/app.css', 'resources/js/app.js'])

            <!-- Alpine Cart Store -->
            <script>
                // setInterval(() => {
                //     fetch('/keep-alive');
                // }, 5 * 60 * 1000);
                document.addEventListener('alpine:init', () => {
                    Alpine.store('cart', {
                        items: Alpine.$persist([]).as('cart_items'),
                        itemsCount: 0,
                        subtotal: 0,
                        syncTimeout: null,

                        init() {
                            this.updateTotals();

                            // Listen for currency changes
                            window.addEventListener('currency-switched', () => {
                                this.syncWithServer();
                            });

                            // Listen for the checkout event from CheckoutComponent to clear the cart.
                            window.addEventListener('checkout', () => {
                                this.clear();
                            });

                            // Listen for cart items from server
                            window.addEventListener('cart-items-from-server', (event) => {
                                this.loadItemsFromServer(event.detail[0]);
                            });

                            // Initial sync with server after a short delay to ensure Livewire is loaded
                            setTimeout(() => {
                                this.syncWithServer();
                            }, 500);
                        },

                        loadItemsFromServer(items) {
                            console.log('Loading items from server:', items);
                            this.items = items;
                            this.updateTotals();
                        },

                        updateTotals() {
                            this.itemsCount = this.items.reduce((sum, item) => sum + item.quantity ,0);
                            this.subtotal = this.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                        },

                        addItem(product, quantity = 1, options = {}) {
                            const { catalog = false } = options;
                            const existingItem = this.items.find(item => item.product_id === product.id);

                            if (existingItem) {
                                existingItem.quantity += quantity;
                            } else {
                                this.items.push({
                                    price: product.prices[0].base_price,
                                    quantity: quantity,
                                    subtotal: this.subtotal,
                                    product_id: product.id,

                                    name: product.translated_name,
                                    description: product.translated_description,
                                    image: product.images[0].image_url,

                                    context: catalog ? 'catalog' : 'products'
                                });
                            }
                            window.Livewire.dispatch('notify', [{
                                type: 'alert-success',
                                message: `${product.translated_name} {{__('alert.added_to_cart')}}`,
                                sec: 2000
                            }]);

                            this.updateTotals();
                            this.scheduleSync();
                        },

                        updateQuantity(productId, quantity) {
                            const item = this.items.find(item => item.product_id === productId);
                            if (item) {
                                if (quantity < 1) {
                                    this.removeItem(productId);
                                } else {
                                    item.quantity = quantity;
                                    this.updateTotals();
                                    this.scheduleSync();
                                }
                            }
                        },

                        removeItem(productId) {
                            this.items = this.items.filter(item => item.product_id !== productId);
                            this.updateTotals();

                            console.log('remove item', productId);

                            let id = productId
                            window.Livewire.dispatch('remove-item', {id});
                        },

                        clear() {
                            this.items = [];
                            this.updateTotals();

                            // this.syncWithServer();

                            window.Livewire.dispatch('clear-cart')
                        },

                        scheduleSync() {
                            if (this.syncTimeout) {
                                clearTimeout(this.syncTimeout)
                            }

                            this.syncTimeout = setTimeout(() => {
                                this.syncWithServer();
                            }, 1000)
                        },

                        async syncWithServer() {
                            if(this.syncTimeout) {
                                clearTimeout(this.syncTimeout);
                            }

                            try {
                                const items = this.items.map(item => ({
                                    product_id: item.product_id,
                                    quantity: item.quantity
                                }));

                                await window.Livewire.dispatch('sync-cart', { items })
                            } catch (error) {
                                console.error('Error syncing cart:', error);
                            }

                        }
                    })
                });
            </script>
        </head>
        <body
            class="min-h-screen bg-gray-50"
            data-user="{{auth()->check() ? auth()->id() : ''}}"
            data-role="{{auth()->user()?->roles()->first()->name ?? ''}}"
        >
        <script type="text/javascript">window.iyz = { token: '1c0c8fca-4009-4523-a6eb-10ebfd34b90b', position: 'bottomRight', ideaSoft: false, pwi: true};</script> <script type="text/javascript" src="https://static.iyzipay.com/buyer-protection/buyer-protection.js"></script>
            <!-- Notification Component -->
            <div
            x-data="{
                    alerts: [],
                    add(message) {
                        if (!message[0] || !message[0].type || !message[0].message) {
                            console.error('Invalid alert format:', message);
                            console.error('Invalid alert format:', message[0].type);
                            console.error('Invalid alert format:', message[0].message);
                            return;
                        }

                        const alert = {
                            id: Date.now(),
                            type: message[0].type,
                            message: message[0].message,
                            sec: message[0].sec || 3000
                        };

                        this.alerts.push(alert);

                        // Auto-remove alert after 3 seconds
                        setTimeout(() => {
                            this.remove(alert.id);
                        }, alert.sec);
                    },
                    remove(id) {
                        this.alerts = this.alerts.filter(alert => alert.id !== id);
                    }
                }"
            @notify.window="add($event.detail)"
            class="fixed top-4 right-4 z-[99999] space-y-2 w-full max-w-sm"
        >
            <template x-for="alert in alerts" :key="alert.id">
                <div
                    x-show="true"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="transform translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
                    x-transition:enter-end="transform translate-y-0 opacity-100 sm:translate-x-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    :class="alert.type"
                    class="shadow-lg rounded-lg pointer-events-auto alert"
                >
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 mr-3">
                                <p class="text-sm font-medium text-white [&>*]:text-white" x-html="alert.message"></p>
                            </div>
                            <div class="flex flex-shrink-0">
                                <button
                                    @click="remove(alert.id)"
                                    class="inline-flex text-white hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-white rounded-md"
                                >
                                    <span class="sr-only">Close</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

            <header
                class="fixed top-0 left-0 right-0 z-50 transition-all duration-300"
                x-data="{ isScrolled: false }"
                x-init="window.addEventListener('scroll', () => {
            isScrolled = window.scrollY > 10;
        })"
                :class="isScrolled
            ? 'fixed top-0 left-0 right-0 z-50 transition-all duration-300 bg-white shadow-lg border-b border-gray-200'
            : 'fixed top-0 left-0 right-0 z-50 transition-all duration-300 bg-white'"
            >
                <livewire:frontend.partials.header-component />

                <livewire:frontend.partials.navbar-component />
            </header>

            <main class="pt-[13.25rem] sm:pt-[9.5rem]">
                {{ $slot }}
            </main>


            <footer>
                <livewire:frontend.partials.footer-component />
            </footer>

            <!-- Notification Component -->
    {{--        <x-notification />--}}

            <!-- Product Modal Component - Global -->
            <livewire:frontend.product-modal-component />

            @stack('scripts')

            <script>
                window.addEventListener('languageChanged', () => {
                    location.reload();
                });
            </script>
        </body>
    </html>


