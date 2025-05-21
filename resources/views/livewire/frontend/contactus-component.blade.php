<div class="py-12 px-2 md:px-0">
    <div class="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Contact Info -->
        <div class="bg-white rounded-2xl shadow-lg p-8 flex flex-col justify-between transition-all duration-300">
            <h2 class="text-2xl font-bold mb-6 text-gray-900">{{ __('main.info_title') }}</h2>
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <span class="text-warning-500"><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='w-6 h-6 text-warning-500'><path stroke-linecap='round' stroke-linejoin='round' d='M2.25 6.75c0-1.243 1.007-2.25 2.25-2.25h2.386c.51 0 .998.194 1.366.543l2.007 1.885a2.25 2.25 0 0 0 1.366.543h6.255c1.243 0 2.25 1.007 2.25 2.25v8.25c0 1.243-1.007 2.25-2.25 2.25H4.5a2.25 2.25 0 0 1-2.25-2.25V6.75Z'/></svg></span>
                    <div>
                        <div class="font-semibold">{{ __('main.phone') }}</div>
                        <div class="text-gray-700 text-sm">+1 (555) 123-4567<br>+1 (555) 987-6543</div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="text-success-500"><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='w-6 h-6 text-success-500'><path stroke-linecap='round' stroke-linejoin='round' d='M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25H4.5a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5H4.5a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-.659 1.591l-7.591 7.591a2.25 2.25 0 0 1-3.182 0l-7.591-7.591A2.25 2.25 0 0 1 2.25 6.993V6.75'/></svg></span>
                    <div>
                        <div class="font-semibold">{{ __('main.email') }}</div>
                        <div class="text-gray-700 text-sm">info@company.com<br>support@company.com</div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="text-primary-500"><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='w-6 h-6 text-primary-500'><path stroke-linecap='round' stroke-linejoin='round' d='M12 6v6l4 2'/></svg></span>
                    <div>
                        <div class="font-semibold">{{ __('main.hours') }}</div>
                        <div class="text-gray-700 text-sm">{{ __('main.hours_detail') }}</div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Contact Form -->
        <div class="md:col-span-2 bg-white rounded-2xl shadow-lg p-8 transition-all duration-300">
            <h2 class="text-2xl font-bold mb-6 text-gray-900">{{ __('main.form_title') }}</h2>
            @if($success)
                <div class="mb-4 p-4 rounded-lg bg-gradient-success text-white shadow">{{ __('main.success') }}</div>
            @endif
            <form wire:submit.prevent="submit" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <x-label for="name" :value="__('main.your_name')" />
                        <x-input id="name" type="text" class="mt-1 block w-full" wire:model.defer="name" autocomplete="name" />
                        <x-input-error for="name" />
                    </div>
                    <div>
                        <x-label for="email" :value="__('main.email_address')" />
                        <x-input id="email" type="email" class="mt-1 block w-full" wire:model.defer="email" autocomplete="email" />
                        <x-input-error for="email" />
                    </div>
                </div>
                <div>
                    <x-label for="subject" :value="__('main.subject')" />
                    <x-input id="subject" type="text" class="mt-1 block w-full" wire:model.defer="subject" />
                    <x-input-error for="subject" />
                </div>
                <div>
                    <x-label for="message" :value="__('main.message')" />
                    <textarea id="message" rows="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 transition" wire:model.defer="message"></textarea>
                    <x-input-error for="message" />
                </div>
                <div>
                    <x-button class="w-full bg-warning-500 hover:bg-warning-600 text-white text-lg font-semibold py-3 rounded-lg shadow transition duration-200 flex items-center justify-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v7.5A2.25 2.25 0 0 0 6.75 19.5h10.5a2.25 2.25 0 0 0 2.25-2.25v-7.5" /></svg>
                        <span>{{ __('main.send_message') }}</span>
                    </x-button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('styles')
<style>
    .text-success-500 { color: #2dce89; }
    .text-warning-500 { color: #fb6340; }
    .text-primary-500 { color: #5e72e4; }
    .bg-warning-500 { background-color: #fb6340; }
    .bg-warning-600 { background-color: #fbb140; }
    .bg-gradient-success { background-image: linear-gradient(310deg, #2dce89, #2dcecc); }
</style>
@endpush
