<div
    x-init="@this.on('lock-scroll', () => { document.body.style.overflow = 'hidden'; })
             @this.on('unlock-scroll', () => { document.body.style.overflow = 'auto'; })"
>
    <!-- Cart Icon -->
    <div class="relative flex items-center gap-x-2">
        <button
            @click="if (!cartButtonDisabled) { $wire.toggleCart(); cartButtonDisabled = true; setTimeout(() => { cartButtonDisabled = false; }, 250); }"
            :disabled="cartButtonDisabled"
            x-data="{ cartButtonDisabled: false }"
            class="p-2.5 text-gray-700 hover:text-blue-600 hover:bg-gray-100 rounded-xl transition-all duration-200 relative group h-11 w-11 flex items-center justify-center">
            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
            </svg>
            @if($this->cartCount > 0)
                <div
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-50"
                     x-transition:enter-end="opacity-100 scale-100"
                     class="absolute -top-1 -right-1 bg-blue-500 text-white text-xs font-bold rounded-full h-5 min-w-[1.25rem] px-1 flex items-center justify-center group-hover:bg-blue-600 transition-colors duration-200 z-50"
                     >{{ $this->cartCount }}</div>
            @endif
        </button>
        <span @click="$wire.toggleCart()"
              class="hidden sm:block text-sm text-gray-700 cursor-pointer hover:text-gray-900">Cart</span>
    </div>

    <!-- Cart Modal -->
    <div x-show="$wire.showCart"
         class="fixed inset-0 z-50 overflow-hidden"
         style="display: none;">

        <!-- Backdrop -->
        <div
             x-show="$wire.showCart"
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-900/80"
             @click="$wire.toggleCart()"
             aria-hidden="true">
        </div>

        <div
             x-show="$wire.showCart"
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
                            <h2 class="text-lg font-medium text-gray-900">Shopping Cart</h2>
                            <button @click="$wire.toggleCart()" class="text-gray-400 hover:text-gray-500">
                                <span class="sr-only">Close panel</span>
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="mt-8">
                            <div class="flow-root">
                                <ul role="list" class="-my-6 divide-y divide-gray-200">
                                    @forelse($this->cartItems as $item)
                                        <li class="py-6 flex">
                                            <div class="flex-shrink-0 w-24 h-24 border border-gray-200 rounded-md overflow-hidden">
                                                <img src="{{ Storage::url($item->product->images->where('is_primary', true)->first()->image_url)}}" alt="{{ $item->product->name }}" class="w-full h-full object-center object-cover">
                                            </div>

                                            <div class="ml-4 flex-1 flex flex-col">
                                                <div class="flex justify-between text-base font-medium text-gray-900">
                                                    <div class="flex-1 line-clamp-3">
                                                        <span class="text-base font-medium text-gray-900">{{$item->product->name}}</span>
                                                        <span class="text-sm text-gray-500">{{$item->product->description}}</span>
                                                    </div>
                                                    <div>
                                                        <p class="ml-4">{{ number_format($item->total, 2) }} {{ $item->currency }}</p>
                                                    </div>
                                                </div>
                                                <div class="flex-1 flex items-end justify-between text-sm">
                                                    <div class="flex items-center">
                                                        <button wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity - 1 }})" class="text-gray-500 hover:text-gray-700">-</button>
                                                        <span class="mx-2">{{ $item->quantity }}</span>
                                                        <button wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity + 1 }})" class="text-gray-500 hover:text-gray-700">+</button>
                                                    </div>
                                                    <button wire:click="removeFromCart({{ $item->id }})" class="font-medium text-indigo-600 hover:text-indigo-500">Remove</button>
                                                </div>
                                            </div>
                                        </li>
                                    @empty
                                        <li class="py-6">
                                            <p class="text-gray-500 text-center">Your cart is empty</p>
                                        </li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    </div>

                    @if($this->cartCount > 0)
                        <div class="border-t border-gray-200 py-6 px-4 sm:px-6">
                            <div class="flex justify-between text-base font-medium text-gray-900">
                                <p>Subtotal</p>
                                <p>{{ number_format($this->cartTotal, 2) }} {{ $this->cartItems->first()->currency }}</p>
                            </div>
                            <p class="mt-0.5 text-sm text-gray-500">Shipping and taxes calculated at checkout.</p>
                            <div class="mt-6">
                                <a href="#" class="flex justify-center items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                    Checkout
                                </a>
                            </div>
                            <div class="mt-6 flex justify-center text-sm text-center text-gray-500">
                                <button wire:click="clearCart" class="text-indigo-600 font-medium hover:text-indigo-500">
                                    Clear Cart<span aria-hidden="true"> &rarr;</span>
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
