<?php
defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

// IMPORTANT: Never fail activation hard (blank/no response in UI). Do best-effort installs.

// Default target URL option
try {
    if (function_exists('add_option')) {
        add_option('gps_target_url', defined('GPS_DEFAULT_URL') ? GPS_DEFAULT_URL : 'http://176.57.189.120', 1);
    }
} catch (Exception $e) {
    // ignore
}

// Create access log table
try {
    if (!function_exists('db_prefix')) {
        return;
    }

    $table = db_prefix() . 'gps_access_log';
    if (!$CI->db->table_exists($table)) {
        $charset = isset($CI->db->char_set) ? $CI->db->char_set : 'utf8';
        $CI->db->query('CREATE TABLE `' . $table . "` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `staff_id` int(11) NOT NULL DEFAULT 0,
            `accessed_at` datetime NOT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `user_agent` varchar(191) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `staff_id` (`staff_id`),
            KEY `accessed_at` (`accessed_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
    }
} catch (Exception $e) {
    // ignore
}
