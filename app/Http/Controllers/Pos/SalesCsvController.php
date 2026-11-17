<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Livewire\Pos\PosLocale;
use App\Models\Store;
use App\Services\Reporting\CsvExporter;
use App\Services\Reporting\LocalDateRange;
use App\Services\Reporting\SalesCsv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Owner CSV of the filtered sales history for one branch. Ordinary HTTP download — not Livewire.
 */
class SalesCsvController extends Controller
{
    public function __invoke(Request $request, Store $store): StreamedResponse
    {
        PosLocale::apply();

        Gate::authorize('usePos', $store);
        Gate::authorize('viewAllSales', $store);

        [$from, $to] = $this->range($request);
        $fromAt = Str::trim((string) $request->query('from_at', ''));
        $toAt = Str::trim((string) $request->query('to_at', ''));
        $intervalPresent = $fromAt !== '' || $toAt !== '';
        abort_if($intervalPresent && LocalDateRange::intervalError($fromAt, $toAt) !== null, 422, __('reports.export.range_required'));
        abort_if(! $intervalPresent && $from !== '' && LocalDateRange::error($from, $to) !== null, 422, __('reports.export.range_required'));

        $search = mb_substr(Str::trim((string) $request->query('q', '')), 0, 80);
        $tender = Str::trim((string) $request->query('tender', ''));
        $filtered = SalesCsv::query($request->user(), $store, $search, $from, $to, $tender, $fromAt, $toAt);

        return CsvExporter::download(
            __('reports.export.sales_filename', ['store' => $store->code, 'date' => LocalDateRange::today()]),
            SalesCsv::headers(),
            SalesCsv::rows($filtered),
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function range(Request $request): array
    {
        $from = Str::trim((string) $request->query('from', ''));
        $to = Str::trim((string) $request->query('to', ''));

        if ($from === '' && $to === '') {
            $legacy = Str::trim((string) $request->query('date', ''));

            return [$legacy, $legacy];
        }

        return [$from === '' ? $to : $from, $to === '' ? $from : $to];
    }
}
