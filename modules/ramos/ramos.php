<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Ramos Operations
Description: Core workflow hub for the Ramos delivery and logistics automation.
Version: 1.0.0
Requires at least: 3.0.*
Author: Sanjay Kumar
*/

define('RAMOS_MODULE_NAME', 'ramos');
define('RAMOS_MODULE_ICON', 'fa-solid fa-truck-fast');

hooks()->add_action('admin_init', 'ramos_register_permissions');
hooks()->add_action('admin_init', 'ramos_init_admin_menu');
hooks()->add_action('admin_init', 'ramos_run_initial_setup');
hooks()->add_action('clients_init', 'ramos_init_client_portal');
hooks()->add_action('after_cron_run', 'ramos_scheduled_automation_and_routes');

register_activation_hook(RAMOS_MODULE_NAME, 'ramos_module_activation_hook');
register_language_files(RAMOS_MODULE_NAME, [RAMOS_MODULE_NAME]);

define('RAMOS_ORDER_STATUS_NEW', 'new');
define('RAMOS_ORDER_STATUS_PROCESSING', 'processing');
define('RAMOS_ORDER_STATUS_READY', 'ready');

define('RAMOS_PRIORITY_LOW', 'low');
define('RAMOS_PRIORITY_NORMAL', 'normal');
define('RAMOS_PRIORITY_HIGH', 'high');

define('RAMOS_ROUTE_DELAY_THRESHOLD_MINUTES', 30);
define('RAMOS_PURCHASE_DELAY_THRESHOLD_HOURS', 6);

/**
 * Run on module activation.
 *
 * @return void
 */
function ramos_module_activation_hook(): void
{
    add_option('ramos_module_initialized_at', date('Y-m-d H:i:s'));
    require_once(__DIR__ . '/install.php');
}

/**
 * Register Ramos module permissions.
 *
 * @return void
 */
function ramos_register_permissions(): void
{
    $capabilities = [
        'capabilities' => [
            'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
            'create' => _l('permission_create'),
            'edit'   => _l('permission_edit'),
            'delete' => _l('permission_delete'),
        ],
    ];

    register_staff_capabilities(RAMOS_MODULE_NAME, $capabilities, _l('ramos_permission_group'));
}

/**
 * Add Ramos menu entry to the admin sidebar.
 *
 * @return void
 */
