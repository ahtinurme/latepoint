<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('OsAvailabilityController')) :

    class OsAvailabilityController extends OsController {

        public function __construct() {
            parent::__construct();
            $this->views_folder          = plugin_dir_path(__FILE__) . '../views/availability/';
            $this->vars['page_header']   = __('Agent Availability', 'latepoint-availability');
            $this->vars['breadcrumbs'][] = [
                'label' => __('Availability', 'latepoint-availability'),
                'link'  => false,
            ];
        }

        public function index() {
            $monday = $this->week_monday($this->params['week_start'] ?? '');
            $dates  = $this->week_dates($monday);

            $agents = (new OsAgentModel())
                ->where(['status' => LATEPOINT_AGENT_STATUS_ACTIVE])
                ->order_by('first_name asc, last_name asc')
                ->get_results_as_models();
            $agents = is_array($agents) ? $agents : [];

            $this->vars['agents']     = $agents;
            $this->vars['dates']      = $dates;
            $this->vars['week_start'] = $monday->format('Y-m-d');
            $this->vars['prev_week']  = (clone $monday)->modify('-7 days')->format('Y-m-d');
            $this->vars['next_week']  = (clone $monday)->modify('+7 days')->format('Y-m-d');
            $this->vars['grid']       = $this->build_grid($agents, $dates);
            $this->vars['saved']      = !empty($this->params['saved']);

            $this->format_render('index');
        }

        public function save() {
            $this->check_nonce('save_availability');

            $submitted      = (isset($this->params['availability']) && is_array($this->params['availability'])) ? $this->params['availability'] : [];
            $default_weekly = $this->weekly_by_day(0);

            foreach ($submitted as $agent_id => $dates) {
                $agent_id = (int) $agent_id;
                if (!$agent_id || !is_array($dates)) {
                    continue;
                }
                $specific_weekly = $this->weekly_by_day($agent_id);
                foreach ($dates as $date => $data) {
                    if (empty($data['present'])) {
                        continue;
                    }
                    $day = DateTime::createFromFormat('Y-m-d', $date);
                    if (!$day || $day->format('Y-m-d') !== $date) {
                        continue;
                    }
                    $week_day      = (int) $day->format('N');
                    $baseline_set  = $this->periods_to_set($this->baseline_periods($specific_weekly, $default_weekly, $week_day));
                    $submitted_set = $this->submitted_to_set($data['periods'] ?? []);
                    $this->save_date($agent_id, $date, $week_day, $submitted_set, $baseline_set);
                }
            }

            wp_safe_redirect(OsRouterHelper::build_link(['availability', 'index'], [
                'week_start' => $this->params['week_start'] ?? '',
                'saved'      => 1,
            ]));
            exit;
        }

        /**
         * Persist an agent's availability for one specific date. We store a dated override
         * only when it differs from the recurring weekly schedule; matching the weekly hours
         * removes any override so the date keeps following the schedule. An empty override
         * (no periods) is stored as a single 0/0 row, i.e. an explicit day off for that date.
         *
         * @param array<int, array{0:int, 1:int}> $submitted_set sorted active periods
         * @param array<int, array{0:int, 1:int}> $baseline_set  sorted active weekly periods
         */
        private function save_date(int $agent_id, string $date, int $week_day, array $submitted_set, array $baseline_set): void {
            $existing = (new OsWorkPeriodModel())->where([
                'agent_id'    => $agent_id,
                'service_id'  => 0,
                'location_id' => 0,
                'custom_date' => $date,
            ])->get_results_as_models();
            foreach (is_array($existing) ? $existing : [] as $row) {
                $row->delete();
            }

            if ($this->set_key($submitted_set) === $this->set_key($baseline_set)) {
                return;
            }

            if (empty($submitted_set)) {
                $this->insert_period($agent_id, $date, $week_day, 0, 0);
                return;
            }

            foreach ($submitted_set as [$start, $end]) {
                $this->insert_period($agent_id, $date, $week_day, $start, $end);
            }
        }

        private function insert_period(int $agent_id, string $date, int $week_day, int $start, int $end): void {
            $period = new OsWorkPeriodModel();
            $period->set_data([
                'agent_id'    => $agent_id,
                'service_id'  => 0,
                'location_id' => 0,
                'week_day'    => $week_day,
                'custom_date' => $date,
                'start_time'  => $start,
                'end_time'    => $end,
            ]);
            $period->save();
        }

        /**
         * @param OsAgentModel[]    $agents
         * @param array<int, array> $dates
         */
        private function build_grid(array $agents, array $dates): array {
            $default_weekly = $this->weekly_by_day(0);
            $grid           = [];
            foreach ($agents as $agent) {
                $specific_weekly = $this->weekly_by_day($agent->id);
                foreach ($dates as $d) {
                    $custom    = $this->periods_for_date($agent->id, $d['date']);
                    $inherited = empty($custom);
                    $source    = $inherited ? $this->baseline_periods($specific_weekly, $default_weekly, $d['week_day']) : $custom;
                    $grid[$agent->id][$d['date']] = [
                        'inherited' => $inherited,
                        'periods'   => $this->periods_to_strings($source),
                    ];
                }
            }
            return $grid;
        }

        /**
         * Recurring weekly periods for a weekday: the agent's own if set, otherwise the
         * global default schedule (agent_id 0). Mirrors how LatePoint resolves availability.
         *
         * @param array<int, OsWorkPeriodModel[]> $specific_weekly
         * @param array<int, OsWorkPeriodModel[]> $default_weekly
         * @return OsWorkPeriodModel[]
         */
        private function baseline_periods(array $specific_weekly, array $default_weekly, int $week_day): array {
            if (isset($specific_weekly[$week_day])) {
                return $specific_weekly[$week_day];
            }
            return $default_weekly[$week_day] ?? [];
        }

        /** @return array<int, OsWorkPeriodModel[]> recurring weekly periods grouped by week_day */
        private function weekly_by_day(int $agent_id): array {
            $rows = (new OsWorkPeriodModel())->where([
                'agent_id'    => $agent_id,
                'service_id'  => 0,
                'location_id' => 0,
                'custom_date' => 'IS NULL',
            ])->order_by('week_day asc, start_time asc')->get_results_as_models();

            $by_day = [];
            foreach (is_array($rows) ? $rows : [] as $row) {
                $by_day[$row->week_day][] = $row;
            }
            return $by_day;
        }

        /** @return OsWorkPeriodModel[] dated periods for one exact date */
        private function periods_for_date(int $agent_id, string $date): array {
            $rows = (new OsWorkPeriodModel())->where([
                'agent_id'    => $agent_id,
                'service_id'  => 0,
                'location_id' => 0,
                'custom_date' => $date,
            ])->order_by('start_time asc')->get_results_as_models();
            return is_array($rows) ? $rows : [];
        }

        /**
         * @param OsWorkPeriodModel[] $periods
         * @return array<int, array{start:string, end:string}> active periods as HH:MM strings, sorted
         */
        private function periods_to_strings(array $periods): array {
            $out = [];
            foreach ($this->periods_to_set($periods) as [$start, $end]) {
                $out[] = ['start' => $this->m2hm($start), 'end' => $this->m2hm($end)];
            }
            return $out;
        }

        /**
         * @param OsWorkPeriodModel[] $periods
         * @return array<int, array{0:int, 1:int}> active periods as [start,end] minutes, sorted
         */
        private function periods_to_set(array $periods): array {
            $set = [];
            foreach ($periods as $p) {
                if ($p->start_time != $p->end_time) {
                    $set[] = [(int) $p->start_time, (int) $p->end_time];
                }
            }
            return $this->sort_set($set);
        }

        /**
         * @param array $submitted raw [i => ['start' => 'HH:MM', 'end' => 'HH:MM']]
         * @return array<int, array{0:int, 1:int}> valid periods as [start,end] minutes, sorted, deduped
         */
        private function submitted_to_set($submitted): array {
            if (!is_array($submitted)) {
                return [];
            }
            $set = [];
            foreach ($submitted as $row) {
                $start = $this->hm_to_minutes($row['start'] ?? '');
                $end   = $this->hm_to_minutes($row['end'] ?? '');
                if ($start === null || $end === null || $start >= $end) {
                    continue;
                }
                $set["{$start}-{$end}"] = [$start, $end];
            }
            return $this->sort_set(array_values($set));
        }

        /**
         * @param array<int, array{0:int, 1:int}> $set
         * @return array<int, array{0:int, 1:int}>
         */
        private function sort_set(array $set): array {
            usort($set, fn($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
            return $set;
        }

        /** @param array<int, array{0:int, 1:int}> $set */
        private function set_key(array $set): string {
            return implode(',', array_map(fn($p) => "{$p[0]}-{$p[1]}", $set));
        }

        private function week_monday(string $week_start): DateTime {
            if ($week_start && DateTime::createFromFormat('Y-m-d', $week_start)) {
                $date = DateTime::createFromFormat('Y-m-d', $week_start);
            } else {
                $date = new DateTime(OsTimeHelper::today_date());
            }
            $date->setTime(0, 0);
            return $date->modify('monday this week');
        }

        /** @return array<int, array{date:string, week_day:int, day_name:string, day_number:string}> */
        private function week_dates(DateTime $monday): array {
            $dates = [];
            for ($i = 0; $i < 7; $i++) {
                $day     = (clone $monday)->modify("+{$i} days");
                $dates[] = [
                    'date'       => $day->format('Y-m-d'),
                    'week_day'   => (int) $day->format('N'),
                    'day_name'   => OsBookingHelper::get_weekday_name_by_number((int) $day->format('N'), true),
                    'day_number' => $day->format('j M'),
                ];
            }
            return $dates;
        }

        private function m2hm(int $minutes): string {
            return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        private function hm_to_minutes(string $value): ?int {
            if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($value), $m)) {
                return null;
            }
            $minutes = ((int) $m[1] * 60) + (int) $m[2];
            return ($minutes >= 0 && $minutes <= 1440) ? $minutes : null;
        }
    }

endif;
