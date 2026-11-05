<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Inventory;

use App\Actions\Inventory\FindUnitsByIdentifier;
use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Exact IMEI / serial lookup for scanners and paste, across the user's stores or within one store.
 *
 * The input is owned by Alpine and sent as an action argument, so a response never rewrites
 * what the operator typed. Each submission carries a sequence number; the browser only follows
 * the response to its latest submission, and the server ignores results older than it has shown.
 */
class DeviceLookup extends Component
{
    use UsesOperationalLocale;

    #[Locked]
    public ?int $storeId = null;

    #[Locked]
    public string $code = '';

    #[Locked]
    public int $sequence = 0;

    /**
     * @var array{current: list<array<string, mixed>>, historical: list<array<string, mixed>>}|null
     */
    private ?array $result = null;

    public function mount(?Store $store = null): void
    {
        $this->storeId = $store?->id;
        $this->authorizeLookup();
    }

    /**
     * @return array{sequence: int, url: ?string, matches: int}
     */
    public function lookup(FindUnitsByIdentifier $find, string $code, int $sequence): array
    {
        $store = $this->authorizeLookup();

        if ($sequence < $this->sequence) {
            return ['sequence' => $sequence, 'url' => null, 'matches' => 0];
        }

        $this->code = mb_substr($code, 0, FindUnitsByIdentifier::MAX_INPUT_LENGTH + 1);
        $this->sequence = $sequence;
        $this->result = $find(auth()->user(), $this->code, $store);

        $current = $this->result['current'];
        $url = count($current) === 1
            ? route('subdomain.inventory.unit', [$current[0]['store_id'], $current[0]['unit_id']])
            : null;

        return ['sequence' => $sequence, 'url' => $url, 'matches' => count($current) + count($this->result['historical'])];
    }

    public function render(FindUnitsByIdentifier $find): View
    {
        $store = $this->authorizeLookup();

        if ($this->result === null && $this->code !== '') {
            $this->result = $find(auth()->user(), $this->code, $store);
        }

        return view('livewire.backend.inventory.device-lookup', [
            'result' => $this->result,
            'scoped' => $store !== null,
            ...$this->localeViewData(),
        ]);
    }

    private function authorizeLookup(): ?Store
    {
        if ($this->storeId === null) {
            $this->authorize('viewAnyInventory', Store::class);

            return null;
        }

        $store = Store::query()->findOrFail($this->storeId);
        $this->authorize('viewInventory', $store);

        return $store;
    }
}