function ramos_init_admin_menu(): void
{
    if (!staff_can('view', RAMOS_MODULE_NAME)) {
        return;
    }

    $CI = &get_instance();
    $CI->app_menu->add_sidebar_menu_item('ramos-dashboard', [
        'name'     => _l('ramos_menu_label'),
        'icon'     => RAMOS_MODULE_ICON,
        'href'     => admin_url('ramos'),
        'position' => 16,
    ]);

    $CI->app_menu->add_sidebar_children_item('ramos-dashboard', [
        'slug'     => 'ramos-orders',
        'name'     => _l('ramos_orders_menu_label'),
        'href'     => admin_url('ramos/orders'),
        'position' => 1,
    ]);

    $CI->app_menu->add_sidebar_children_item('ramos-dashboard', [
        'slug'     => 'ramos-inventory',
        'name'     => _l('ramos_inventory_menu_label'),
        'href'     => admin_url('ramos/inventory'),
        'position' => 2,
    ]);

    $CI->app_menu->add_sidebar_children_item('ramos-dashboard', [
        'slug'     => 'ramos-suppliers',
        'name'     => _l('ramos_suppliers_menu_label'),
        'href'     => admin_url('ramos/suppliers'),
        'position' => 3,
    ]);

    $CI->app_menu->add_sidebar_children_item('ramos-dashboard', [
        'slug'     => 'ramos-purchases',
        'name'     => _l('ramos_purchases_menu_label'),
        'href'     => admin_url('ramos/purchases'),
        'position' => 4,
    ]);

    $CI->app_menu->add_sidebar_children_item('ramos-dashboard', [
        'slug'     => 'ramos-picking',
        'name'     => _l('ramos_picking_menu_label'),
        'href'     => admin_url('ramos/picking'),
        'position' => 5,
    ]);

    $CI->app_menu->add_sidebar_children_item('ramos-dashboard', [
        'slug'     => 'ramos-picking-console',
        'name'     => _l('ramos_picking_console_menu_label'),
        'href'     => admin_url('ramos/picking/console'),
        'position' => 6,
    ]);

    $CI->app_menu->add_sidebar_children_item('ramos-dashboard', [
        'slug'     => 'ramos-facturacion',
        'name'     => _l('ramos_facturacion_menu_label'),
        'href'     => admin_url('ramos/facturacion'),
        'position' => 7,
    ]);

    $CI->app_menu->add_sidebar_children_item('ramos-dashboard', [
        'slug'     => 'ramos-routes',
        'name'     => _l('ramos_routes_menu_label'),
        'href'     => admin_url('ramos/routes'),
        'position' => 8,
    ]);

    $CI->app_menu->add_sidebar_children_item('ramos-dashboard', [
        'slug'     => 'ramos-routes-board',
        'name'     => _l('ramos_routes_board_menu_label'),
        'href'     => admin_url('ramos/routes/board'),
        'position' => 9,
    ]);

    $CI->app_menu->add_sidebar_children_item('ramos-dashboard', [
        'slug'     => 'ramos-pricing',
        'name'     => _l('ramos_pricing_menu_label'),
        'href'     => admin_url('ramos/pricing'),
        'position' => 10,
    ]);

    $CI->app_menu->add_sidebar_children_item('ramos-dashboard', [
        'slug'     => 'ramos-automation',
        'name'     => _l('ramos_automation_menu_label'),
        'href'     => admin_url('ramos/automation'),
        'position' => 11,
    ]);

    $CI->app_menu->add_sidebar_children_item('ramos-automation', [
        'slug'     => 'ramos-automation-settings',
        'name'     => _l('ramos_settings_menu_label'),
        'href'     => admin_url('ramos/automation/settings'),
        'position' => 1,
    ]);
}

/**
 * Register Ramos entry in the client portal navigation.
 *
 * @return void
 */
function ramos_init_client_portal(): void
{
    if (!is_client_logged_in()) {
        return;
    }

    if (!function_exists('add_theme_menu_item')) {
        $CI = &get_instance();
        $CI->load->helper('themes');
    }

    add_theme_menu_item('ramos-client-orders', [
        'name'     => _l('ramos_client_orders_nav'),
        'href'     => site_url('clients/ramos_client/orders'),
        'position' => 55,
        'icon'     => 'fa-solid fa-basket-shopping',
    ]);
}

/**
 * Ensure database structure exists when module loads.
 *
 * @return void
 */
function ramos_run_initial_setup(): void
{
    $CI = &get_instance();

    if (!property_exists($CI, 'db')) {
        $CI->load->database();
    }

    if (!isset($CI->db) || !method_exists($CI->db, 'table_exists')) {
        return;
    }

    if (!$CI->db->table_exists(db_prefix() . 'ramos_orders')) {
        require_once(__DIR__ . '/install.php');
    }

    if (!$CI->db->table_exists(db_prefix() . 'ramos_inventory_items')) {
        require_once(__DIR__ . '/install.php');
    }

    if (!$CI->db->table_exists(db_prefix() . 'ramos_suppliers')
        || !$CI->db->table_exists(db_prefix() . 'ramos_order_items')
        || !$CI->db->table_exists(db_prefix() . 'ramos_purchase_batches')
        || !$CI->db->table_exists(db_prefix() . 'ramos_purchase_batch_items')
        || !$CI->db->table_exists(db_prefix() . 'ramos_modules')
        || !$CI->db->table_exists(db_prefix() . 'ramos_module_products')
        || !$CI->db->table_exists(db_prefix() . 'ramos_module_staff')
        || !$CI->db->table_exists(db_prefix() . 'ramos_pick_items')
        || !$CI->db->field_exists('supplier_id', db_prefix() . 'ramos_inventory_items')) {
        require_once(__DIR__ . '/install.php');
    }

    // Check for automation tracking feature updates
    if (!$CI->db->table_exists(db_prefix() . 'ramos_automation_runs')
        || !$CI->db->field_exists('processed_for_purchase', db_prefix() . 'ramos_orders')
        || !$CI->db->field_exists('priority', db_prefix() . 'ramos_suppliers')
        || !$CI->db->field_exists('image_path', db_prefix() . 'ramos_inventory_items')) {
        require_once(__DIR__ . '/install.php');
    }
}

