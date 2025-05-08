<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait Searchable
{

    /**
     * Variables Need to be defined on the model
     * $searchable_columns = [col_1, col_2]
     * $return_from_search = [col_1, col_2]
     *
     *
     * @param Builder $builder
     * @param string $needle
     * @param string $orderByColumn
     * @param string $orderByDirection
     * @param string|null $sortOrder
     * @return Builder|null
     *
     */

    public function scopeSearch(Builder $builder,
                                string  $needle,
                                string  $orderByColumn,
                                string  $orderByDirection = 'asc',
    ): ?Builder
    {
        if ($this->searchable_columns == null) {
            return null;
        }

        // $this->searchable_columns = ['id', 'code'];
        $searchableColumns = array_map(function ($column) use ($builder) {
            return "`{$this->getTable()}`" . ".`$column`";
        }, $this->searchable_columns);
        // $searchableColumns = [
        //      0 => "`products`.`id`"
        //      1 => "`products`.`code`"
        //  ]

        $queryString = $this->buildQuery($searchableColumns, $needle);
        // $queryString = '`products`.`id` LIKE %search% OR
        //                 `products`.`code` LIKE %search% OR
        //                 `products`.`id` LIKE %keyword% OR
        //                 `products`.`code` LIKE %keyword%'


        $builder->whereRaw("( $queryString )")
            ->orderBy($orderByColumn, $orderByDirection);

        if ($this->return_from_search) {
            // $this->return_from_search = ['id', 'name']
            $builder->select(array_map(function ($column) {
                return "{$this->getTable()}.$column";
            }, $this->return_from_search));
            // $builder = Builder statement with only id,name columns it is just like pluck('id', 'name')
        }

        return $builder;
    }

    /**
     * Build a query based on the array that contains column names.
     *
     * @param array $array
     * @param string $searchTerm
     * @return string
     */
    private function buildQuery(array $array, string $searchTerm): string
    {
        // $array = ['`products`.`id`', '`products`.`code`'];
        $first = true;
        $queryString = '';
        // $searchTerms = 'search keyword';
        $searchTerms = explode(' ', $searchTerm);
        // $searchTerms = ['search', 'keyword']

        foreach ($searchTerms as $searchTerm) {
            $searchTerm = DB::connection()->getPdo()->quote('%' . $searchTerm . '%');
            // 1 => $searchTerm = '%search%';
            // 2 => $searchTerm = '%keyword%';

            foreach ($array as $column) {
                // 1.1 => $first = true;
                // 1.2 => $first = false;
                // 2.1 => $first = false;
                // 2.2 => $first = false;

                if ($first) {
                    $first = false;
                } else {
                    $queryString .= ' OR ';
                }
                $queryString .= $column . ' LIKE ' . $searchTerm;
                // 1.1 => $queryString = '`products`.`id` LIKE %search%'
                // 1.2 => $queryString = '`products`.`id` LIKE %search% OR `products`.`code` LIKE %search%'
                // 2.1 => $queryString = '`products`.`id` LIKE %search% OR `products`.`code` LIKE %search% OR `products`.`id` LIKE %keyword%'
                // 2.2 => $queryString = '`products`.`id` LIKE %search% OR `products`.`code` LIKE %search% OR `products`.`id` LIKE %keyword% OR `products`.`code` LIKE %keyword%'
            }
        }

        return $queryString;
    }
}
