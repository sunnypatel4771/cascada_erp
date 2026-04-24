<?php
defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

if (!$CI->db->table_exists(db_prefix() . 'gps_access_logs')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "gps_access_logs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL DEFAULT 0,
        `staff_name` VARCHAR(191) NULL,
        `ip_address` VARCHAR(64) NULL,
        `user_agent` TEXT NULL,
        `target_url` TEXT NULL,
        `status` VARCHAR(50) NULL DEFAULT 'opened',
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

if (function_exists('add_option')) {
    if (get_option('gps_target_url') == '') {
        add_option('gps_target_url', 'http://176.57.189.120');
    }
}
