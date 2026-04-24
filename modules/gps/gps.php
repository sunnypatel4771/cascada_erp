<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: GPS
Description: Módulo GPS 3.3 con dashboard, acceso al sitio externo, estado de conectividad y bitácora de accesos.
Version: 1.0.6
Requires at least: 3.3.*
Author: EREISE
*/

define('GPS_MODULE_NAME', 'gps');

hooks()->add_action('admin_init', 'gps_module_init_menu');

register_activation_hook(GPS_MODULE_NAME, 'gps_module_activate');
register_deactivation_hook(GPS_MODULE_NAME, 'gps_module_deactivate');
register_language_files(GPS_MODULE_NAME, ['gps']);

function gps_module_activate()
{
    try {
        require_once(__DIR__ . '/install.php');
    } catch (Exception $e) {
        log_activity('GPS module activation warning: ' . $e->getMessage());
    }
}

function gps_module_deactivate()
{
}

function gps_module_init_menu()
{
    $CI = &get_instance();

    if (has_permission('settings', '', 'view') || is_admin()) {
        $CI->app_menu->add_sidebar_menu_item('gps', [
            'slug'     => 'gps',
            'name'     => _l('gps_menu'),
            'icon'     => 'fa fa-location-arrow',
            'href'     => admin_url('gps'),
            'position' => 35,
        ]);
    }
}
