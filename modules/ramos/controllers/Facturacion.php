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
     * Update price for an individual order line item.
     * For omni_sales orders this updates tblcart_detailt.price.
     * For erp_invoice orders this updates tblitemable.rate.
     */
    public function update_item_price(): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        if (!$this->input->post()) {
            redirect(admin_url('ramos/facturacion'));
        }

        $orderSource = $this->input->post('order_source') ?: 'omni_sales';
        $itemId      = (int) $this->input->post('item_id');
        $newPrice    = (float) $this->input->post('new_price');

        if ($itemId <= 0 || $newPrice < 0) {
            set_alert('warning', _l('ramos_facturacion_price_invalid'));
            redirect(admin_url('ramos/facturacion'));
        }

        if ($orderSource === 'erp_invoice') {
            $this->db->where('id', $itemId);
            $updated = $this->db->update(db_prefix() . 'itemable', ['rate' => $newPrice]);
        } else {
            $this->db->where('id', $itemId);
            $updated = $this->db->update(db_prefix() . 'cart_detailt', ['price' => $newPrice]);
        }

        if ($updated) {
            set_alert('success', _l('ramos_facturacion_price_updated'));
        } else {
            set_alert('warning', _l('ramos_facturacion_price_update_failed'));
        }

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
     * Send the Perfex invoice for an order to the customer by email.
     *
     * Resolves the Perfex invoice linked to the order (via ramos_orders or
     * the processed_for_purchase invoice) then delegates to Perfex's own
     * send_invoice_to_client which handles template, PDF attachment, and logging.
     */
    public function send_invoice_email($orderId): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $orderId = (int) $orderId;

        // Resolve invoice_id from ramos_orders (legacy ramos flow)
        $orderRow = $this->db
            ->select('invoice_id')
            ->where('id', $orderId)
            ->get(db_prefix() . 'ramos_orders')
            ->row_array();

        $invoiceId = $orderRow ? (int) ($orderRow['invoice_id'] ?? 0) : 0;

        // Fallback: try omni_sales / cart order by matching the exact clientnote string
        // that Clients::save_new_order() sets ("Order created from customer portal").
        // Using an exact match avoids picking up the wrong invoice when a customer has
        // placed multiple portal orders (previously only "LIKE '%portal%'" was used).
        if (!$invoiceId) {
            $cartRow = $this->db
                ->select('userid, order_number')
                ->where('id', $orderId)
                ->get(db_prefix() . 'cart')
                ->row_array();

            if ($cartRow) {
                $inv = $this->db
                    ->select('id')
                    ->where('clientid', (int) $cartRow['userid'])
                    ->where('status', 1)
                    ->where('clientnote', 'Order created from customer portal')
                    ->order_by('id', 'DESC')
                    ->limit(1)
                    ->get(db_prefix() . 'invoices')
                    ->row_array();

                $invoiceId = $inv ? (int) $inv['id'] : 0;
            }
        }

        if (!$invoiceId) {
            set_alert('warning', _l('ramos_facturacion_email_no_invoice'));
            redirect(admin_url('ramos/facturacion'));
        }

        // Use Perfex's built-in invoice email (template + PDF attachment)
        $this->load->model('invoices_model');
        $sent = $this->invoices_model->send_invoice_to_client(
            $invoiceId,
            '',    // use default template
            true,  // attach PDF
            '',    // no CC
            true   // mark as sent manually
        );

        // Log the send attempt
        if ($sent) {
            $this->db->insert(db_prefix() . 'ramos_facturacion_email_log', [
                'order_id'   => $orderId,
                'invoice_id' => $invoiceId,
                'sent_by'    => get_staff_user_id(),
                'sent_at'    => date('Y-m-d H:i:s'),
                'status'     => 'sent',
            ]);
            set_alert('success', _l('ramos_facturacion_email_sent'));
        } else {
            $this->db->insert(db_prefix() . 'ramos_facturacion_email_log', [
                'order_id'   => $orderId,
                'invoice_id' => $invoiceId,
                'sent_by'    => get_staff_user_id(),
                'sent_at'    => date('Y-m-d H:i:s'),
                'status'     => 'failed',
            ]);
            set_alert('danger', _l('ramos_facturacion_email_failed'));
        }

        redirect(admin_url('ramos/facturacion'));
    }

    /**
     * Generate or retrieve FE-SAT document for an order's invoice.
     *
     * Records the generation attempt in ramos_facturacion_fesat_log.
     * Actual SAT provider API integration should be added here once
     * credentials and provider are confirmed (Infilesat, Megaprint, etc.).
     */
    public function generate_fesat($orderId): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $orderId = (int) $orderId;

        // Resolve invoice
        $orderRow = $this->db
            ->select('invoice_id')
            ->where('id', $orderId)
            ->get(db_prefix() . 'ramos_orders')
            ->row_array();

        $invoiceId = $orderRow ? (int) ($orderRow['invoice_id'] ?? 0) : 0;

        if (!$invoiceId) {
            set_alert('warning', _l('ramos_facturacion_fesat_no_invoice'));
            redirect(admin_url('ramos/facturacion'));
        }

        // Check if already generated to avoid duplicates
        $existing = $this->db
            ->where('order_id', $orderId)
            ->where('status', 'generated')
            ->get(db_prefix() . 'ramos_facturacion_fesat_log')
            ->row_array();

        if ($existing) {
            set_alert('warning', _l('ramos_facturacion_fesat_already_generated', $existing['document_id'] ?? ''));
            redirect(admin_url('ramos/facturacion'));
        }

        // SAT provider integration placeholder.
        // Replace this block with actual provider API call (e.g. Infilesat/Megaprint/G4S).
        $fesat_enabled = get_option('ramos_fesat_enabled') === '1';
        if (!$fesat_enabled) {
            set_alert('warning', _l('ramos_facturacion_fesat_not_configured'));
            redirect(admin_url('ramos/facturacion'));
        }

        // Log generation attempt (provider-specific result would update document_id + status)
        $this->db->insert(db_prefix() . 'ramos_facturacion_fesat_log', [
            'order_id'    => $orderId,
            'invoice_id'  => $invoiceId,
            'document_id' => null, // populated by provider response
            'generated_by'=> get_staff_user_id(),
            'generated_at'=> date('Y-m-d H:i:s'),
            'status'      => 'pending',
            'notes'       => 'FE-SAT generation pending provider integration.',
        ]);

        set_alert('info', _l('ramos_facturacion_fesat_pending'));
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
                $orderId     = (int) $stop['order_id'];
                $orderSource = $stop['order_source'] ?? 'omni_sales';
                $orderData   = $this->get_order_pick_data($orderId, $orderSource);

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
     * Get order data with pick items for facturacion display.
     * Supports both omni_sales (tblcart) and erp_invoice (tblinvoices) order sources.
     */
    protected function get_order_pick_data(int $orderId, string $orderSource = 'omni_sales'): array
    {
        if ($orderSource === 'erp_invoice') {
            return $this->get_erp_order_pick_data($orderId);
        }

        return $this->get_omni_order_pick_data($orderId);
    }

    /**
     * Facturacion data for Omni Sales (tblcart) orders
     */
    protected function get_omni_order_pick_data(int $orderId): array
    {
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

        $items = $this->db
            ->select([
                'pi.id as pick_id',
                'cd.id as item_id',
                'pi.required_qty',
                'pi.picked_qty',
                'pi.weight',
                'pi.status as pick_status',
                'i.description as item_name',
                'u.unit_name as unit',
                'cd.quantity',
                'cd.price',
                'cd.ripeness',
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
            $items = $this->db
                ->select([
                    '0 as pick_id',
                    'cd.id as item_id',
                    'cd.quantity as required_qty',
                    '0 as picked_qty',
                    '0 as weight',
                    "'pending' as pick_status",
                    'i.description as item_name',
                    'u.unit_name as unit',
                    'cd.quantity',
                    'cd.price',
                    'cd.ripeness',
                ])
                ->from(db_prefix() . 'cart_detailt cd')
                ->join(db_prefix() . 'items i', 'i.id = cd.product_id', 'left')
                ->join(db_prefix() . 'ware_unit_type u', 'u.unit_type_id = i.unit_id', 'left')
                ->where('cd.cart_id', $orderId)
                ->order_by('cd.id', 'ASC')
                ->get()
                ->result_array();
        }

        $displayStatus = $this->calculate_display_status(array_column($items, 'pick_status'));

        $hasInvoice = $this->db
            ->select('invoice_id')
            ->from(db_prefix() . 'ramos_orders')
            ->where('id', $orderId)
            ->get()
            ->row();

        return [
            'order_id'         => $orderId,
            'order_source'     => 'omni_sales',
            'order_number'     => $order['order_number'],
            'customer_name'    => $order['customer_name'] ?: $order['client_company'],
            'delivery_address' => $order['delivery_address'],
            'total'            => (float) $order['total'],
            'items'            => $items,
            'status'           => $displayStatus,
            'invoice_id'       => $hasInvoice ? $hasInvoice->invoice_id : null,
        ];
    }

    /**
     * Facturacion data for ERP portal (tblinvoices) orders
     */
    protected function get_erp_order_pick_data(int $orderId): array
    {
        $order = $this->db
            ->select('i.id, i.number as order_number, i.total, i.clientid')
            ->select('cl.company as customer_name, cl.shipping_street as delivery_address')
            ->from(db_prefix() . 'invoices i')
            ->join(db_prefix() . 'clients cl', 'cl.userid = i.clientid', 'left')
            ->where('i.id', $orderId)
            ->get()
            ->row_array();

        if (empty($order)) {
            return [];
        }

        // ERP invoice items come from tblitemable
        $items = $this->db
            ->select([
                '0 as pick_id',
                'ia.id as item_id',
                'ia.qty as required_qty',
                '0 as picked_qty',
                '0 as weight',
                "'pending' as pick_status",
                'ia.description as item_name',
                'ia.unit as unit',
                'ia.qty as quantity',
                'ia.rate as price',
                'ia.ripeness as ripeness',
            ])
            ->from(db_prefix() . 'itemable ia')
            ->where('ia.rel_id', $orderId)
            ->where('ia.rel_type', 'invoice')
            ->order_by('ia.item_order', 'ASC')
            ->get()
            ->result_array();

        $displayStatus = $this->calculate_display_status(array_column($items, 'pick_status'));

        return [
            'order_id'         => $orderId,
            'order_source'     => 'erp_invoice',
            'order_number'     => $order['order_number'],
            'customer_name'    => $order['customer_name'],
            'delivery_address' => $order['delivery_address'],
            'total'            => (float) $order['total'],
            'items'            => $items,
            'status'           => $displayStatus,
            'invoice_id'       => $orderId, // ERP orders ARE invoices
        ];
    }

    /**
     * Compute the display status (green/yellow/red) from an array of pick statuses.
     * Red    = any item waiting_for_po
     * Yellow = any item pending, in_progress, or weight_missing
     * Green  = all items completed
     */
    protected function calculate_display_status(array $statuses): string
    {
        if (empty($statuses)) {
            return 'red';
        }

        if (in_array('waiting_for_po', $statuses, true)) {
            return 'red';
        }

        if (count(array_filter($statuses, fn($s) => in_array($s, ['pending', 'in_progress', 'weight_missing'], true))) > 0) {
            return 'yellow';
        }

        return 'green';
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
