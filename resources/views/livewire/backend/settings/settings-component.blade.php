<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
            <!-- Tabs -->
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex -mb-px">
                    <button wire:click="$set('activeTab', 'general')"
                            class="px-4 py-2 text-sm font-medium {{ $activeTab === 'general' ? 'border-b-2 border-indigo-500 text-indigo-600' : 'text-gray-500 hover:text-gray-700' }}">
                        General
                    </button>
                    <button wire:click="$set('activeTab', 'social')"
                            class="px-4 py-2 text-sm font-medium {{ $activeTab === 'social' ? 'border-b-2 border-indigo-500 text-indigo-600' : 'text-gray-500 hover:text-gray-700' }}">
                        Social Media
                    </button>
                </nav>
            </div>

            <!-- Settings Form -->
            <div class="p-6">
                <form wire:submit="saveSettings">
                    @if($activeTab === 'general')
                        <div class="space-y-4">
                            <div>
                                <label for="site_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Site Name</label>
                                <input type="text" id="site_name" wire:model="settings.general.site_name"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label for="phone_number_1" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Primary Phone Number</label>
                                <input type="tel" id="phone_number_1" wire:model="settings.general.phone_number_1"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label for="phone_number_2" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Secondary Phone Number</label>
                                <input type="tel" id="phone_number_2" wire:model="settings.general.phone_number_2"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label for="phone_number_3" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tertiary Phone Number</label>
                                <input type="tel" id="phone_number_3" wire:model="settings.general.phone_number_3"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label for="contact_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact Email</label>
                                <input type="email" id="contact_email" wire:model="settings.general.contact_email"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <!-- Product Prices Toggle -->
                            <div class="flex items-center justify-between">
                                <div>
                                    <label for="product_prices" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Show Product Prices</label>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Enable or disable product prices display on the website</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox"
                                           id="product_prices"
                                           wire:model="settings.general.product_prices"
                                           {{ (isset($settings['general']['product_prices'])) ? ( ($settings['general']['product_prices'] == 'enabled') ? 'checked' : '' ) : '' }}
                                           class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
                                </label>
                            </div>
                        </div>
                    @endif

                    @if($activeTab === 'social')
                        <div class="space-y-4">
                            <div>
                                <label for="facebook_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Facebook URL</label>
                                <input type="url" id="facebook_url" wire:model="settings.social.facebook_url"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label for="twitter_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Twitter URL</label>
                                <input type="url" id="twitter_url" wire:model="settings.social.twitter_url"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label for="instagram_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Instagram URL</label>
                                <input type="url" id="instagram_url" wire:model="settings.social.instagram_url"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label for="whatsapp_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">WhatsApp Number</label>
                                <input type="tel" id="whatsapp_number" wire:model="settings.social.whatsapp_number"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        </div>
                    @endif

                    <div class="mt-6">
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
