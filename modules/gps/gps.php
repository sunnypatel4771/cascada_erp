<?php
/**
 * Prevent direct access.
 */
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: GPS
Description: Adds an admin menu item that opens an external URL in an iframe (with safe fallback) and logs access.
Version: 1.0.3
Requires at least: 3.3.*
*/

define('GPS_MODULE_NAME', 'gps');

define('GPS_DEFAULT_URL', 'http://176.57.189.120');

register_activation_hook(GPS_MODULE_NAME, 'gps_activation_hook');
register_uninstall_hook(GPS_MODULE_NAME, 'gps_uninstall_hook');

hooks()->add_action('admin_init', 'gps_init_menu_items');

// Register module language files so Perfex loads them from the module folder.
// This prevents CI from looking for application/language/english/gps_lang.php.
if (function_exists('register_language_files')) {
    register_language_files(GPS_MODULE_NAME, [GPS_MODULE_NAME]);
}

function gps_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

function gps_uninstall_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/uninstall.php');
}

function gps_init_menu_items()
{
    if (!function_exists('is_staff_logged_in') || !is_staff_logged_in()) {
        return;
    }

    $CI = &get_instance();

    // Add sidebar menu item in Admin Area
    if (isset($CI->app_menu)) {
        $CI->app_menu->add_sidebar_menu_item('gps-menu', [
            'name'     => _l('gps_menu_name'),
            'href'     => admin_url('gps'),
            'position' => 55, // near Utilities
            'icon'     => 'fa fa-map-marker',
        ]);
    }
}
