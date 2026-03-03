<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Settings Controller for Ramos Module
 *
 * Manages scheduled automation configuration
 */
class Settings extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }
    }

    /**
     * Scheduled automation settings page
     */
    public function automation_schedule(): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        // Handle form submission
        if ($this->input->post()) {
            $this->_save_automation_schedule_settings();
            return;
        }

        // Load current settings
        $data['title']    = _l('ramos_settings_automation_schedule_title');
        $data['subtitle'] = _l('ramos_settings_automation_schedule_subtitle');

        // Automation schedule settings
        $data['automation_enabled'] = get_option('ramos_automation_schedule_enabled') === '1';
        
        $hoursJson = get_option('ramos_automation_schedule_hours', json_encode([8, 14, 18]));
        $data['schedule_hours'] = json_decode($hoursJson, true);
        if (!is_array($data['schedule_hours'])) {
            $data['schedule_hours'] = [8, 14, 18];
        }

        // Route generation settings
        $data['route_generation_auto'] = get_option('ramos_route_generate_on_success') === '1';
        $data['default_max_stops'] = (int)get_option('ramos_default_max_stops', 10);
        $data['default_route_prefix'] = get_option('ramos_default_route_prefix', 'Route');
        $data['default_route_start_time'] = get_option('ramos_default_route_start_time', '08:00:00');

        // Last run info
        $data['last_automation_run_date'] = get_option('ramos_last_automation_run_date') ?: _l('ramos_settings_never_run');

        // Available hours for selection
        $data['available_hours'] = array_combine(range(0, 23), range(0, 23));

        $this->load->view('settings/automation_schedule', $data);
    }

    /**
     * Save automation schedule settings
     */
    private function _save_automation_schedule_settings(): void
    {
        // Validate CSRF
        if (!$this->input->is_ajax_request()) {
            show_error(_l('access_denied'));
        }

        try {
            // Automation enabled/disabled
            $automationEnabled = $this->input->post('automation_enabled') === 'on' ? '1' : '0';
            update_option('ramos_automation_schedule_enabled', $automationEnabled);

            // Schedule hours - comma-separated values converted to JSON array
            if ($automationEnabled === '1') {
                $hoursInput = $this->input->post('schedule_hours');
                if (is_string($hoursInput)) {
                    $hoursInput = explode(',', $hoursInput);
                }
                
                // Validate and convert to integers
                $hours = array_filter(array_map(function ($h) {
                    $h = (int)trim($h);
                    return ($h >= 0 && $h <= 23) ? $h : null;
                }, $hoursInput));

                if (empty($hours)) {
                    $hours = [8, 14, 18]; // Default if empty
                }

                update_option('ramos_automation_schedule_hours', json_encode(array_values($hours)));
            }

            // Route generation auto-generate on success
            $routeGenAuto = $this->input->post('route_generation_auto') === 'on' ? '1' : '0';
            update_option('ramos_route_generate_on_success', $routeGenAuto);

            // Default max stops per route
            $maxStops = (int)$this->input->post('default_max_stops');
            $maxStops = max(1, min(100, $maxStops)); // Between 1-100
            update_option('ramos_default_max_stops', (string)$maxStops);

            // Default route prefix
            $routePrefix = trim((string)$this->input->post('default_route_prefix'));
            $routePrefix = $routePrefix ?: 'Route';
            update_option('ramos_default_route_prefix', $routePrefix);

            // Default route start time
            $startTime = trim((string)$this->input->post('default_route_start_time'));
            if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime)) {
                $startTime = '08:00:00';
            } else {
                // Ensure HH:MM:SS format
                $parts = explode(':', $startTime);
                $startTime = sprintf('%02d:%02d:%02d', (int)$parts[0], (int)$parts[1], isset($parts[2]) ? (int)$parts[2] : 0);
            }
            update_option('ramos_default_route_start_time', $startTime);

            log_activity('Ramos scheduled automation settings updated');

            echo json_encode([
                'success' => true,
                'message' => _l('ramos_settings_saved_successfully')
            ]);

        } catch (Exception $e) {
            log_activity('Error saving ramos settings: ' . $e->getMessage());

            echo json_encode([
                'success' => false,
                'message' => _l('ramos_settings_save_error'),
                'error'   => $e->getMessage()
            ]);
        }
    }
}
