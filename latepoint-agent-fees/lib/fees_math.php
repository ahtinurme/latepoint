<?php
/*
 * Pure schedule-fee math, no WordPress dependencies.
 * Shifts: 9:00-14:00 and 14:00-20:00 (minutes since midnight). A shift covered at
 * least 80% by the agent's working hours earns the full schedule fee (1.0 units);
 * below 80% it earns a proportional coefficient (covered / shift length) of the fee.
 * If neither shift reaches 80% on its own, the day still earns at least ONE full
 * schedule when the total working time within 9:00-20:00 reaches 80% of the
 * shortest shift (240 min) - e.g. working 12:00-16:00 earns one full schedule.
 *
 * Run `php lib/fees_math.php` to self-check the logic.
 */

if (!defined('LATEPOINT_AGENT_FEES_SHIFTS')) {
    define('LATEPOINT_AGENT_FEES_SHIFTS', [[540, 840], [840, 1200]]);
}

if (!function_exists('latepoint_agent_fees_merge')) {

    /**
     * @param array<int, array{0:int, 1:int}> $periods
     * @return array<int, array{0:int, 1:int}> sorted, non-overlapping, empty periods dropped
     */
    function latepoint_agent_fees_merge(array $periods): array {
        $periods = array_values(array_filter($periods, fn($p) => $p[0] < $p[1]));
        usort($periods, fn($a, $b) => $a[0] <=> $b[0]);

        $merged = [];
        foreach ($periods as [$start, $end]) {
            $last = count($merged) - 1;
            if ($last >= 0 && $start <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $end);
                continue;
            }
            $merged[] = [$start, $end];
        }
        return $merged;
    }

    /**
     * @param array<int, array{0:int, 1:int}> $merged non-overlapping sorted periods
     */
    function latepoint_agent_fees_overlap(array $merged, int $from, int $to): int {
        $minutes = 0;
        foreach ($merged as [$start, $end]) {
            $minutes += max(0, min($end, $to) - max($start, $from));
        }
        return $minutes;
    }

    /**
     * @param array<int, array{0:int, 1:int}> $periods raw working periods for one day
     * @return array{
     *     schedules: float,
     *     shift_units: array<int, float>,
     *     combined: bool,
     *     covered: array<int, int>,
     *     hits: array<int, bool>,
     *     total: int
     * } covered/total are minutes; total is clipped to the 9:00-20:00 window. Units are
     *   full-schedule-fee multiples: 1.0 per shift covered >=80%, covered/length below.
     */
    function latepoint_agent_fees_day_schedules(array $periods): array {
        $shifts      = LATEPOINT_AGENT_FEES_SHIFTS;
        $merged      = latepoint_agent_fees_merge($periods);
        $covered     = [];
        $hits        = [];
        $shift_units = [];

        foreach ($shifts as $i => [$from, $to]) {
            $covered[$i]     = latepoint_agent_fees_overlap($merged, $from, $to);
            $hits[$i]        = $covered[$i] * 5 >= ($to - $from) * 4;
            $shift_units[$i] = $hits[$i] ? 1.0 : $covered[$i] / ($to - $from);
        }

        $window    = [min(array_column($shifts, 0)), max(array_column($shifts, 1))];
        $total     = latepoint_agent_fees_overlap($merged, $window[0], $window[1]);
        $schedules = array_sum($shift_units);
        $combined  = false;

        // ponytail: combined threshold = 80% of the shortest shift (240 min)
        $threshold = (int) ceil(min(array_map(fn($s) => $s[1] - $s[0], $shifts)) * 0.8);
        if (!in_array(true, $hits, true) && $total >= $threshold && $schedules < 1.0) {
            $combined  = true;
            $schedules = 1.0;
        }

        return [
            'schedules'   => $schedules,
            'shift_units' => $shift_units,
            'combined'    => $combined,
            'covered'     => $covered,
            'hits'        => $hits,
            'total'       => $total,
        ];
    }
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $hm  = fn(string $s) => ((int) substr($s, 0, 2)) * 60 + (int) substr($s, 3, 2);
    $day = fn(array $ps) => latepoint_agent_fees_day_schedules(
        array_map(fn($p) => [$hm($p[0]), $hm($p[1])], $ps)
    );

    $eq = fn(float $a, float $b) => abs($a - $b) < 1e-9;

    assert($eq($day([])['schedules'], 0));
    assert($eq($day([['09:00', '20:00']])['schedules'], 2));
    assert($eq($day([['09:00', '14:00']])['schedules'], 1));
    assert($eq($day([['10:00', '14:00']])['schedules'], 1));           // 4h/5h = 80% of shift 1: full fee
    assert($eq($day([['10:30', '14:00']])['schedules'], 0.7));         // 3.5h/5h = 70%: coefficient
    assert($eq($day([['14:00', '17:00']])['schedules'], 0.5));         // 3h/6h = 50% of shift 2
    assert($eq($day([['14:00', '18:48']])['schedules'], 1));           // 4.8h/6h = 80% of shift 2
    $combined = $day([['12:00', '16:00']]);                            // spans both shifts, 4h total
    assert($eq($combined['schedules'], 1) && $combined['combined']);   // combined beats 0.4 + 0.33
    assert($eq($day([['09:00', '13:00'], ['15:00', '20:00']])['schedules'], 2)); // both hit 80% separately
    assert($eq($day([['09:00', '16:00']])['schedules'], 1 + 2 / 6));   // full shift 1 + coefficient on shift 2
    assert($day([['09:00', '10:00'], ['09:30', '13:30']])['covered'][0] === 270); // overlap merged
    assert($eq($day([['00:00', '00:00']])['schedules'], 0));           // explicit day off
    assert($eq($day([['06:00', '08:00']])['schedules'], 0));           // outside window

    echo "fees_math self-check OK\n";
}
