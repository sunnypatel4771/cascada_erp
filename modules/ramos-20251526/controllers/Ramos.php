<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Ramos module dashboard controller.
 */
class Ramos extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('ramos/dashboard_model', 'dashboard_model');
        $this->load->model('ramos/notifications_model', 'notifications_model');
    }

    public function index(): void
    {
        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $routeDate = $this->input->get('date');

        $data['title']            = _l('ramos_dashboard_title');
        $data['tagline']          = _l('ramos_dashboard_tagline');
        $data['quick_links']      = $this->build_quick_links();
        $data['initial_snapshot'] = $this->dashboard_model->build_snapshot($routeDate);

        $this->load->view('dashboard', $data);
    }

    public function snapshot(): void
    {
        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $routeDate = $this->input->get('route_date');
        $snapshot  = $this->dashboard_model->build_snapshot($routeDate);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => true,
                'data'    => $snapshot,
            ]));
    }

    public function notifications($notificationId): void
    {
        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        if (!$this->input->post()) {
            show_error('Invalid request', 400);
        }

        $action = $this->input->post('action');
        $success = false;

        if ($action === 'acknowledge') {
            $success = $this->notifications_model->acknowledge($notificationId);
        } elseif ($action === 'resolve') {
            $success = $this->notifications_model->resolve($notificationId, get_staff_user_id());
        }

        $csrfPayload = [
            'name' => $this->security->get_csrf_token_name(),
            'hash' => $this->security->get_csrf_hash(),
        ];

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => (bool) $success,
                'csrf'    => $csrfPayload,
            ]));
    }

    protected function build_quick_links(): array
    {
        return [
            [
                'label' => _l('ramos_quick_link_orders'),
                'icon'  => 'fa-solid fa-clipboard-check',
                'href'  => admin_url('ramos/orders'),
            ],
            [
                'label' => _l('ramos_quick_link_purchases'),
                'icon'  => 'fa-solid fa-file-signature',
                'href'  => admin_url('ramos/purchases'),
            ],
            [
                'label' => _l('ramos_quick_link_inventory'),
                'icon'  => 'fa-solid fa-boxes-stacked',
                'href'  => admin_url('ramos/inventory'),
            ],
            [
                'label' => _l('ramos_quick_link_modules'),
                'icon'  => 'fa-solid fa-table-cells-large',
                'href'  => admin_url('ramos/picking'),
            ],
            [
                'label' => _l('ramos_quick_link_routes'),
                'icon'  => 'fa-solid fa-truck-ramp-box',
                'href'  => admin_url('ramos/routes'),
            ],
        ];
    }
}
