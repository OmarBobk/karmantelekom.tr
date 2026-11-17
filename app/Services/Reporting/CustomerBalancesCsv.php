<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Customer;
use App\Models\Store;
use App\Services\Customers\CustomerAccount;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Authoritative customer balance query shared by the accounts screen and CSV download.
 */
final class CustomerBalancesCsv
{
    /**
     * @return Builder<Customer>
     */
    public static function query(CustomerAccount $account, Store $store, string $search, string $balance): Builder
    {
        $term = Str::squish($search);
        $settled = $balance === 'settled';

        return ($settled ? $account->settled($store) : $account->debtors($store))
            ->when($term !== '', fn ($query) => $query->where('customers.name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->reorder()
            ->orderBy('customers.name')
            ->orderBy('customers.id');
    }

    /**
     * @param  Builder<Customer>  $filtered
     * @return \Generator<int, list<string>>
     */
    public static function rows(Builder $filtered, string $balance): \Generator
    {
        $status = $balance === 'settled'
            ? __('reports.export.customers.settled')
            : __('reports.export.customers.open');

        foreach ($filtered->lazy(ReportCsvBatch::size()) as $customer) {
            yield [
                CsvExporter::text($customer->name),
                CsvExporter::text($customer->phone_normalized),
                CsvExporter::amount(Money::toDecimal(Money::fromDecimal((string) $customer->due_amount))),
                'TRY',
                CsvExporter::text($status),
            ];
        }
    }

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            __('reports.export.customers.name'),
            __('reports.export.customers.phone'),
            __('reports.export.customers.due'),
            __('reports.export.customers.currency'),
            __('reports.export.customers.status'),
        ];
    }
}
