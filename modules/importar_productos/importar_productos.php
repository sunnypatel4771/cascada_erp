<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Importar Productos
Description: Importa productos a tblitems desde CSV/XLS/XLSX (con encabezados), con mapeo flexible, simulación y validaciones.
Version: 1.0.0
Requires at least: 3.3
*/

define('IMPORTAR_PRODUCTOS_MODULE_NAME', 'importar_productos');

register_language_files(IMPORTAR_PRODUCTOS_MODULE_NAME, [IMPORTAR_PRODUCTOS_MODULE_NAME]);

hooks()->add_action('admin_init', 'importar_productos_admin_init', 100);

function importar_productos_admin_init(): void
{
    $CI = &get_instance();
    $CI->lang->load('importar_productos', 'spanish', false, true, APP_MODULES_PATH . IMPORTAR_PRODUCTOS_MODULE_NAME . '/');

    $email = null;
    if (function_exists('get_staff')) {
        $staff = get_staff();
        if ($staff && isset($staff->email)) {
            $email = (string) $staff->email;
        }
    }

    if (!staff_can('create', 'items') && $email !== 'developer@3ware.mx') {
        return;
    }

    if ($CI->app_modules->is_active('ramos')) {
        $CI->app_menu->add_sidebar_children_item('ramos-dashboard', [
            'slug'     => 'importar-productos',
            'name'     => _l('importar_productos_menu'),
            'href'     => admin_url('importar_productos'),
            'icon'     => 'fa fa-file-upload',
            'position' => 14,
        ]);
    } else {
        $CI->app_menu->add_sidebar_menu_item('importar-productos-menu', [
            'name'     => _l('importar_productos_menu'),
            'href'     => admin_url('importar_productos'),
            'icon'     => 'fa fa-file-upload',
            'position' => 36,
        ]);
    }
}
