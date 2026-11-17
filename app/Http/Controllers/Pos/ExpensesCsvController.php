<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pos;

use App\Enums\ExpenseCategory;
use App\Http\Controllers\Controller;
use App\Livewire\Pos\PosLocale;
use App\Models\Store;
use App\Services\Reporting\CsvExporter;
use App\Services\Reporting\ExpensesCsv;
use App\Services\Reporting\LocalDateRange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Owner CSV of the filtered expense history for one branch. Ordinary HTTP download — not Livewire.
 */
class ExpensesCsvController extends Controller
{
    public function __invoke(Request $request, Store $store): StreamedResponse
    {
        PosLocale::apply();

        Gate::authorize('manageExpenses', $store);

        $from = Str::trim((string) $request->query('from', ''));
        $to = Str::trim((string) $request->query('to', ''));
        $category = Str::trim((string) $request->query('category', ''));

        if ($from === '') {
            $from = $to;
        } elseif ($to === '') {
            $to = $from;
        }

        abort_if($from !== '' && LocalDateRange::error($from, $to) !== null, 422, __('reports.export.range_required'));
        abort_if($category !== '' && ExpenseCategory::tryFrom($category) === null, 422, __('reports.export.range_required'));

        $filtered = ExpensesCsv::query($store, $from, $to, $category);

        return CsvExporter::download(
            __('reports.export.expenses_filename', ['store' => $store->code, 'date' => LocalDateRange::today()]),
            ExpensesCsv::headers(),
            ExpensesCsv::rows($filtered),
        );
    }
}
