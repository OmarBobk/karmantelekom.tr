<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Livewire\Backend\OperationalLocale;
use App\Livewire\Pos\PosLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosLocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate(['locale' => ['required', 'string', Rule::in(OperationalLocale::SUPPORTED)]]);
        $request->session()->put(PosLocale::SESSION_KEY, $validated['locale']);

        return back(fallback: route('pos.home'));
    }
}