/**
 * Available order statuses.
 *
 * @return array
 */
function ramos_order_statuses(): array
{
    return [
        RAMOS_ORDER_STATUS_NEW        => _l('ramos_order_status_new'),
        RAMOS_ORDER_STATUS_PROCESSING => _l('ramos_order_status_processing'),
        RAMOS_ORDER_STATUS_READY      => _l('ramos_order_status_ready'),
    ];
}

/**
 * Available order priorities.
 *
 * @return array
 */
function ramos_order_priorities(): array
{
    return [
        RAMOS_PRIORITY_HIGH   => _l('ramos_order_priority_high'),
        RAMOS_PRIORITY_NORMAL => _l('ramos_order_priority_normal'),
        RAMOS_PRIORITY_LOW    => _l('ramos_order_priority_low'),
    ];
}

/**
 * Status badge CSS helper.
 *
 * @param  string $status
 * @return string
 */
function ramos_order_status_badge_class(string $status): string
{
    $map = [
        RAMOS_ORDER_STATUS_NEW        => 'label-default',
        RAMOS_ORDER_STATUS_PROCESSING => 'label-warning',
        RAMOS_ORDER_STATUS_READY      => 'label-success',
    ];

    return $map[$status] ?? 'label-default';
}

/**
 * Priority badge CSS helper.
 *
 * @param  string $priority
 * @return string
 */
function ramos_order_priority_badge_class(string $priority): string
{
    $map = [
        RAMOS_PRIORITY_HIGH   => 'label-danger',
        RAMOS_PRIORITY_NORMAL => 'label-info',
        RAMOS_PRIORITY_LOW    => 'label-default',
    ];

    return $map[$priority] ?? 'label-default';
}

/**
 * Determine inventory status based on quantity and safety stock.
 *
 * @param  float $quantity
 * @param  float $safety
 * @param  float $bufferPercent
 * @return string
 */
function ramos_inventory_status(float $quantity, float $safety, float $bufferPercent = 25.0): string
{
    if ($safety <= 0) {
        return $quantity > 0 ? 'green' : 'red';
    }

    if ($quantity < $safety) {
        return 'red';
    }

    $threshold = $safety * (1 + ($bufferPercent / 100));

    if ($quantity <= $threshold) {
        return 'yellow';
    }

    return 'green';
}

/**
 * Map inventory status to badge class.
 *
 * @param  string $status
 * @return string
 */
function ramos_inventory_status_badge_class(string $status): string
{
    $map = [
        'green'  => 'label-success',
        'yellow' => 'label-warning',
        'red'    => 'label-danger',
    ];

    return $map[$status] ?? 'label-default';
}

/**
 * Map inventory status to readable label.
 *
 * @param  string $status
 * @return string
 */
function ramos_inventory_status_label(string $status): string
{
    $map = [
        'green'  => _l('ramos_inventory_status_green'),
        'yellow' => _l('ramos_inventory_status_yellow'),
        'red'    => _l('ramos_inventory_status_red'),
    ];

    return $map[$status] ?? $status;
}

