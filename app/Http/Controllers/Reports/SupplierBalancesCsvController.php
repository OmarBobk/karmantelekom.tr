<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Livewire\Backend\OperationalLocale;
use App\Models\OperationalSupplier;
use App\Models\Store;
use App\Services\Purchasing\SupplierAccount;
use App\Services\Reporting\CsvExporter;
use App\Services\Reporting\LocalDateRange;
use App\Services\Reporting\SupplierBalancesCsv;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Owner CSV of supplier balances across accessible branches. Ordinary HTTP download — not Livewire.
 */
class SupplierBalancesCsvController extends Controller
{
    public function __invoke(SupplierAccount $accounts): StreamedResponse
    {
        OperationalLocale::apply();

        Gate::authorize('viewFinanceOverview', Store::class);
        Gate::authorize('viewAccounts', OperationalSupplier::class);

        $storeIds = $accounts->accessibleStoreIds(auth()->user());

        return CsvExporter::download(
            __('reports.export.suppliers_filename', ['date' => LocalDateRange::today()]),
            SupplierBalancesCsv::headers(),
            SupplierBalancesCsv::rows($accounts, $storeIds),
        );
    }
}
