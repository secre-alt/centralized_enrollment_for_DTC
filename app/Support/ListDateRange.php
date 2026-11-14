<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ListDateRange
{
    public static function rules(Request $request): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => array_merge(['nullable', 'date_format:Y-m-d'], $request->filled('date_from') ? ['after_or_equal:date_from'] : []),
        ];
    }

    // These lists store local timestamps using the application's Asia/Manila convention.
    public static function apply(Builder $query, array $filters, string $column = 'created_at'): Builder
    {
        if (isset($filters['date_from'])) {
            $query->where($column, '>=', $filters['date_from'].' 00:00:00');
        }
        if (isset($filters['date_to'])) {
            $query->where($column, '<', \Carbon\Carbon::parse($filters['date_to'], 'Asia/Manila')->addDay()->format('Y-m-d').' 00:00:00');
        }

        return $query;
    }
}
