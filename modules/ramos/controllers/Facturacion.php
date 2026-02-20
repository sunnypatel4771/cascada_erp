<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Facturacion extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $this->load->model('ramos/routes_model', 'routes_model');
        $this->load->model('ramos/picking_model', 'picking_model');
        $this->load->model('ramos/modules_model', 'modules_model');
        $this->load->model('invoices_model');
        $this->load->model('clients_model');
    }

    /**
     * Display the Facturacion screen
     * Shows orders grouped by Route, then by Customer
     */
    public function index(): void
    {
        $date = $this->input->get('date') ?: date('Y-m-d');

        $routesData = $this->get_facturacion_data($date);

        $data['title']       = _l('ramos_facturacion_title');
        $data['subtitle']    = _l('ramos_facturacion_subtitle');
        $data['routes_data'] = $routesData;
        $data['selected_date'] = $date;
        $data['can_edit']    = staff_can('edit', RAMOS_MODULE_NAME) || is_admin();

        $this->load->view('facturacion/manage', $data);
    }

    /**
     * AJAX endpoint to refresh facturacion data
     */
    public function refresh(): void
    {
        if (!$this->input->is_ajax_request()) {
            show_error('Invalid request', 400);
        }

        $date = $this->input->post('date') ?: date('Y-m-d');
        $routesData = $this->get_facturacion_data($date);

        $statusLabels = [
            'red'    => 'label-danger',
            'yellow' => 'label-warning',
            'green'  => 'label-success',
        ];

        $canEdit = staff_can('edit', RAMOS_MODULE_NAME) || is_admin();

        $html = '';
        if (!empty($routesData)) {
            $html = $this->render_facturacion_routes($routesData, $statusLabels, $canEdit);
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => true,
                'html'    => $html,
                'hasData' => !empty($routesData),
            ]));
    }

    /**
     * Update a pick item (quantity/weight)
     */
    public function update_item($pickId): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        if (!$this->input->post()) {
            redirect(admin_url('ramos/facturacion'));
        }

        $pick = $this->picking_model->get_pick_item($pickId);
        if (empty($pick)) {
            show_404();
        }

        $pickedQty = (float) $this->input->post('picked_qty');
        $weight    = (float) $this->input->post('weight');
        $staffId   = get_staff_user_id();

        $this->picking_model->update_pick_item($pickId, $pickedQty, $weight, $staffId);

        set_alert('success', _l('ramos_facturacion_item_updated'));

        redirect(admin_url('ramos/facturacion'));
    }

    /**
     * Generate invoice for an order
     */
    public function generate_invoice($orderId): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $this->load->library('ramos/ramos_invoice_generator', null, 'ramos_invoice_generator');

        $result = $this->ramos_invoice_generator->generate_for_order((int) $orderId);

        if ($result['success']) {
            set_alert('success', $result['message'] ?? _l('ramos_facturacion_invoice_created'));
        } else {
            set_alert('warning', $result['message'] ?? _l('ramos_facturacion_invoice_failed'));
        }

        if (!empty($result['warnings'])) {
            foreach ($result['warnings'] as $warning) {
                set_alert('warning', $warning);
            }
        }

        redirect(admin_url('ramos/facturacion'));
    }

    /**
     * Generate remission (delivery note) for an order
     * This creates a PDF delivery note without generating an invoice
     */
    public function generate_remision($orderId): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $order = $this->get_order_with_items((int) $orderId);

        if (empty($order)) {
            set_alert('warning', _l('ramos_facturacion_order_not_found'));
            redirect(admin_url('ramos/facturacion'));
        }

        // Generate PDF remission
        $this->load->library('pdf');

        $html = $this->load->view('ramos/facturacion/remision_pdf', ['order' => $order], true);

        $this->pdf->load_html($html);
        $this->pdf->render();

        $filename = 'Remision_' . $order['order_number'] . '_' . date('Ymd') . '.pdf';
        $this->pdf->stream($filename, ['Attachment' => false]);
    }

    /**
     * Get facturacion data grouped by route and customer
     */
    protected function get_facturacion_data(string $date): array
    {
        $routes = $this->routes_model->get_routes($date);

        if (empty($routes)) {
            return [];
        }

        $result = [];

        foreach ($routes as $route) {
            $routeId = (int) $route['id'];
            $stops = $this->routes_model->get_route_stops($routeId);

            if (empty($stops)) {
                continue;
            }

            $customers = [];
            foreach ($stops as $stop) {
                $orderId = (int) $stop['order_id'];
                $orderData = $this->get_order_pick_data($orderId);

                if (!empty($orderData)) {
                    $customers[] = $orderData;
                }
            }

            if (!empty($customers)) {
                $result[] = [
                    'route'     => $route,
                    'customers' => $customers,
                ];
            }
        }

        return $result;
    }

    /**
     * Get order data with pick items for facturacion display
     */
    protected function get_order_pick_data(int $orderId): array
    {
        // Get order info from cart (omni_sales orders)
        $order = $this->db
            ->select('c.id, c.order_number, c.phonenumber as customer_name, c.address as delivery_address, c.userid, c.total')
            ->select('cl.company as client_company')
            ->from(db_prefix() . 'cart c')
            ->join(db_prefix() . 'clients cl', 'cl.userid = c.userid', 'left')
            ->where('c.id', $orderId)
            ->get()
            ->row_array();

        if (empty($order)) {
            return [];
        }

        // Get pick items for this order
        $items = $this->db
            ->select([
                'pi.id as pick_id',
                'pi.required_qty',
                'pi.picked_qty',
                'pi.weight',
                'pi.status as pick_status',
                'i.description as item_name',
                'u.unit_name as unit',
                'cd.quantity',
                'cd.price',
            ])
            ->from(db_prefix() . 'ramos_pick_items pi')
            ->join(db_prefix() . 'cart_detailt cd', 'cd.id = pi.order_item_id', 'inner')
            ->join(db_prefix() . 'items i', 'i.id = cd.product_id', 'left')
            ->join(db_prefix() . 'ware_unit_type u', 'u.unit_type_id = i.unit_id', 'left')
            ->where('pi.order_id', $orderId)
            ->order_by('pi.id', 'ASC')
            ->get()
            ->result_array();

        if (empty($items)) {
            // Also try to get items directly from cart_detailt even without pick records
            $items = $this->db
                ->select([
                    '0 as pick_id',
                    'cd.quantity as required_qty',
                    '0 as picked_qty',
                    '0 as weight',
                    "'pending' as pick_status",
                    'i.description as item_name',
                    'u.unit_name as unit',
                    'cd.quantity',
                    'cd.price',
                ])
                ->from(db_prefix() . 'cart_detailt cd')
                ->join(db_prefix() . 'items i', 'i.id = cd.product_id', 'left')
                ->join(db_prefix() . 'ware_unit_type u', 'u.unit_type_id = i.unit_id', 'left')
                ->where('cd.cart_id', $orderId)
                ->order_by('cd.id', 'ASC')
                ->get()
                ->result_array();
        }

        // Calculate order status
        $statuses = array_column($items, 'pick_status');
        $totalItems = count($items);
        $completed = count(array_filter($statuses, fn($s) => $s === 'completed'));

        $displayStatus = 'red';
        if ($completed === $totalItems && $totalItems > 0) {
            $displayStatus = 'green';
        } elseif (!in_array('pending', $statuses, true) && !in_array('in_progress', $statuses, true)) {
            if (in_array('weight_missing', $statuses, true)) {
                $displayStatus = 'yellow';
            } else {
                $displayStatus = 'green';
            }
        }

        // Check if invoice already exists
        $hasInvoice = $this->db
            ->select('invoice_id')
            ->from(db_prefix() . 'ramos_orders')
            ->where('id', $orderId)
            ->get()
            ->row();

        $invoiceId = $hasInvoice ? $hasInvoice->invoice_id : null;

        return [
            'order_id'         => $orderId,
            'order_number'     => $order['order_number'],
            'customer_name'    => $order['customer_name'] ?: $order['client_company'],
            'delivery_address' => $order['delivery_address'],
            'total'            => (float) $order['total'],
            'items'            => $items,
            'status'           => $displayStatus,
            'invoice_id'       => $invoiceId,
        ];
    }

    /**
     * Get full order with items for remision PDF
     */
    protected function get_order_with_items(int $orderId): array
    {
        $order = $this->db
            ->select('c.*, cl.company as client_company, cl.billing_street, cl.billing_city, cl.billing_state, cl.billing_zip')
            ->from(db_prefix() . 'cart c')
            ->join(db_prefix() . 'clients cl', 'cl.userid = c.userid', 'left')
            ->where('c.id', $orderId)
            ->get()
            ->row_array();

        if (empty($order)) {
            return [];
        }

        $order['items'] = $this->db
            ->select('cd.*, i.description as item_name, u.unit_name as unit, pi.picked_qty, pi.weight')
            ->from(db_prefix() . 'cart_detailt cd')
            ->join(db_prefix() . 'items i', 'i.id = cd.product_id', 'left')
            ->join(db_prefix() . 'ware_unit_type u', 'u.unit_type_id = i.unit_id', 'left')
            ->join(db_prefix() . 'ramos_pick_items pi', 'pi.order_item_id = cd.id', 'left')
            ->where('cd.cart_id', $orderId)
            ->order_by('cd.id', 'ASC')
            ->get()
            ->result_array();

        return $order;
    }

    /**
     * Render facturacion routes HTML for AJAX refresh
     */
    protected function render_facturacion_routes(array $routesData, array $statusLabels, bool $canEdit): string
    {
        $buffer = '';
        foreach ($routesData as $entry) {
            $buffer .= $this->load->view('ramos/facturacion/partials/route_card', [
                'route'        => $entry['route'],
                'customers'    => $entry['customers'],
                'statusLabels' => $statusLabels,
                'can_edit'     => $canEdit,
            ], true);
        }

        return $buffer;
    }
}
