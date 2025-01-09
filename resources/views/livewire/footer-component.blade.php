<div class="bg-base-200">
    <!-- Newsletter Section -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                <div class="text-white">
                    <h3 class="text-2xl font-bold mb-2">Subscribe to our newsletter</h3>
                    <p class="text-blue-100">Stay up to date with the latest news, announcements, and articles.</p>
                </div>
                <form class="flex flex-col md:flex-col sm:flex-row gap-3">
                    <input type="email" placeholder="Enter your email" class="input input-lg flex-grow bg-white/10 text-white placeholder-blue-200 border-white/20 focus:border-white focus:ring-2 focus:ring-white">
                    <button class="btn btn-lg bg-white text-blue-600 hover:bg-blue-50 border-white">
                        Subscribe
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Main Footer -->
    <footer class="footer p-10 text-base-content max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <!-- Company Info -->
        <div>
            <span class="text-2xl font-semibold mb-2" style="font-family: 'Poppins', sans-serif; background: linear-gradient(to right, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                İndirmGo
            </span>
            <p class="mt-4 text-gray-600 max-w-xs">
                Making the world a better place through constructing elegant hierarchies.
            </p>
            <!-- Social Links -->
            <div class="flex gap-4 mt-6">
                @foreach($socialLinks as $social)
                    <a href="{{ $social['url'] }}" class="text-gray-600 hover:text-gray-900 transition-colors duration-200">
                        <span class="sr-only">{{ $social['name'] }}</span>
                        @if($social['icon'] === 'facebook')
                            <svg class="size-6" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
                        @elseif($social['icon'] === 'twitter')
                            <svg class="size-6" fill="currentColor" viewBox="0 0 24 24"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/></svg>
                        @elseif($social['icon'] === 'instagram')
                            <svg class="size-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                        @elseif($social['icon'] === 'linkedin')
                            <svg class="size-6" fill="currentColor" viewBox="0 0 24 24"><path d="M4.98 3.5c0 1.381-1.11 2.5-2.48 2.5s-2.48-1.119-2.48-2.5c0-1.38 1.11-2.5 2.48-2.5s2.48 1.12 2.48 2.5zm.02 4.5h-5v16h5v-16zm7.982 0h-4.968v16h4.969v-8.399c0-4.67 6.029-5.052 6.029 0v8.399h4.988v-10.131c0-7.88-8.922-7.593-11.018-3.714v-2.155z"/></svg>
                        @elseif($social['icon'] === 'youtube')
                            <svg class="size-6" fill="currentColor" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Company Links -->
        <div>
            <span class="footer-title opacity-100 text-gray-900 font-semibold">Company</span>
            <div class="flex flex-col space-y-3 mt-4">
                @foreach($companyLinks as $link)
                    <a href="{{ $link['url'] }}" class="link link-hover text-gray-600 hover:text-gray-900">{{ $link['name'] }}</a>
                @endforeach
            </div>
        </div>

        <!-- Legal Links -->
        <div>
            <span class="footer-title opacity-100 text-gray-900 font-semibold">Legal</span>
            <div class="flex flex-col space-y-3 mt-4">
                @foreach($legalLinks as $link)
                    <a href="{{ $link['url'] }}" class="link link-hover text-gray-600 hover:text-gray-900">{{ $link['name'] }}</a>
                @endforeach
            </div>
        </div>

        <!-- Support Links -->
        <div>
            <span class="footer-title opacity-100 text-gray-900 font-semibold">Support</span>
            <div class="flex flex-col space-y-3 mt-4">
                @foreach($supportLinks as $link)
                    <a href="{{ $link['url'] }}" class="link link-hover text-gray-600 hover:text-gray-900">{{ $link['name'] }}</a>
                @endforeach
            </div>
        </div>
    </footer>

    <!-- Bottom Footer -->
    <footer class="footer footer-center p-10 bg-base-300 text-base-content">
        <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="text-sm text-gray-600">
                    <p>Copyright © {{ date('Y') }} İndirmGo. All rights reserved.</p>
                </div>
                <div class="flex items-center gap-4">
                    <select class="select select-sm bg-transparent border-gray-300 py-0 h-full">
                        <option>🌍 English</option>
                        <option>🇹🇷 Türkçe</option>
                        <option>🇩🇪 Deutsch</option>
                        <option>🇫🇷 Français</option>
                        <option>🇪🇸 Español</option>
                    </select>
                    <select class="select select-sm bg-transparent border-gray-300 py-0 h-full">
                        <option>USD $</option>
                        <option>EUR €</option>
                        <option>GBP £</option>
                        <option>TRY ₺</option>
                    </select>
                </div>
                <div class="flex items-center gap-4">
                    <img src="https://raw.githubusercontent.com/simple-icons/simple-icons/develop/icons/visa.svg" alt="Visa" class="h-8 opacity-50 hover:opacity-100 transition-opacity">
                    <img src="https://raw.githubusercontent.com/simple-icons/simple-icons/develop/icons/mastercard.svg" alt="Mastercard" class="h-8 opacity-50 hover:opacity-100 transition-opacity">
                    <img src="https://raw.githubusercontent.com/simple-icons/simple-icons/develop/icons/paypal.svg" alt="PayPal" class="h-8 opacity-50 hover:opacity-100 transition-opacity">
                    <img src="https://raw.githubusercontent.com/simple-icons/simple-icons/develop/icons/applepay.svg" alt="Apple Pay" class="h-8 opacity-50 hover:opacity-100 transition-opacity">
                </div>
            </div>
        </div>
    </footer>
</div>
