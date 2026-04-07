<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Routes extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $this->load->model('ramos/routes_model', 'routes_model');
    }

    public function index(): void
    {
        $date = $this->sanitizeDate($this->input->get('date')) ?? date('Y-m-d');

        if ($this->input->post('generate')) {
            if (!staff_can('create', RAMOS_MODULE_NAME)) {
                access_denied();
            }

            $generateDate = $this->sanitizeDate($this->input->post('route_date')) ?? $date;
            $startTime    = $this->sanitizeTime($this->input->post('start_time')) ?? '08:00:00';
            $maxStops     = (int) $this->input->post('max_stops');
            $prefix       = trim((string) $this->input->post('route_prefix'));

            if ($maxStops <= 0) {
                $maxStops = 10;
            }

            if ($prefix === '') {
                $prefix = 'Route';
            }

            $created = $this->routes_model->generate_routes($generateDate, $startTime, $maxStops, $prefix);

            if (!empty($created)) {
                set_alert('success', _l('ramos_routes_generated_success', count($created)));
            } else {
                set_alert('warning', _l('ramos_routes_generated_none'));
            }

            redirect(admin_url('ramos/routes?date=' . $generateDate));
        }

        $routes = $this->routes_model->get_routes($date);

        $data['title']   = _l('ramos_routes_title');
        $data['subtitle']= _l('ramos_routes_subtitle');
        $data['routes']  = $routes;
        $data['date']    = $date;
        $data['form_defaults'] = [
            'route_date'    => $date,
            'start_time'    => '08:00',
            'max_stops'     => 10,
            'route_prefix'  => 'Route',
        ];
        $data['can_generate'] = staff_can('create', RAMOS_MODULE_NAME);

        $this->load->view('routes/manage', $data);
    }

    public function board(): void
    {
        $date = $this->sanitizeDate($this->input->get('date')) ?? date('Y-m-d');

        $routes = $this->routes_model->get_routes_for_board($date);

        $data['title']      = _l('ramos_routes_board_title');
        $data['subtitle']   = _l('ramos_routes_board_subtitle');
        $data['hint']       = _l('ramos_routes_board_hint');
        $data['routes']     = $routes;
        $data['date']       = $date;
        $data['can_edit']   = staff_can('edit', RAMOS_MODULE_NAME) || is_admin();
        $data['csrf'] = [
            'name' => $this->security->get_csrf_token_name(),
            'hash' => $this->security->get_csrf_hash(),
        ];

        $this->load->view('routes/board', $data);
    }

    public function list_refresh(): void
    {
        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $date   = $this->sanitizeDate($this->input->get('date')) ?? date('Y-m-d');
        $routes = $this->routes_model->get_routes($date);

        $html = $this->load->view('ramos/routes/partials/table_rows', [
            'routes' => $routes,
        ], true);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => true,
                'html'    => $html,
            ]));
    }

    public function view($routeId): void
    {
        $route = $this->routes_model->get_route($routeId);
        if (empty($route)) {
            show_404();
        }

        $stops = $this->routes_model->get_route_stops($routeId);
        $deliverySheet = $this->routes_model->get_route_delivery_sheet($routeId);

        $data['title'] = sprintf(_l('ramos_routes_view_title'), html_escape($route['vehicle_label'] ?? $route['id']));
        $data['route'] = $route;
        $data['stops'] = $stops;
        $data['delivery_sheet'] = $deliverySheet;

        $this->load->view('routes/view', $data);
    }

    public function update_status($routeId)
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $route = $this->routes_model->get_route($routeId);
        if (empty($route)) {
            show_404();
        }

        $status = $this->input->post('status');
        $success = $this->routes_model->update_route_status($routeId, $status);

        if ($success) {
            set_alert('success', _l('updated_successfully', _l('ramos_routes_label')));
        } else {
            set_alert('warning', _l('problem_updating', _l('ramos_routes_label')));
        }

        redirect(admin_url('ramos/routes/view/' . $routeId));
    }

    /**
     * AJAX endpoint: check whether a route is ready to dispatch.
     * Returns JSON with:
     *   all_picked   – true if all pick items for the route are 'completed'
     *   all_invoiced – true if every omni_sales stop has a generated invoice
     *                  (erp_invoice stops are treated as already invoiced)
     */
    public function dispatch_readiness($routeId): void
    {
        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            $this->output->set_status_header(403)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'forbidden']));
            return;
        }

        $routeId = (int) $routeId;
        $route   = $this->routes_model->get_route($routeId);
        if (empty($route)) {
            $this->output->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'not_found']));
            return;
        }

        $stops = $this->db
            ->select('order_id, order_source')
            ->from(db_prefix() . 'ramos_route_stops')
            ->where('route_id', $routeId)
            ->get()
            ->result_array();

        $allPicked   = true;
        $allInvoiced = true;

        foreach ($stops as $stop) {
            $orderId = (int) $stop['order_id'];

            $pickStatuses = $this->db
                ->select('status')
                ->from(db_prefix() . 'ramos_pick_items')
                ->where('order_id', $orderId)
                ->get()
                ->result_array();

            if (empty($pickStatuses)) {
                $allPicked = false;
            } else {
                foreach ($pickStatuses as $pi) {
                    if ($pi['status'] !== 'completed') {
                        $allPicked = false;
                        break;
                    }
                }
            }

            $orderSource = $stop['order_source'] ?? '';
            if ($orderSource !== 'erp_invoice') {
                $orderRow = $this->db
                    ->select('invoice_id')
                    ->where('id', $orderId)
                    ->get(db_prefix() . 'ramos_orders')
                    ->row_array();

                if (!$orderRow || empty($orderRow['invoice_id'])) {
                    $allInvoiced = false;
                }
            }
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'all_picked'   => $allPicked,
                'all_invoiced' => $allInvoiced,
            ]));
    }

    public function move_stop(): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME) && !is_admin()) {
            access_denied();
        }

        if (!$this->input->post()) {
            show_404();
        }

        $this->output->set_content_type('application/json');

        $stopId             = (int) $this->input->post('stop_id');
        $originRouteId      = (int) $this->input->post('origin_route_id');
        $destinationRouteId = (int) $this->input->post('destination_route_id');
        $order              = $this->input->post('order');

        $csrfPayload = [
            'name' => $this->security->get_csrf_token_name(),
            'hash' => $this->security->get_csrf_hash(),
        ];

        if ($stopId <= 0 || $destinationRouteId <= 0 || !is_array($order)) {
            $this->output->set_status_header(400);
            echo json_encode(['success' => false, 'message' => _l('ramos_routes_board_move_error'), 'csrf' => $csrfPayload]);
            return;
        }

        $orderedIds = array_values(array_filter(array_map('intval', $order)));

        if (!in_array($stopId, $orderedIds, true)) {
            $this->output->set_status_header(400);
            echo json_encode(['success' => false, 'message' => _l('ramos_routes_board_move_error'), 'csrf' => $csrfPayload]);
            return;
        }

        $result = $this->routes_model->move_stop($stopId, $originRouteId, $destinationRouteId, $orderedIds);

        if (!$result) {
            // Distinguish capacity-exceeded from generic failure for meaningful UI feedback
            $isCrossRouteMove = $originRouteId !== $destinationRouteId;
            if ($isCrossRouteMove) {
                $destRoute = $this->routes_model->get_route($destinationRouteId);
                $capacity  = isset($destRoute['capacity']) && (int) $destRoute['capacity'] > 0
                    ? (int) $destRoute['capacity']
                    : 10;
                $stopCount = (int) $this->db
                    ->where('route_id', $destinationRouteId)
                    ->count_all_results(db_prefix() . 'ramos_route_stops');
                if ($stopCount >= $capacity) {
                    $message = _l('ramos_routes_board_move_capacity_exceeded', $capacity);
                    $this->output->set_status_header(422);
                    echo json_encode(['success' => false, 'message' => $message, 'csrf' => $csrfPayload]);
                    return;
                }
            }
            $this->output->set_status_header(400);
            echo json_encode(['success' => false, 'message' => _l('ramos_routes_board_move_error'), 'csrf' => $csrfPayload]);
            return;
        }

        $destinationSummary = $this->routes_model->get_route_summary($destinationRouteId);
        $originSummary      = $originRouteId > 0 && $originRouteId !== $destinationRouteId
            ? $this->routes_model->get_route_summary($originRouteId)
            : null;

        $csrfPayload['hash'] = $this->security->get_csrf_hash();

        echo json_encode([
            'success'     => true,
            'message'     => _l('ramos_routes_board_move_success'),
            'destination' => $destinationSummary,
            'origin'      => $originSummary,
            'csrf'        => $csrfPayload,
        ]);
    }

    public function board_refresh(): void
    {
        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $date   = $this->sanitizeDate($this->input->get('date')) ?? date('Y-m-d');
        $routes = $this->routes_model->get_routes_for_board($date);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => true,
                'html'    => $this->render_board_columns($routes),
                'routes'  => $routes,
            ]));
    }

    private function sanitizeDate(?string $date): ?string
    {
        $date = trim((string) $date);

        if ($date === '') {
            return null;
        }

        $dt = DateTime::createFromFormat('Y-m-d', $date);

        if ($dt && $dt->format('Y-m-d') === $date) {
            return $date;
        }

        return null;
    }

    private function sanitizeTime(?string $time): ?string
    {
        $time = trim((string) $time);

        if ($time === '') {
            return null;
        }

        if (preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/', $time)) {
            if (strlen($time) === 5) {
                return $time . ':00';
            }

            return $time;
        }

        return null;
    }

    protected function render_board_columns(array $routes): string
    {
        if (empty($routes)) {
            return '';
        }

        $progressTemplate = _l('ramos_routes_board_progress_label');
        $pendingTemplate  = _l('ramos_routes_board_pending_label');

        $html = '';
        foreach ($routes as $route) {
            $html .= $this->load->view('ramos/routes/partials/board_column', [
                'route'             => $route,
                'progress_template' => $progressTemplate,
                'pending_template'  => $pendingTemplate,
            ], true);
        }

        return $html;
    }
}
