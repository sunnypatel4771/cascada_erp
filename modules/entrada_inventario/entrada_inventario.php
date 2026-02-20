<?php
defined('BASEPATH') or exit('No direct script access allowed');
/*
Module Name: Entrada Inventario
Description: Entrada a inventario desde Orden de Compra (RAMOS01 warehouse_id=1). UI alineada a Perfex + Proveedor en encabezado + movimientos tblentrada_inventory_moves (source='oc').
Version: 1.0.0
Requires at least: 3.3.0
Author: EREISE
*/
define('ENTRADA_INVENTARIO_MODULE_NAME', 'entrada_inventario');

hooks()->add_action('admin_init', 'entrada_inventario_init_menu_items');
function entrada_inventario_init_menu_items()
{
    $CI = &get_instance();
    if (is_staff_member()) {
        $CI->app_menu->add_sidebar_menu_item('entrada_inventario', [
            'name'     => _l('Entrada Inventario'),
            'href'     => admin_url('entrada_inventario'),
            'position' => 55,
            'icon'     => 'fa fa-sign-in'
        ]);
    }
}
