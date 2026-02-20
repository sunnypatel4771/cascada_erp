<?php
defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

// Remove option
if (function_exists('delete_option')) {
    delete_option('gps_target_url');
}

// Drop table
$table = db_prefix() . 'gps_access_log';
if ($CI->db->table_exists($table)) {
    $CI->db->query('DROP TABLE `' . $table . '`;');
}
