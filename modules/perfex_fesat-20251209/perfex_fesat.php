<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Perfex FE-SAT
Description: Generate CFDI 4.0 invoices with Digibox timbrado and attach results to Perfex invoices.
Version: 1.0.0
Requires at least: 3.0.*
*/

const PERFEX_FESAT_MODULE_NAME  = 'perfex_fesat';
const PERFEX_FESAT_UPLOAD_DIR   = FCPATH . 'uploads/fe_sat/';
const PERFEX_FESAT_MODULE_ROUTE = 'perfex_fesat';

hooks()->add_action('admin_init', 'perfex_fesat_init');
hooks()->add_action('admin_init', 'perfex_fesat_register_permissions');
hooks()->add_action('after_invoice_view_as_client_link', 'perfex_fesat_render_generate_button');
hooks()->add_action('after_invoice_preview_template_rendered', 'perfex_fesat_render_invoice_panel');
hooks()->add_action('before_invoice_deleted', 'perfex_fesat_prevent_invoice_delete_when_stamped');

register_activation_hook(PERFEX_FESAT_MODULE_NAME, 'perfex_fesat_activate');
register_language_files(PERFEX_FESAT_MODULE_NAME, ['fe_sat']);

/**
 * Module activation handler.
 */
function perfex_fesat_activate(): void
{
    $CI = &get_instance();

    perfex_fesat_run_install_sql($CI);
    perfex_fesat_seed_options();
    perfex_fesat_ensure_upload_directory();
    perfex_fesat_ensure_email_template();
}

/**
 * Ensure upload directory exists.
 */
function perfex_fesat_ensure_upload_directory(): void
{
    if (!is_dir(PERFEX_FESAT_UPLOAD_DIR)) {
        if (!mkdir(PERFEX_FESAT_UPLOAD_DIR, 0755, true) && !is_dir(PERFEX_FESAT_UPLOAD_DIR)) {
            log_activity('Perfex FE-SAT could not create uploads directory: ' . PERFEX_FESAT_UPLOAD_DIR);
        }
    }
}

/**
 * Execute install SQL statements.
 */
function perfex_fesat_run_install_sql(CI_Controller $CI): void
{
    $sqlFile = __DIR__ . '/install.sql';
    if (!is_file($sqlFile)) {
        return;
    }

    $commands = file_get_contents($sqlFile);
    if (!$commands) {
        return;
    }

    $commands = str_replace('{{DB_PREFIX}}', db_prefix(), $commands);

    foreach (explode(';', $commands) as $command) {
        $statement = trim($command);
        if ($statement === '') {
            continue;
        }

        $CI->db->query($statement);
    }
}

/**
 * Register module permissions.
 */
function perfex_fesat_register_permissions(): void
{
    $capabilities = [
        'capabilities' => [
            'view'             => _l('fe_sat_permission_view'),
            'generate'         => _l('fe_sat_permission_generate'),
            'permission_send_email'       => _l('fe_sat_permission_send_email'),
            'manage_settings'  => _l('fe_sat_permission_manage_settings'),
        ],
        'help' => [
            'view'            => _l('fe_sat_permission_view_help'),
            'generate'        => _l('fe_sat_permission_generate_help'),
            'permission_send_email'      => _l('fe_sat_permission_send_email_help'),
            'manage_settings' => _l('fe_sat_permission_manage_settings_help'),
        ],
    ];

    register_staff_capabilities('fe_sat', $capabilities, _l('fe_sat_permissions_group_name'));
}

/**
 * Seed default options on activation.
 */
