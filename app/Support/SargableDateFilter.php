<?php

namespace App\Support;

use Carbon\Carbon;

class SargableDateFilter
{
    public static function apply(object $query, string $column, string $operator, mixed $value): object
    {
        $start = Carbon::parse($value)->startOfDay();
        $nextDay = $start->copy()->addDay();
        $startBoundary = $start->toDateString();
        $nextDayBoundary = $nextDay->toDateString();

        return match ($operator) {
            '=', '==' => $query
                ->where($column, '>=', $startBoundary)
                ->where($column, '<', $nextDayBoundary),
            '<=' => $query->where($column, '<', $nextDayBoundary),
            '<' => $query->where($column, '<', $startBoundary),
            '>=' => $query->where($column, '>=', $startBoundary),
            '>' => $query->where($column, '>=', $nextDayBoundary),
            default => throw new \InvalidArgumentException("Operator tanggal tidak didukung: {$operator}"),
        };
    }
}
