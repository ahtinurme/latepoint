<?php
/*
 * Pure schedule-fee math, no WordPress dependencies.
 * Shifts: 9:00-14:00 and 14:00-20:00 (minutes since midnight). A shift counts as an
 * opened schedule when at least 80% of it is covered by the agent's working hours.
 * If neither shift reaches 80% on its own, the day still counts as ONE combined
 * schedule when the total working time within 9:00-20:00 reaches 80% of the
 * shortest shift (240 min) - e.g. working 12:00-16:00 counts as one schedule.
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
     *     schedules: int,
     *     shift_counts: array<int, int>,
     *     combined: bool,
     *     covered: array<int, int>,
     *     hits: array<int, bool>,
     *     total: int
     * } covered/total are minutes; total is clipped to the 9:00-20:00 window. A combined
     *   schedule is billed under the shift with the most covered minutes (shift_counts).
     */
    function latepoint_agent_fees_day_schedules(array $periods): array {
        $shifts       = LATEPOINT_AGENT_FEES_SHIFTS;
        $merged       = latepoint_agent_fees_merge($periods);
        $covered      = [];
        $hits         = [];
        $shift_counts = [];

        foreach ($shifts as $i => [$from, $to]) {
            $covered[$i]      = latepoint_agent_fees_overlap($merged, $from, $to);
            $hits[$i]         = $covered[$i] * 5 >= ($to - $from) * 4;
            $shift_counts[$i] = $hits[$i] ? 1 : 0;
        }

        $window   = [min(array_column($shifts, 0)), max(array_column($shifts, 1))];
        $total    = latepoint_agent_fees_overlap($merged, $window[0], $window[1]);
        $combined = false;

        // ponytail: combined threshold = 80% of the shortest shift (240 min)
        $threshold = (int) ceil(min(array_map(fn($s) => $s[1] - $s[0], $shifts)) * 0.8);
        if (array_sum($shift_counts) === 0 && $total >= $threshold) {
            $combined = true;
            $shift_counts[array_search(max($covered), $covered)] = 1;
        }

        return [
            'schedules'    => array_sum($shift_counts),
            'shift_counts' => $shift_counts,
            'combined'     => $combined,
            'covered'      => $covered,
            'hits'         => $hits,
            'total'        => $total,
        ];
    }
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $hm  = fn(string $s) => ((int) substr($s, 0, 2)) * 60 + (int) substr($s, 3, 2);
    $day = fn(array $ps) => latepoint_agent_fees_day_schedules(
        array_map(fn($p) => [$hm($p[0]), $hm($p[1])], $ps)
    );

    assert($day([])['schedules'] === 0);
    assert($day([['09:00', '20:00']])['schedules'] === 2);
    assert($day([['09:00', '14:00']])['schedules'] === 1);
    assert($day([['10:00', '14:00']])['schedules'] === 1);          // 4h/5h = 80% of shift 1
    assert($day([['10:30', '14:00']])['schedules'] === 0);          // 3.5h/5h = 70%, under combined 4h
    assert($day([['14:00', '18:48']])['schedules'] === 1);          // 4.8h/6h = 80% of shift 2
    $combined = $day([['12:00', '16:00']]);                         // spans both shifts, 4h total
    assert($combined['schedules'] === 1 && $combined['combined']);
    assert($combined['shift_counts'] === [1, 0]);                   // 2h in each; tie goes to shift 1
    assert($day([['12:30', '16:30']])['shift_counts'] === [0, 1]);  // more minutes in shift 2
    assert($day([['09:00', '13:00'], ['15:00', '20:00']])['schedules'] === 2); // both hit 80% separately
    assert($day([['09:00', '14:00']])['shift_counts'] === [1, 0]);
    assert($day([['09:00', '10:00'], ['09:30', '13:30']])['covered'][0] === 270); // overlap merged
    assert($day([['00:00', '00:00']])['schedules'] === 0);          // explicit day off
    assert($day([['06:00', '08:00']])['schedules'] === 0);          // outside window

    echo "fees_math self-check OK\n";
}
