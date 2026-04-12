<?php

defined('BASEPATH') or exit('No direct script access allowed');

use Illuminate\Console\Scheduling\Schedule;

/**
 * Scheduler Controller
 * 
 * Handles Laravel Task Scheduler for autonomous automation and route generation.
 * This controller should be called every 5 minutes via cron job:
 * 
 * */5 * * * * curl -s https://yourdomain.com/scheduler/run > /dev/null 2>&1
 * 
 * The scheduler will only execute tasks at their configured times.
 */
class Scheduler extends App_Controller
{
    /**
     * Run the scheduler
     * Executes any pending scheduled tasks
     */
    public function run(): void
    {
        // Prevent direct browser access - only allow from CLI or cron with valid key
        if (!$this->_validate_scheduler_access()) {
            http_response_code(403);
            echo "Access Denied";
            return;
        }

        try {
            // Initialize Laravel scheduler
            $schedule = new Schedule();

            // Load configuration
            $this->_register_scheduled_tasks($schedule);

            // Run pending tasks
            $schedule->run();

            // Log successful execution
            log_activity('Scheduler executed successfully at ' . date('Y-m-d H:i:s'));

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Scheduler executed',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            log_activity('Scheduler error: ' . $e->getMessage(), 'ramos');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Scheduler error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get scheduler status
     * Returns information about configured tasks
     */
    public function status(): void
    {
        if (!staff_can('view', 'ramos')) {
            access_denied();
        }

        $automation_enabled = get_option('ramos_automation_schedule_enabled') === '1';
        $mode             = get_option('ramos_automation_schedule_mode', 'daily_once');
        $schedule_hour    = (int) get_option('ramos_automation_schedule_hour', 8);
        $schedule_minutes = (int) get_option('ramos_automation_schedule_minutes', 0);
        $schedule_date    = get_option('ramos_automation_schedule_date', '');
        $last_run         = get_option('ramos_last_automation_run_date');

        $tasks = [];
        if ($automation_enabled) {
            if ($mode === 'multi_daily') {
                $hours = json_decode(get_option('ramos_automation_schedule_hours', json_encode([8])), true);
                if (!is_array($hours)) {
                    $hours = [8];
                }
                foreach ($hours as $hour) {
                    $time = str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($schedule_minutes, 2, '0', STR_PAD_LEFT);
                    $tasks[] = [
                        'name'     => 'Automation',
                        'schedule' => 'Daily at ' . $time,
                        'next_run' => $this->_calculate_next_run($hour, $schedule_minutes),
                    ];
                }
            } else {
                $scheduleLabel = $mode === 'weekly_once'
                    ? ucfirst($schedule_date) . 's'
                    : 'Every day';
                $time = str_pad($schedule_hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($schedule_minutes, 2, '0', STR_PAD_LEFT);
                $tasks[] = [
                    'name'     => 'Automation',
                    'schedule' => $scheduleLabel . ' at ' . $time,
                    'next_run' => $this->_calculate_next_run($schedule_hour, $schedule_minutes, $mode === 'weekly_once' ? $schedule_date : null),
                ];
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'enabled'      => $automation_enabled,
            'mode'         => $mode,
            'tasks'        => $tasks,
            'last_run'     => $last_run ?: 'Never',
            'current_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Validate scheduler access
     * Allows: CLI, cron with valid APP_CRON_KEY, or staff with permission
     */
    private function _validate_scheduler_access(): bool
    {
        // Allow from command line
        if (defined('STDIN')) {
            return true;
        }

        // Check if valid cron key provided
        $cron_key = $this->input->get('key') ?: $this->input->post('key');
        if ($cron_key && defined('APP_CRON_KEY') && $cron_key === APP_CRON_KEY) {
            return true;
        }

        // Check if logged in staff with permission
        if (is_staff_logged_in() && staff_can('view', 'ramos')) {
            return true;
        }

        return false;
    }

    /**
     * Register scheduled tasks with Laravel scheduler.
     *
     * Reads the same option set as ramos_should_run_scheduled_automation() so
     * both execution paths (Perfex after_cron_run hook and this Laravel
     * scheduler) share one configuration model.  In practice only one path
     * should be active; the ramos_automation_runs daily-date guard prevents
     * double-execution if both happen to run on the same installation.
     */
    private function _register_scheduled_tasks(Schedule $schedule): void
    {
        if (get_option('ramos_automation_schedule_enabled') !== '1') {
            return;
        }

        $this->load->helper('ramos/ramos_automation');

        $mode    = get_option('ramos_automation_schedule_mode', 'daily_once');
        $hour    = (int) get_option('ramos_automation_schedule_hour', 8);
        $minutes = (int) get_option('ramos_automation_schedule_minutes', 0);
        $weekday = get_option('ramos_automation_schedule_date', '');

        $registeredCount = 0;

        if ($mode === 'multi_daily') {
            $hours = json_decode(get_option('ramos_automation_schedule_hours', json_encode([8])), true);
            if (!is_array($hours) || empty($hours)) {
                log_activity('[SCHEDULER] multi_daily mode but no hours configured');
                return;
            }

            foreach ($hours as $h) {
                $h = (int) $h;
                if ($h < 0 || $h > 23) {
                    continue;
                }
                $time = str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes, 2, '0', STR_PAD_LEFT);
                $schedule->call(function () {
                    $this->_execute_automation();
                })->dailyAt($time)->name('ramos_automation_' . $time);
                $registeredCount++;
            }
        } elseif ($mode === 'weekly_once') {
            if (empty($weekday)) {
                log_activity('[SCHEDULER] weekly_once mode but no weekday configured');
                return;
            }
            $time   = str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes, 2, '0', STR_PAD_LEFT);
            $method = $this->_get_cron_method($weekday);
            $schedule->call(function () {
                $this->_execute_automation();
            })->{$method}()->at($time)->name('ramos_automation_' . $weekday . '_' . $time);
            $registeredCount = 1;
        } else {
            // daily_once (default)
            $time = str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes, 2, '0', STR_PAD_LEFT);
            $schedule->call(function () {
                $this->_execute_automation();
            })->dailyAt($time)->name('ramos_automation_' . $time);
            $registeredCount = 1;
        }

        log_activity('[SCHEDULER] Registered ' . $registeredCount . ' automation task(s) for mode=' . $mode);
    }

    /**
     * Execute automation and routes (called by the Laravel scheduler).
     *
     * Uses ramos_last_automation_run_date as the idempotency key so that
     * even if both the Perfex cron hook and this scheduler path fire on the
     * same day, only one run takes effect.
     */
    private function _execute_automation(): void
    {
        try {
            $appTimezone = get_option('default_timezone');
            $nowTz = !empty($appTimezone)
                ? new DateTime('now', new DateTimeZone($appTimezone))
                : new DateTime('now');
            $today = $nowTz->format('Y-m-d');

            $last_run_date = get_option('ramos_last_automation_run_date');
            if ($last_run_date === $today) {
                log_activity('[SCHEDULER] Automation already ran today (' . $today . '), skipping');
                return;
            }

            $result = ramos_execute_automation(0);

            // Stamp immediately to prevent re-entry
            update_option('ramos_last_automation_run_date', $today);

            if ($result['success']) {
                $routeResult  = ramos_generate_routes_for_today();
                $pickResult   = ramos_assign_picking_for_all_modules();
                log_activity('[SCHEDULER] Cycle complete: ' . $result['orders_processed'] . ' orders, ' . $result['batches_created'] . ' batches, ' . $routeResult['routes_count'] . ' routes, ' . $pickResult['modules_assigned'] . ' modules');
            } else {
                log_activity('[SCHEDULER] PO step skipped: ' . $result['message']);
                $routeResult = ramos_generate_routes_for_today();
                $pickResult  = ramos_assign_picking_for_all_modules();
                log_activity('[SCHEDULER] Routes + picking: ' . $routeResult['routes_count'] . ' routes, ' . $pickResult['modules_assigned'] . ' modules');
            }
        } catch (Exception $e) {
            log_activity('[SCHEDULER] Automation error: ' . $e->getMessage());
        }
    }

    /**
     * Get cron method name for day
     */
    private function _get_cron_method($day): string
    {
        $day = strtolower($day);
        $methods = [
            'monday' => 'mondays',
            'tuesday' => 'tuesdays',
            'wednesday' => 'wednesdays',
            'thursday' => 'thursdays',
            'friday' => 'fridays',
            'saturday' => 'saturdays',
            'sunday' => 'sundays'
        ];
        return $methods[$day] ?? 'daily';
    }

    /**
     * Calculate next run time
     */
    private function _calculate_next_run($hour, $minute, $day = null): string
    {
        $now = new DateTime();
        $next = clone $now;
        $next->setTime($hour, $minute);

        if ($next <= $now) {
            $next->add(new DateInterval('P1D'));
        }

        if ($day && strtolower($day) !== 'daily') {
            // Find next occurrence of specific day
            $day_num = $this->_get_day_number($day);
            while ($next->format('N') != $day_num) {
                $next->add(new DateInterval('P1D'));
            }
        }

        return $next->format('Y-m-d H:i:s');
    }

    /**
     * Get day number (1=Monday, 7=Sunday)
     */
    private function _get_day_number($day): int
    {
        $days = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7
        ];
        return $days[strtolower($day)] ?? 1;
    }
}
