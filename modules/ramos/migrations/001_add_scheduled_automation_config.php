<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Add_scheduled_automation_config extends CI_Migration
{
    /**
     * Run migrations for scheduled automation and route generation
     */
    public function up()
    {
        // Add configuration options
        $CI = &get_instance();

        // Scheduled automation enabled/disabled flag
        add_option('ramos_automation_schedule_enabled', '0');

        // Schedule hours (JSON array of hours: [8, 14, 18])
        add_option('ramos_automation_schedule_hours', json_encode([8, 14, 18]));

        // Route generation configuration
        add_option('ramos_default_max_stops', '10');
        add_option('ramos_default_route_prefix', 'Route');
        add_option('ramos_route_generate_on_success', '1');

        // Track last successful automation run date (to prevent duplicate runs same day)
        add_option('ramos_last_automation_run_date', '');

        // Default route start time
        add_option('ramos_default_route_start_time', '08:00:00');

        // Add columns to tblramos_automation_runs table
        $table = db_prefix() . 'ramos_automation_runs';
        if ($CI->db->table_exists($table)) {
            $CI->db->query("
                ALTER TABLE " . $table . "
                ADD COLUMN IF NOT EXISTS cron_scheduled TINYINT(1) DEFAULT 0,
                ADD COLUMN IF NOT EXISTS routes_generated_count INT DEFAULT 0
            ");
        }

        return true;
    }

    /**
     * Revert migrations
     */
    public function down()
    {
        $CI = &get_instance();

        // Remove options
        delete_option('ramos_automation_schedule_enabled');
        delete_option('ramos_automation_schedule_hours');
        delete_option('ramos_default_max_stops');
        delete_option('ramos_default_route_prefix');
        delete_option('ramos_route_generate_on_success');
        delete_option('ramos_last_automation_run_date');
        delete_option('ramos_default_route_start_time');

        // Remove columns from table (if they exist)
        $table = db_prefix() . 'ramos_automation_runs';
        if ($CI->db->table_exists($table)) {
            if ($CI->db->field_exists('cron_scheduled', $table)) {
                $CI->db->query("ALTER TABLE " . $table . " DROP COLUMN cron_scheduled");
            }

            if ($CI->db->field_exists('routes_generated_count', $table)) {
                $CI->db->query("ALTER TABLE " . $table . " DROP COLUMN routes_generated_count");
            }
        }

        return true;
    }
}
