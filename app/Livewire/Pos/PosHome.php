<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Livewire\Pos\Concerns\UsesPosLocale;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Branch picker for the counter. Only stores the user may sell in are listed; a single
 * store opens directly.
 */
class PosHome extends Component
{
    use UsesPosLocale;

    public function mount(): void
    {
        $stores = $this->stores();

        if ($stores->count() === 1) {
            $this->redirectRoute('pos.counter', $stores->first());
        }
    }

    public function render(): View
    {
        return view('livewire.pos.pos-home', [
            'stores' => $this->stores(),
            ...$this->localeViewData(),
        ])->layout('layouts.pos')->title(__('pos.title'));
    }

    /**
     * @return Collection<int, Store>
     */
    private function stores(): Collection
    {
        $user = auth()->user();

        return Store::query()->accessibleTo($user)
            ->orderBy('code')
            ->get()
            ->filter(fn (Store $store): bool => $user->can('usePos', $store))
            ->values();
    }
}
