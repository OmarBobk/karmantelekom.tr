<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Livewire\Backend\OperationalLocale;
use App\Livewire\Pos\PosLocale;
use App\Models\Sale;
use App\Models\Store;
use Illuminate\Contracts\View\View;

/**
 * The internal 80 mm sales slip. Not a fiscal receipt; it never shows costs or profit.
 */
class SaleReceiptController extends Controller
{
    public function __invoke(Store $store, Sale $sale): View
    {
        PosLocale::apply();

        $sale->load(['lines', 'payments.terminal', 'cashier:id,name', 'store']);

        return view('pos.receipt', [
            'store' => $store,
            'sale' => $sale,
            'locale' => OperationalLocale::resolve(),
            'textDirection' => OperationalLocale::direction(),
            'autoPrint' => request()->boolean('print'),
        ]);
    }
}
