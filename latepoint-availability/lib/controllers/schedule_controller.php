<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('OsScheduleController')) :

    class OsScheduleController extends OsController {

        const SHIFTS = [
            'H' => ['name' => 'Hommik', 'label' => 'Hommik 9–15', 'start' => 540, 'end' => 900, 'placeholder' => '9-15'],
            'O' => ['name' => 'Õhtu', 'label' => 'Õhtu 15–20', 'start' => 900, 'end' => 1200, 'placeholder' => '15-20'],
        ];

        const INFO_EMAIL = 'info@yumefitstuudio.ee';

        public function __construct() {
            parent::__construct();
            $this->views_folder          = plugin_dir_path(__FILE__) . '../views/schedule/';
            $this->vars['page_header']   = 'Treenerite graafik';
            $this->vars['breadcrumbs'][] = ['label' => 'Graafik', 'link' => false];
        }

        public function index() {
            $month = $this->month_param();
            $first = new DateTimeImmutable("{$month}-01");
            $days_in_month = (int) $first->format('t');

            $days = [];
            for ($d = 1; $d <= $days_in_month; $d++) {
                $days[] = ['d' => $d, 'wd' => (int) $first->modify('+' . ($d - 1) . ' days')->format('N')];
            }

            $all_agents = $this->active_agents(true);
            $agents     = yumefit_schedule_filter_agents($all_agents);
            $data       = yumefit_schedule_data($month);
            $user       = OsAuthHelper::get_current_user();

            [$summary, $uncovered_per_day] = $this->build_summary($data, $agents, $days_in_month);

            $full_uncovered = count(array_filter($uncovered_per_day, fn($u) => $u >= 2));
            $half_uncovered = count(array_filter($uncovered_per_day, fn($u) => $u === 1));

            $this->vars['month']             = $month;
            $this->vars['month_title']       = yumefit_schedule_month_title($month);
            $this->vars['prev_month']        = $first->modify('-1 month')->format('Y-m');
            $this->vars['next_month']        = $first->modify('+1 month')->format('Y-m');
            $this->vars['days']              = $days;
            $this->vars['days_in_month']     = $days_in_month;
            $this->vars['agents']            = $agents;
            $this->vars['all_agents']        = $all_agents;
            $this->vars['data']              = $data;
            $this->vars['summary']           = $summary;
            $this->vars['uncovered_per_day'] = $uncovered_per_day;
            $this->vars['stats']             = [
                'full_uncovered' => $full_uncovered,
                'half_uncovered' => $half_uncovered,
                'covered'        => $days_in_month - $full_uncovered - $half_uncovered,
            ];
            $this->vars['is_admin']    = $user->backend_user_type === LATEPOINT_USER_TYPE_ADMIN;
            $this->vars['my_agent_id'] = (int) OsAuthHelper::get_logged_in_agent_id();
            $this->vars['saved']       = !empty($this->params['saved']);
            $this->vars['confirmed']   = !empty($this->params['confirmed']);

            $this->format_render('index');
        }

        public function save_availability() {
            $this->check_nonce('yumefit_schedule_availability');

            $month         = $this->month_param();
            $days_in_month = (int) (new DateTimeImmutable("{$month}-01"))->format('t');
            $user          = OsAuthHelper::get_current_user();
            $is_admin      = $user->backend_user_type === LATEPOINT_USER_TYPE_ADMIN;
            $my_agent_id   = (int) OsAuthHelper::get_logged_in_agent_id();

            $data      = yumefit_schedule_data($month);
            $submitted = (isset($this->params['availability']) && is_array($this->params['availability'])) ? $this->params['availability'] : [];
            $notify_agent_ids = [];

            foreach ($submitted as $agent_id => $block) {
                $agent_id = (int) $agent_id;
                if (!$agent_id || !is_array($block) || empty($block['present'])) {
                    continue;
                }
                if (!$is_admin && $agent_id !== $my_agent_id) {
                    continue;
                }
                foreach (array_keys(self::SHIFTS) as $shift) {
                    $days = array_map('intval', (array) ($block[$shift] ?? []));
                    $days = array_values(array_unique(array_filter($days, fn($d) => $d >= 1 && $d <= $days_in_month)));
                    sort($days);
                    $data['availability'][$agent_id][$shift] = $days;
                }
                $times = [];
                foreach (array_keys(self::SHIFTS) as $shift) {
                    foreach ((array) ($block['times'][$shift] ?? []) as $day => $raw) {
                        $day   = (int) $day;
                        $range = self::parse_range((string) $raw);
                        if ($range && in_array($day, $data['availability'][$agent_id][$shift], true)) {
                            $times[$shift][$day] = $range;
                        }
                    }
                }
                $data['availability'][$agent_id]['times'] = $times;
                $data['submitted'][$agent_id] = current_time('mysql');
                if (!$is_admin) {
                    $notify_agent_ids[] = $agent_id;
                }
            }

            update_option('yumefit_schedule_' . $month, $data, false);
            $this->notify_info($month, $data, $notify_agent_ids);

            wp_safe_redirect(OsRouterHelper::build_link(['schedule', 'index'], ['month' => $month, 'saved' => 1]));
            exit;
        }

        public function save_agents() {
            $this->check_nonce('yumefit_schedule_agents');

            if (OsAuthHelper::get_current_user()->backend_user_type !== LATEPOINT_USER_TYPE_ADMIN) {
                wp_die('Ainult administraator saab treenerite valikut muuta.');
            }

            $ids = array_map('intval', (array) ($this->params['agent_ids'] ?? []));
            update_option('yumefit_schedule_agents', array_values(array_filter($ids)), false);

            wp_safe_redirect(OsRouterHelper::build_link(['schedule', 'index'], ['month' => $this->month_param(), 'saved' => 1]));
            exit;
        }

        public function save_assignments() {
            $this->check_nonce('yumefit_schedule_assignments');

            if (OsAuthHelper::get_current_user()->backend_user_type !== LATEPOINT_USER_TYPE_ADMIN) {
                wp_die('Ainult administraator saab graafikut jaotada.');
            }

            $month         = $this->month_param();
            $days_in_month = (int) (new DateTimeImmutable("{$month}-01"))->format('t');

            $assignments = [];
            foreach (array_keys(self::SHIFTS) as $shift) {
                for ($d = 1; $d <= $days_in_month; $d++) {
                    $agent_id = (int) ($this->params['assign'][$shift][$d] ?? 0);
                    if ($agent_id) {
                        $assignments[$shift][$d] = $agent_id;
                    }
                }
            }

            $data                = yumefit_schedule_data($month);
            $data['assignments'] = $assignments;

            $confirm = !empty($this->params['confirm']);
            if ($confirm) {
                $data['confirmed_at'] = current_time('mysql');
                $agents = $this->active_agents();
                $this->write_work_periods($month, $days_in_month, $assignments, $agents, $data);
                $this->email_agents_schedule($month, $days_in_month, $assignments, $agents, $data);
            }

            update_option('yumefit_schedule_' . $month, $data, false);

            wp_safe_redirect(OsRouterHelper::build_link(['schedule', 'index'], ['month' => $month, $confirm ? 'confirmed' : 'saved' => 1]));
            exit;
        }

        /**
         * Confirmed assignments become real LatePoint availability: for every active
         * agent and every day of the month a date-specific override is written —
         * the assigned shift hours, or an explicit day off (0/0) when not assigned.
         * Jooga is untouched: it lives on service-specific work periods, these are
         * the general (service_id 0) ones.
         *
         * @param array<string, array<int, int>> $assignments [shift][day] => agent_id
         * @param OsAgentModel[]                 $agents
         */
        private function write_work_periods(string $month, int $days_in_month, array $assignments, array $agents, array $data): void {
            for ($d = 1; $d <= $days_in_month; $d++) {
                $date     = sprintf('%s-%02d', $month, $d);
                $week_day = (int) (new DateTimeImmutable($date))->format('N');

                foreach ($agents as $agent) {
                    $existing = (new OsWorkPeriodModel())->where([
                        'agent_id'    => $agent->id,
                        'service_id'  => 0,
                        'location_id' => 0,
                        'custom_date' => $date,
                    ])->get_results_as_models();
                    foreach (is_array($existing) ? $existing : [] as $row) {
                        $row->delete();
                    }

                    $periods = [];
                    foreach (self::SHIFTS as $key => $shift) {
                        if ((int) ($assignments[$key][$d] ?? 0) === (int) $agent->id) {
                            $periods[] = $data['availability'][$agent->id]['times'][$key][$d] ?? [$shift['start'], $shift['end']];
                        }
                    }
                    if (count($periods) === 2 && $periods[0][1] >= $periods[1][0]) {
                        $periods = [[$periods[0][0], max($periods[0][1], $periods[1][1])]];
                    }
                    if (empty($periods)) {
                        $periods = [[0, 0]];
                    }

                    foreach ($periods as [$start, $end]) {
                        $period = new OsWorkPeriodModel();
                        $period->set_data([
                            'agent_id'    => $agent->id,
                            'service_id'  => 0,
                            'location_id' => 0,
                            'week_day'    => $week_day,
                            'custom_date' => $date,
                            'start_time'  => $start,
                            'end_time'    => $end,
                        ]);
                        $period->save();
                    }
                }
            }
        }

        /**
         * @param array<string, array<int, int>> $assignments
         * @param OsAgentModel[]                 $agents
         */
        private function email_agents_schedule(string $month, int $days_in_month, array $assignments, array $agents, array $data): void {
            $title     = yumefit_schedule_month_title($month);
            $month_num = (int) substr($month, 5, 2);
            $letters   = [1 => 'E', 2 => 'T', 3 => 'K', 4 => 'N', 5 => 'R', 6 => 'L', 7 => 'P'];

            foreach ($agents as $agent) {
                $lines = [];
                for ($d = 1; $d <= $days_in_month; $d++) {
                    $labels = [];
                    foreach (self::SHIFTS as $key => $shift) {
                        if ((int) ($assignments[$key][$d] ?? 0) === (int) $agent->id) {
                            $range    = $data['availability'][$agent->id]['times'][$key][$d] ?? null;
                            $labels[] = $range ? $shift['name'] . ' ' . self::range_label($range) : $shift['label'];
                        }
                    }
                    if ($labels) {
                        $wd      = (int) (new DateTimeImmutable(sprintf('%s-%02d', $month, $d)))->format('N');
                        $lines[] = sprintf('%s %02d.%02d — %s', $letters[$wd], $d, $month_num, implode(' ja ', $labels));
                    }
                }

                if (empty($lines) && empty($data['submitted'][$agent->id])) {
                    continue;
                }
                if (!is_email($agent->email)) {
                    continue;
                }

                $schedule = $lines
                    ? implode("\n", $lines) . "\n\nKokku " . count($lines) . ' vahetusega päeva.'
                    : 'Sel kuul ei ole sulle vahetusi planeeritud.';

                wp_mail(
                    $agent->email,
                    "Sinu graafik on kinnitatud — {$title}",
                    "Tere, {$agent->first_name}!\n\nSinu {$title} graafik on kinnitatud:\n\n{$schedule}\n\nYumefit stuudio"
                );
            }
        }

        /** @param int[] $agent_ids agents whose availability changed (only their own submissions) */
        private function notify_info(string $month, array $data, array $agent_ids): void {
            $title = yumefit_schedule_month_title($month);
            $link  = OsRouterHelper::build_link(['schedule', 'index'], ['month' => $month]);

            foreach ($agent_ids as $agent_id) {
                $agent = new OsAgentModel($agent_id);
                $counts = [];
                foreach (self::SHIFTS as $key => $shift) {
                    $counts[] = $shift['label'] . ': ' . count($data['availability'][$agent_id][$key] ?? []) . ' päeva';
                }
                wp_mail(
                    self::INFO_EMAIL,
                    "Graafik: {$agent->full_name} sisestas saadavuse ({$title})",
                    "Treener {$agent->full_name} sisestas või uuendas oma saadavust — {$title}.\n\n" . implode("\n", $counts) . "\n\nVaata ja jaota: {$link}"
                );
            }
        }

        /**
         * @param OsAgentModel[] $agents
         * @return array{
         *     0: array<string, array<int, array{available: int[], assigned: int}>>,
         *     1: array<int, int>
         * } per-shift-per-day summary and uncovered-shift count per day
         */
        private function build_summary(array $data, array $agents, int $days_in_month): array {
            $agent_ids = array_map(fn($a) => (int) $a->id, $agents);
            $summary   = [];
            $uncovered = array_fill(1, $days_in_month, 0);

            foreach (array_keys(self::SHIFTS) as $shift) {
                for ($d = 1; $d <= $days_in_month; $d++) {
                    $available = [];
                    foreach ($agent_ids as $aid) {
                        if (in_array($d, $data['availability'][$aid][$shift] ?? [], true)) {
                            $available[] = $aid;
                        }
                    }
                    $assigned = (int) ($data['assignments'][$shift][$d] ?? 0);
                    $summary[$shift][$d] = ['available' => $available, 'assigned' => $assigned];
                    if (!$assigned && empty($available)) {
                        $uncovered[$d]++;
                    }
                }
            }
            return [$summary, $uncovered];
        }

        /** @return OsAgentModel[] */
        private function active_agents(bool $include_nonparticipating = false): array {
            $agents = (new OsAgentModel())
                ->where(['status' => LATEPOINT_AGENT_STATUS_ACTIVE])
                ->order_by('first_name asc, last_name asc')
                ->get_results_as_models();
            $agents = is_array($agents) ? $agents : [];
            return $include_nonparticipating ? $agents : yumefit_schedule_filter_agents($agents);
        }

        /**
         * Parses "10-14", "10:30-14" or "10.30-14.15" into minutes.
         *
         * @return ?array{0: int, 1: int}
         */
        public static function parse_range(string $raw): ?array {
            if (!preg_match('/^\s*(\d{1,2})(?:[:.](\d{2}))?\s*[-–]\s*(\d{1,2})(?:[:.](\d{2}))?\s*$/u', $raw, $m)) {
                return null;
            }
            $start = (int) $m[1] * 60 + (int) ($m[2] ?? 0);
            $end   = (int) $m[3] * 60 + (int) ($m[4] ?? 0);
            return ($start < $end && $end <= 1440) ? [$start, $end] : null;
        }

        /** @param array{0: int, 1: int} $range */
        public static function range_label(array $range): string {
            $fmt = fn(int $t) => intdiv($t, 60) . ($t % 60 ? sprintf(':%02d', $t % 60) : '');
            return $fmt($range[0]) . '–' . $fmt($range[1]);
        }

        private function month_param(): string {
            $month = (string) ($this->params['month'] ?? '');
            if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
                return $month;
            }
            return (new DateTimeImmutable('first day of next month', wp_timezone()))->format('Y-m');
        }
    }

endif;
