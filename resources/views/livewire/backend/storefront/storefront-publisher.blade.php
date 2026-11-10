<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('storefront.publisher.title') }}</h1>
            <p class="mt-1 max-w-3xl text-sm text-gray-600">{{ __('storefront.publisher.help') }}</p>
        </div>
        <div class="w-full sm:w-72">
            <label for="publisher-search" class="block text-sm font-medium text-gray-700">{{ __('storefront.publisher.search') }}</label>
            <input id="publisher-search" type="search" wire:model.live.debounce.350ms="search" maxlength="{{ \App\Livewire\Backend\Storefront\StorefrontPublisher::MAX_SEARCH_LENGTH }}" class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
    </div>

    @error('publish')
        <p class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200" role="alert">{{ $message }}</p>
    @enderror
    @if (! $linkingDefinition)
        @error('link')
            <p class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200" role="alert">{{ $message }}</p>
        @enderror
    @endif

    @if ($definitions->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-gray-300 bg-white px-6 py-12 text-center">
            <h2 class="text-base font-semibold text-gray-900">{{ $search === '' ? __('storefront.publisher.empty_title') : __('storefront.publisher.empty_filtered') }}</h2>
            @if ($search === '')
                <p class="mt-1 text-sm text-gray-600">{{ __('storefront.publisher.empty_body') }}</p>
            @endif
        </div>
    @else
        <ul class="space-y-4">
            @foreach ($definitions as $definition)
                @php($listing = $definition->storefrontListing)
                @php($available = $availability[$definition->id] ?? 0)
                <li wire:key="storefront-definition-{{ $definition->id }}" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200" data-testid="storefront-row">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 space-y-2">
                            <div>
                                <h2 class="font-semibold text-gray-900">{{ $definition->name }}</h2>
                                <p class="flex flex-wrap gap-x-3 text-sm text-gray-600">
                                    @if ($definition->model)<bdi>{{ $definition->model }}</bdi>@endif
                                    <span>{{ $definition->tracking_mode->label() }}</span>
                                    @if ($definition->sku)<span dir="ltr" class="font-mono text-xs">{{ $definition->sku }}</span>@endif
                                    @unless ($definition->is_active)
                                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('catalog.statuses.inactive') }}</span>
                                    @endunless
                                </p>
                            </div>
                            <p class="text-sm text-gray-700">
                                <span class="font-medium">{{ __('storefront.publisher.allocated') }}:</span>
                                @forelse ($allocations[$definition->id] ?? [] as $allocation)
                                    <span class="me-2 inline-flex items-center gap-1"><span dir="ltr" class="font-mono text-xs">{{ $allocation['store'] }}</span> <bdi dir="ltr" class="tabular-nums">{{ $allocation['quantity'] }}</bdi></span>
                                @empty
                                    <span class="text-gray-500">{{ __('storefront.publisher.none_allocated') }}</span>
                                @endforelse
                            </p>
                            <p class="text-sm">
                                <span class="font-medium text-gray-700">{{ __('storefront.publisher.available_now') }}:</span>
                                <bdi dir="ltr" @class(['font-semibold tabular-nums', 'text-green-700' => $available > 0, 'text-gray-600' => $available === 0])>{{ $available }}</bdi>
                            </p>
                        </div>

                        <div class="space-y-3 lg:w-96">
                            @if ($listing)
                                <div class="rounded-lg bg-gray-50 px-3 py-2 text-sm ring-1 ring-gray-200">
                                    <p class="text-xs font-medium text-gray-600">{{ __('storefront.publisher.linked_to') }}</p>
                                    <p class="font-medium text-gray-900">{{ $listing->product?->name }}</p>
                                    <p class="flex flex-wrap gap-x-3 text-xs text-gray-600">
                                        <span dir="ltr" class="font-mono">{{ $listing->product?->code }}</span>
                                        <span dir="ltr" class="font-mono">/{{ $listing->product?->slug }}</span>
                                        @if ($listing->product && ! $listing->product->is_active)
                                            <span class="font-medium text-amber-800">{{ __('storefront.publisher.product_inactive') }}</span>
                                        @endif
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($channels as $channel)
                                        @php($published = $listing->isPublishedTo($channel))
                                        <div class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm ring-1 ring-gray-200">
                                            <span class="font-medium text-gray-800">{{ $channel->label() }}</span>
                                            <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-green-50 text-green-800' => $published, 'bg-gray-100 text-gray-700' => ! $published])>{{ $published ? __('storefront.publisher.live') : __('storefront.publisher.not_live') }}</span>
                                            <button type="button" wire:click="setChannel({{ $listing->id }}, '{{ $channel->value }}', {{ $published ? 'false' : 'true' }})" wire:loading.attr="disabled" wire:target="setChannel" @class(['min-h-11 rounded-lg px-2 font-medium disabled:opacity-60', 'text-gray-700 hover:bg-gray-100' => $published, 'text-blue-700 hover:bg-blue-50' => ! $published])>
                                                {{ $published ? __('storefront.publisher.withdraw') : __('storefront.publisher.publish') }}
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                                @if ($listing->product && ! $listing->product->has_wholesale_tiers && ! $listing->isPublishedTo(\App\Enums\StorefrontChannel::WHOLESALE))
                                    <p class="text-xs text-gray-600">{{ __('storefront.publisher.wholesale_needs_tiers') }}</p>
                                @endif
                                @unless ($listing->isPublished())
                                    <button type="button" wire:click="unlink({{ $listing->id }})" wire:confirm="{{ __('storefront.publisher.unlink_confirm') }}" class="min-h-11 text-sm font-medium text-gray-700 underline hover:text-gray-900">{{ __('storefront.publisher.unlink') }}</button>
                                @endunless
                            @elseif ($linkingDefinitionId !== $definition->id)
                                <p class="text-sm text-gray-600">{{ __('storefront.publisher.not_linked') }}</p>
                                <button type="button" wire:click="openLink({{ $definition->id }})" class="min-h-11 rounded-lg px-3 text-sm font-medium text-blue-700 ring-1 ring-blue-200 hover:bg-blue-50">{{ __('storefront.publisher.link_or_create') }}</button>
                            @endif
                        </div>
                    </div>

                    @if (! $listing && $linkingDefinitionId === $definition->id)
                        <div class="mt-4 grid gap-4 border-t border-gray-100 pt-4 md:grid-cols-2" data-testid="link-panel">
                            <section aria-labelledby="link-existing-{{ $definition->id }}" class="space-y-2">
                                <h3 id="link-existing-{{ $definition->id }}" class="text-sm font-semibold text-gray-900">{{ __('storefront.publisher.link_existing') }}</h3>
                                <label for="product-search" class="block text-sm text-gray-700">{{ __('storefront.publisher.product_search') }}</label>
                                <input id="product-search" type="search" autofocus wire:model.live.debounce.350ms="productSearch" maxlength="{{ \App\Livewire\Backend\Storefront\StorefrontPublisher::MAX_SEARCH_LENGTH }}" class="block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('link') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                                @if (mb_strlen(trim($productSearch)) >= 2)
                                    <ul class="divide-y divide-gray-100 rounded-lg ring-1 ring-gray-200">
                                        @forelse ($candidates as $candidate)
                                            <li wire:key="candidate-{{ $candidate->id }}" class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                                <span class="min-w-0">
                                                    <span class="block font-medium text-gray-900">{{ $candidate->name }}</span>
                                                    <span dir="ltr" class="block font-mono text-xs text-gray-600">{{ $candidate->code }} · /{{ $candidate->slug }}</span>
                                                </span>
                                                <button type="button" wire:click="link({{ $candidate->id }})" wire:loading.attr="disabled" wire:target="link" class="min-h-11 shrink-0 rounded-lg px-3 font-medium text-blue-700 ring-1 ring-blue-200 hover:bg-blue-50 disabled:opacity-60">{{ __('storefront.publisher.link') }}</button>
                                            </li>
                                        @empty
                                            <li class="px-3 py-2 text-sm text-gray-600">{{ __('storefront.publisher.no_candidates') }}</li>
                                        @endforelse
                                    </ul>
                                @endif
                            </section>
                            <form wire:submit="createProduct" aria-labelledby="create-new-{{ $definition->id }}" class="space-y-2">
                                <h3 id="create-new-{{ $definition->id }}" class="text-sm font-semibold text-gray-900">{{ __('storefront.publisher.create_new') }}</h3>
                                <p class="text-xs text-gray-600">{{ __('storefront.publisher.create_help') }}</p>
                                <label for="category" class="block text-sm text-gray-700">{{ __('storefront.publisher.category') }}</label>
                                <select id="category" wire:model="categoryId" @error('category') aria-invalid="true" aria-describedby="category-error" @enderror class="block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">{{ __('storefront.publisher.choose_category') }}</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category') <p id="category-error" class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                                <div class="flex gap-2">
                                    <button type="submit" wire:loading.attr="disabled" wire:target="createProduct" class="min-h-11 rounded-lg bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">{{ __('storefront.publisher.create') }}</button>
                                    <button type="button" wire:click="closeLink" class="min-h-11 rounded-lg px-4 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('storefront.publisher.cancel') }}</button>
                                </div>
                            </form>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        {{ $definitions->links() }}
    @endif
</div>