function ramos_purchase_statuses(): array
{
    return [
        'draft'   => _l('ramos_purchase_status_draft'),
        'sent'    => _l('ramos_purchase_status_sent'),
        'partial' => _l('ramos_purchase_status_partial'),
        'received'=> _l('ramos_purchase_status_received'),
    ];
}

function ramos_purchase_status_badge_class(string $status): string
{
    $map = [
        'draft'   => 'label-default',
        'sent'    => 'label-info',
        'partial' => 'label-warning',
        'received'=> 'label-success',
    ];

    return $map[$status] ?? 'label-default';
}

function ramos_route_statuses(): array
{
    return [
        'draft'      => _l('ramos_route_status_draft'),
        'dispatched' => _l('ramos_route_status_dispatched'),
        'completed'  => _l('ramos_route_status_completed'),
    ];
}

function ramos_route_status_badge_class(string $status): string
{
    $map = [
        'draft'      => 'label-primary',
        'dispatched' => 'label-info',
        'completed'  => 'label-success',
    ];

    return $map[$status] ?? 'label-default';
}

/**
 * Scheduled automation and route generation via cron
 *
 * Runs at configured hours (e.g., 8am, 2pm, 6pm) to:
 * 1. Execute automation to generate purchase orders
 * 2. On success, immediately generate routes for today
 *
 * @param bool $manually Whether cron was triggered manually
 * @return void
 */
function ramos_scheduled_automation_and_routes($manually = false): void
{
    // Log that this function was called
    log_activity('[RAMOS CRON] ramos_scheduled_automation_and_routes called, time: ' . date('Y-m-d H:i:s'));
    
    // Load automation helper
    $CI = &get_instance();
    $CI->load->helper('ramos/ramos_automation');

    // Log cron call for debugging
    $enabled = get_option('ramos_automation_schedule_enabled');
    $hours = json_decode(get_option('ramos_automation_schedule_hours', json_encode([8, 14, 18])), true);
    $minutes = json_decode(get_option('ramos_automation_schedule_minutes', json_encode([0])), true);
    log_activity('[RAMOS CRON] Cron called - Enabled: ' . ($enabled === '1' ? 'Yes' : 'No') . ', Hours: ' . implode(',', $hours) . ', Minutes: ' . implode(',', $minutes) . ', Current: ' . date('H:i'));

    // Check if scheduled automation should run
    if (!ramos_should_run_scheduled_automation()) {
        log_activity('[RAMOS CRON] Conditions not met to run automation');
        return;
    }

    log_activity('[RAMOS CRON] Running automation...');

    // Execute automation
    $automationResult = ramos_execute_automation(0); // 0 = system/cron

    // Check if automation succeeded
    if (!$automationResult['success']) {
        log_activity('[RAMOS CRON] Automation failed: ' . ($automationResult['message'] ?? 'Unknown error'));
        return;
    }

    // Log automation success
    log_activity('[RAMOS CRON] Automation successful: ' . $automationResult['orders_processed'] . ' orders processed, ' . $automationResult['batches_created'] . ' purchase batches created');

    // Check if routes should be auto-generated on success
    if (get_option('ramos_route_generate_on_success') !== '1') {
        return;
    }

    // Generate routes for today
    $routeResult = ramos_generate_routes_for_today();

    if ($routeResult['success']) {
        // Update automation run record with routes count
        $CI->load->model('ramos/automation_model');
        $CI->automation_model->update_run_routes($automationResult['run_id'], $routeResult['routes_count']);

        log_activity('[RAMOS CRON] Routes generated successfully: ' . $routeResult['routes_count'] . ' routes created');
    } else {
        log_activity('[RAMOS CRON] Route generation failed: ' . ($routeResult['error'] ?? 'Unknown error'));
    }

    // Mark automation as completed for today
    update_option('ramos_last_automation_run_date', date('Y-m-d H:i:s'));
}
