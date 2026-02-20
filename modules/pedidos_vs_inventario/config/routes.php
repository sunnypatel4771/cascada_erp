<?php
defined('BASEPATH') or exit('No direct script access allowed');

$route['admin/pedidos_vs_inventario'] = 'pedidos_vs_inventario/pedidos_vs_inventario/index';
$route['admin/pedidos_vs_inventario/index'] = 'pedidos_vs_inventario/pedidos_vs_inventario/index';

$route['admin/pedidos_vs_inventario/vendor_priority'] = 'pedidos_vs_inventario/pedidos_vs_inventario/vendor_priority';
$route['admin/pedidos_vs_inventario/vendor_priority_save'] = 'pedidos_vs_inventario/pedidos_vs_inventario/vendor_priority_save';

$route['admin/pedidos_vs_inventario/generate_po'] = 'pedidos_vs_inventario/pedidos_vs_inventario/generate_po';
$route['admin/pedidos_vs_inventario/generate_po_selected'] = 'pedidos_vs_inventario/pedidos_vs_inventario/generate_po_selected';
$route['admin/pedidos_vs_inventario/export_csv'] = 'pedidos_vs_inventario/pedidos_vs_inventario/export_csv';

$route['admin/pedidos_vs_inventario/vendor_item_price_get'] = 'pedidos_vs_inventario/pedidos_vs_inventario/vendor_item_price_get';
$route['admin/pedidos_vs_inventario/vendor_item_price_save'] = 'pedidos_vs_inventario/pedidos_vs_inventario/vendor_item_price_save';
