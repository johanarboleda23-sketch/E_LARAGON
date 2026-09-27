<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class ReportFilters
{
    public static function applyFilters(Builder $query, array $filters): Builder
    {
        if (isset($filters['date_from'])) {
            $query->where('date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('date', '<=', $filters['date_to']);
        }

        if (isset($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query;
    }
}
