<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Facturacion
Module Name (System): facturacion
Description: Modulo de Facturacion
Version: 1.1.2
Author: EREISE
Requires at least: 3.3.0
*/

hooks()->add_action('admin_init', 'facturacion_admin_init');

function facturacion_admin_init()
{
    $CI = &get_instance();

    if (is_admin()) {

        // Main operational entry (Pedidos list + Generate invoice lives here)
        $CI->app_menu->add_sidebar_menu_item('facturacion', [
            'name'     => 'Facturación',
            'href'     => admin_url('facturacion'),
            'icon'     => 'fa fa-file-text-o',
            'position' => 35,
        ]);

        // Extra direct entry (same page) so users always see an action-oriented label
        $CI->app_menu->add_sidebar_menu_item('facturacion_generar', [
            'name'     => 'Generar factura',
            'href'     => admin_url('facturacion'),
            'icon'     => 'fa fa-bolt',
            'position' => 36,
        ]);
        // Nota: Configuración sigue existiendo en /admin/facturacion/settings pero NO se muestra en el menú.
    }
}
