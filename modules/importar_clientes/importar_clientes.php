<?php defined('BASEPATH') or exit('No direct script access allowed');
/*
Module Name: Importar Clientes
Description: Importa clientes desde CSV o XLS/XLSX (sin encabezados) con vista previa, validaciones, mapeo de campos personalizados y modo actualizar.
Version: 1.5
Requires at least: 3.3
Author: EREISE
*/

define('IMPORTAR_CLIENTES_MODULE_NAME', 'importar_clientes');

hooks()->add_action('admin_init', 'importar_clientes_admin_init');

function importar_clientes_admin_init()
{
    $CI = &get_instance();

    $CI->lang->load('importar_clientes', 'spanish', false, true, APP_MODULES_PATH . IMPORTAR_CLIENTES_MODULE_NAME . '/');

    if (has_permission('customers', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('importar_clientes_menu', [
            'name'     => _l('importar_clientes_menu'),
            'href'     => admin_url('importar_clientes'),
            'icon'     => 'fa fa-upload',
            'position' => 35,
        ]);
    }
}
