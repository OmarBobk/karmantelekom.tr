<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Livewire\Pos\PosLocale;
use App\Models\Store;
use App\Services\Customers\CustomerAccount;
use App\Services\Reporting\CsvExporter;
use App\Services\Reporting\CustomerBalancesCsv;
use App\Services\Reporting\LocalDateRange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Owner CSV of customer balances for one branch. Ordinary HTTP download — not Livewire.
 */
class CustomerBalancesCsvController extends Controller
{
    public function __invoke(Request $request, Store $store, CustomerAccount $account): StreamedResponse
    {
        PosLocale::apply();

        Gate::authorize('receiveCustomerPayments', $store);

        $search = mb_substr(Str::squish((string) $request->query('q', '')), 0, 80);
        $balance = $request->query('balance') === 'settled' ? 'settled' : 'open';
        $filtered = CustomerBalancesCsv::query($account, $store, $search, $balance);

        return CsvExporter::download(
            __('reports.export.customers_filename', ['store' => $store->code, 'date' => LocalDateRange::today()]),
            CustomerBalancesCsv::headers(),
            CustomerBalancesCsv::rows($filtered, $balance),
        );
    }
}
