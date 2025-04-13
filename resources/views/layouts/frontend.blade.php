<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      class="light" style="border:none">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Alpine Cart Store -->
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.store('cart', {
                    items: Alpine.$persist([]).as('cart_items'),
                    syncedUserId: Alpine.$persist(null).as('is_user_synced'),

                    // The counter that is on the shopping cart icon you can find it on the header component.
                    count: 0,
                    total: 0,

                    init() {
                        this.updateTotals();

                        // Listen for currency changes
                        window.addEventListener('currency-switched', () => {
                            this.syncWithServer();
                        });



                        this.filterDependingOnRole();

                        const userId = document.body.dataset.user;

                        // If no user is logged in but a synced user is stored, clear it
                        if (!userId && this.syncedUserId !== null) {
                            this.syncedUserId = null;
                        }

                        // Sync Only if user logged in and not synced yet.
                        if (this.shouldSync(userId)) {

                            // Initial sync with server after a short delay to ensure Livewire is loaded
                            setTimeout(() => {
                                this.syncWithServer();
                            }, 3000);

                            this.syncedUserId = userId;

                        }



                    },

                    updateTotals() {
                        this.count = this.items.reduce((sum, item) => sum + item.quantity, 0);
                        this.total = this.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                    },

                    addItem(product, quantity = 1) {
                        const existingItem = this.items.find(item => item.product_id === product.id);

                        if (existingItem) {
                            existingItem.quantity += quantity;
                        } else {
                            this.items.push({
                                product_id: product.id,
                                name: product.name,
                                description: product.description,
                                price: product.prices[0].base_price,
                                currency: product.prices[0].currency.code,
                                quantity: quantity,
                                image: product.images[0].image_url
                            });
                        }

                        this.updateTotals();
                        // this.scheduleSync();
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
                        this.scheduleSync();
                    },

                    clear() {
                        this.items = [];
                        this.updateTotals();
                        // this.syncWithServer();
                    },

                    filterDependingOnRole() {},

                    shouldSync(userId) {
                        // Sync only if:
                        //   - logged in
                        //   - Cart items
                        //   - Sync hasn't happened yet for this user.

                        return userId && this.items.length > 0 && this.syncedUserId !== userId;
                    },

                    syncTimeout: null,
                    scheduleSync() {
                        if (this.syncTimeout) {
                            clearTimeout(this.syncTimeout);
                        }

                        this.syncTimeout = setTimeout(() => {
                            this.syncWithServer();
                        }, 1000); // Sync after 1 second of inactivity (reduced from 3s)
                    },

                    async syncWithServer() {
                        if (this.syncTimeout) {
                            clearTimeout(this.syncTimeout);
                        }

                        try {
                            console.log('syncing...')
                            const items = this.items.map(item => ({
                                product_id: item.product_id,
                                quantity: item.quantity
                            }));

                            await window.Livewire.dispatch('sync-cart', {items});
                        } catch (error) {
                            console.error('Failed to sync cart with server:', error);
                        }
                    },

                    logout() {
                        this.syncedUserId = null;

                        fetch('/logout', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            }
                        }).then(() => window.location.href = '/')
                    }
                });
            });
        </script>
    </head>
    <body
        class="min-h-screen bg-gray-50"
        data-user="{{auth()->check() ? auth()->id() : ''}}"
        data-role="{{auth()->user()?->roles()->first()->name ?? ''}}"
    >
        <header>
            <livewire:frontend.partials.header-component />
        </header>
        <main>
            {{ $slot }}
        </main>
        <footer>
            <livewire:frontend.partials.footer-component />
        </footer>

        <!-- Notification Component -->
        <x-notification />

        @stack('scripts')
    </body>
</html>