function perfex_fesat_seed_options(): void
{
    $defaults = [
        'perfex_fesat_base_url'           => '',
        'perfex_fesat_username'           => '',
        'perfex_fesat_password'           => '',
        'perfex_fesat_api_key'            => '',
        'perfex_fesat_series_default'     => 'A',
        'perfex_fesat_folio_next'         => '1',
        'perfex_fesat_cfdi_use_default'   => 'G03',
        'perfex_fesat_payment_method'     => 'PPD',
        'perfex_fesat_payment_form'       => '99',
        'perfex_fesat_currency'           => 'MXN',
        'perfex_fesat_email_template'     => 'fe_sat_send_email',
        'perfex_fesat_storage_driver'     => 'local',
        'perfex_fesat_sandbox_mode'       => '1',
        'perfex_fesat_last_settings_test' => '',
        'perfex_fesat_company_regimen'    => '601',
    ];

    foreach ($defaults as $option => $value) {
        if (get_option($option) === false) {
            add_option($option, $value);
        }
    }
}

/**
 * Create default email template if missing.
 */
function perfex_fesat_ensure_email_template(): void
{
    if (!function_exists('create_email_template')) {
        $CI = &get_instance();
        $CI->load->helper('email_templates');
    }

    if (total_rows(db_prefix() . 'emailtemplates', ['slug' => 'fe_sat_send_email']) === 0) {
        create_email_template(
            'CFDI timbrado {invoice_number}',
            '<p>Estimado {contact_firstname},</p><p>Adjuntamos los archivos timbrados (XML y PDF) correspondientes a la factura {fe_sat_invoice_number}.</p><p>UUID: {fe_sat_uuid}</p><p>Saludos cordiales.</p>',
            'invoice',
            'Envió CFDI timbrado',
            'fe_sat_send_email',
            1
        );
    }
}

/**
 * Initialize admin bits (menus, assets).
 */
function perfex_fesat_init(): void
{
    perfex_fesat_ensure_upload_directory();

    if (!has_permission('fe_sat', '', 'manage_settings')) {
        return;
    }

    $CI = &get_instance();
    if (!class_exists('App_menu', false)) {
        return;
    }

    $CI->app_menu->add_sidebar_menu_item('fe_sat', [
        'name'     => _l('fe_sat_sidebar_label'),
        'href'     => admin_url(PERFEX_FESAT_MODULE_ROUTE . '/settings'),
        'icon'     => 'fa-solid fa-file-invoice',
        'position' => 26,
    ]);
}

/**
 * Invoice dropdown button handler.
 */
function perfex_fesat_render_generate_button($invoice): void
{
    if (!perfex_fesat_can_generate($invoice)) {
        return;
    }

    echo '<a href="' . admin_url(PERFEX_FESAT_MODULE_ROUTE . '/form/' . $invoice->id) . '">'
        . _l('fe_sat_generate_invoice_action')
        . '</a>';
}

/**
 * Determine generate permission.
 */
function perfex_fesat_can_generate($invoice): bool
{
    $CI = &get_instance();
    if (!class_exists('Invoices_model', false)) {
        $CI->load->model('invoices_model');
    }
    if (!staff_can('generate', 'fe_sat')) {
        return false;
    }

    if (empty($invoice) || !isset($invoice->status)) {
        return false;
    }

    return (int) $invoice->status !== Invoices_model::STATUS_DRAFT;
}

/**
 * Render invoice panel after preview content.
 */
function perfex_fesat_render_invoice_panel($invoice): void
{
    if (!staff_can('view', 'fe_sat')) {
        return;
    }

    if (!isset($invoice->id)) {
        return;
    }

    $CI = &get_instance();
    $CI->load->model('perfex_fesat/fe_sat_model', 'feSatModel');
    $document = $CI->feSatModel->find_by_invoice((int) $invoice->id);

    echo $CI->load->view('perfex_fesat/partials/fe_files_panel', [
        'document' => $document,
        'invoice'  => $invoice,
    ], true);
}

/**
 * Prevent invoice delete if stamped.
 */
function perfex_fesat_prevent_invoice_delete_when_stamped($invoice_id): void
{
    $CI = &get_instance();
    $CI->load->model('perfex_fesat/fe_sat_model', 'feSatModel');
    $document = $CI->feSatModel->find_by_invoice((int) $invoice_id);

    if ($document && $document->status === 'success') {
        set_alert('warning', _l('fe_sat_invoice_delete_warning'));
    }
}
