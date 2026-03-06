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
        $schedule_hours = json_decode(get_option('ramos_automation_schedule_hours', json_encode([8, 14, 18])), true);
        $schedule_minutes = (int)get_option('ramos_automation_schedule_minutes', 0);
        $schedule_date = get_option('ramos_automation_schedule_date', '');
        $schedule_run_daily = get_option('ramos_automation_schedule_run_daily') === '1';
        $last_run = get_option('ramos_last_automation_run_date');

        // Build task list
        $tasks = [];
        if ($automation_enabled) {
            if ($schedule_run_daily) {
                foreach ($schedule_hours as $hour) {
                    $time = str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($schedule_minutes, 2, '0', STR_PAD_LEFT);
                    $tasks[] = [
                        'name' => 'Automation',
                        'schedule' => 'Daily at ' . $time,
                        'next_run' => $this->_calculate_next_run($hour, $schedule_minutes)
                    ];
                }
            } else {
                foreach ($schedule_hours as $hour) {
                    $time = str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($schedule_minutes, 2, '0', STR_PAD_LEFT);
                    $tasks[] = [
                        'name' => 'Automation',
                        'schedule' => $schedule_date . ' at ' . $time,
                        'next_run' => $this->_calculate_next_run($hour, $schedule_minutes, $schedule_date)
                    ];
                }
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'enabled' => $automation_enabled,
            'tasks' => $tasks,
            'last_run' => $last_run ?: 'Never',
            'current_time' => date('Y-m-d H:i:s')
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
     * Register scheduled tasks with Laravel scheduler
     */
    private function _register_scheduled_tasks(Schedule $schedule): void
    {
        // Check if automation is enabled
        if (get_option('ramos_automation_schedule_enabled') !== '1') {
            return;
        }

        // Load helper with automation functions
        $this->load->helper('ramos/ramos_automation');

        // Get schedule configuration
        $schedule_hours = json_decode(get_option('ramos_automation_schedule_hours', json_encode([8, 14, 18])), true);
        $schedule_minutes = (int)get_option('ramos_automation_schedule_minutes', 0);
        $schedule_date = get_option('ramos_automation_schedule_date', '');
        $schedule_run_daily = get_option('ramos_automation_schedule_run_daily') === '1';

        // Validate configuration
        if (empty($schedule_hours)) {
            log_activity('[SCHEDULER] No hours configured for automation');
            return;
        }

        // Register tasks for each hour
        foreach ($schedule_hours as $hour) {
            $hour = (int)$hour;
            if ($hour < 0 || $hour > 23) {
                continue;
            }

            $time = str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($schedule_minutes, 2, '0', STR_PAD_LEFT);

            if ($schedule_run_daily) {
                // Daily automation at specified time
                $schedule->call(function () {
                    $this->_execute_automation();
                })->dailyAt($time)->name('ramos_automation_' . $time);
            } else {
                // Specific day only
                $schedule->call(function () {
                    $this->_execute_automation();
                })->{$this->_get_cron_method($schedule_date)}()->at($time)->name('ramos_automation_' . $schedule_date . '_' . $time);
            }
        }

        log_activity('[SCHEDULER] Registered ' . count($schedule_hours) . ' automation task(s)');
    }

    /**
     * Execute automation and routes
     */
    private function _execute_automation(): void
    {
        try {
            // Check if already ran today
            $last_run_date = get_option('ramos_last_automation_run_date');
            $today = date('Y-m-d');

            if ($last_run_date === $today) {
                log_activity('[SCHEDULER] Automation already ran today at ' . $last_run_date);
                return;
            }

            // Execute automation as system user
            $result = ramos_execute_automation(0);

            if ($result['success']) {
                // Generate routes if enabled
                if (get_option('ramos_route_generate_on_success') === '1') {
                    $route_result = ramos_generate_routes_for_today();
                    $route_count = $route_result['success'] ? count($route_result['route_ids']) : 0;
                    log_activity('[SCHEDULER] Automation successful: ' . $result['orders_processed'] . ' orders, ' . $result['batches_created'] . ' batches, ' . $route_count . ' routes generated');
                } else {
                    log_activity('[SCHEDULER] Automation successful: ' . $result['orders_processed'] . ' orders, ' . $result['batches_created'] . ' batches');
                }

                // Mark as ran today
                update_option('ramos_last_automation_run_date', $today);
            } else {
                log_activity('[SCHEDULER] Automation failed: ' . $result['message']);
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
