<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Reporting periods on the Rwandan government financial year.
 *
 * The year runs from 1 July to 30 June, so the quarters are:
 *
 *   Q1  July      to September
 *   Q2  October   to December
 *   Q3  January   to March
 *   Q4  April     to June
 *
 * Calendar quarters would put a January figure in Q1 and misstate every
 * quarterly return the unit submits, so everything here is derived from
 * the financial year rather than the calendar.
 */
class FiscalPeriod
{
    /** The month the financial year begins. */
    public const START_MONTH = 7;

    /**
     * The financial year a date falls in, named by its starting year.
     * 15 August 2026 and 3 March 2027 both belong to 2026.
     */
    public static function yearOf(?Carbon $date = null): int
    {
        $date = $date ?: now();

        return $date->month >= self::START_MONTH ? $date->year : $date->year - 1;
    }

    /** '2026/2027' */
    public static function label(int $year): string
    {
        return $year . '/' . ($year + 1);
    }

    /** First day of a financial year. */
    public static function start(int $year): Carbon
    {
        return Carbon::create($year, self::START_MONTH, 1)->startOfDay();
    }

    /** Last day of a financial year. */
    public static function end(int $year): Carbon
    {
        return Carbon::create($year + 1, self::START_MONTH, 1)->subDay()->endOfDay();
    }

    /** Which financial quarter a date falls in, 1 to 4. */
    public static function quarterOf(?Carbon $date = null): int
    {
        $date = $date ?: now();

        return match (true) {
            $date->month >= 7  && $date->month <= 9  => 1,
            $date->month >= 10 && $date->month <= 12 => 2,
            $date->month >= 1  && $date->month <= 3  => 3,
            default                                  => 4,
        };
    }

    /** The date range of one financial quarter. */
    public static function quarter(int $year, int $q): array
    {
        $startMonth = match ($q) {
            1 => [$year,     7],
            2 => [$year,     10],
            3 => [$year + 1, 1],
            4 => [$year + 1, 4],
        };

        $from = Carbon::create($startMonth[0], $startMonth[1], 1)->startOfDay();

        return [$from, $from->copy()->addMonths(3)->subDay()->endOfDay()];
    }

    public static function quarterLabel(int $q): string
    {
        return match ($q) {
            1 => 'Q1 — July to September',
            2 => 'Q2 — October to December',
            3 => 'Q3 — January to March',
            4 => 'Q4 — April to June',
        };
    }

    /**
     * Resolve a period key into [from, to, label].
     * An unknown or empty key returns nulls, meaning no date limit.
     */
    public static function resolve(?string $key): array
    {
        $now  = now();
        $year = self::yearOf($now);

        // fy:2026 and q:2026:3 carry their own arguments
        if ($key && str_starts_with($key, 'fy:')) {
            $y = (int) substr($key, 3);
            return [self::start($y), self::end($y), 'Financial year ' . self::label($y)];
        }

        if ($key && str_starts_with($key, 'q:')) {
            [, $y, $q] = explode(':', $key);
            [$from, $to] = self::quarter((int) $y, (int) $q);
            return [$from, $to, self::quarterLabel((int) $q) . ', ' . self::label((int) $y)];
        }

        return match ($key) {
            'today'      => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'Today'],
            'yesterday'  => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(), 'Yesterday'],

            'this_week'  => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek(), 'This week'],
            'last_week'  => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek(), 'Last week'],

            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), $now->format('F Y')],
            'last_month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth(),
                             $now->copy()->subMonth()->format('F Y')],

            'this_quarter' => (function () use ($year, $now) {
                $q = self::quarterOf($now);
                [$from, $to] = self::quarter($year, $q);
                return [$from, $to, self::quarterLabel($q) . ', ' . self::label($year)];
            })(),

            'last_quarter' => (function () use ($year, $now) {
                $q = self::quarterOf($now) - 1;
                $y = $year;
                if ($q < 1) { $q = 4; $y = $year - 1; }
                [$from, $to] = self::quarter($y, $q);
                return [$from, $to, self::quarterLabel($q) . ', ' . self::label($y)];
            })(),

            'this_year'  => [self::start($year), self::end($year), 'Financial year ' . self::label($year)],
            'last_year'  => [self::start($year - 1), self::end($year - 1), 'Financial year ' . self::label($year - 1)],

            default      => [null, null, 'All time'],
        };
    }

    /**
     * The selector, grouped for a readable dropdown.
     * Financial years are offered back to whenever records begin.
     */
    public static function options(?int $earliestYear = null): array
    {
        $year = self::yearOf();
        $earliestYear = $earliestYear ?: $year - 2;

        $quarters = [];
        for ($q = 1; $q <= 4; $q++) {
            $quarters['q:' . $year . ':' . $q] = self::quarterLabel($q);
        }

        $years = [];
        for ($y = $year; $y >= $earliestYear; $y--) {
            $years['fy:' . $y] = self::label($y)
                . ($y === $year ? ' — current' : '');
        }

        return [
            'Day' => [
                'today'     => 'Today',
                'yesterday' => 'Yesterday',
            ],
            'Week' => [
                'this_week' => 'This week',
                'last_week' => 'Last week',
            ],
            'Month' => [
                'this_month' => 'This month',
                'last_month' => 'Last month',
            ],
            'Quarter — financial year ' . self::label($year) => array_merge([
                'this_quarter' => 'This quarter',
                'last_quarter' => 'Last quarter',
            ], $quarters),
            'Financial year — 1 July to 30 June' => $years,
        ];
    }
}