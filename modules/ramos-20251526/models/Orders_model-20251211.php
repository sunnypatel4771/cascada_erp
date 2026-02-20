<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Orders_model extends App_Model
{
    /**
     * @var string
     */
    protected $table;

    public function __construct()
    {
        parent::__construct();
        $this->table = db_prefix() . 'ramos_orders';
    }

    /**
     * Fetch orders.
     *
     * @param  int|null $id
     * @param  array    $filters
     * @return array
     */
    public function get($id = null, array $filters = []): array
    {
        if ($id !== null) {
            $this->db->where('id', $id);
            $order = $this->db->get($this->table)->row_array();

            return $order ?: [];
        }

        if (!empty($filters['status'])) {
            $this->db->where('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $this->db->where('priority', $filters['priority']);
        }

        if (array_key_exists('customer_reference', $filters)) {
            if ($filters['customer_reference'] === null) {
                $this->db->where('customer_reference IS NULL', null, false);
            } else {
                $this->db->where('customer_reference', $filters['customer_reference']);
            }
        }

        if (array_key_exists('client_id', $filters)) {
            if ($filters['client_id'] === null) {
                $this->db->where('client_id IS NULL', null, false);
            } else {
                $this->db->where('client_id', $filters['client_id']);
            }
        }

        if (!empty($filters['search'])) {
            $this->db->group_start()
                     ->like('customer_name', $filters['search'])
                     ->or_like('order_number', $filters['search'])
                     ->or_like('delivery_address', $filters['search'])
                     ->group_end();
        }

        $this->db->order_by('created_at', 'DESC');

        return $this->db->get($this->table)->result_array();
    }

    /**
     * Create a new order.
     *
     * @param  array $data
     * @return int
     */
    public function create(array $data): int
    {
        $data = $this->prepareOrderPayload($data, true, null);

        $this->db->insert($this->table, $data);

        $orderId = (int) $this->db->insert_id();

        if ($orderId > 0) {
            $this->trigger_new_order_notification($orderId, $data);
        }

        return $orderId;
    }

    /**
     * Update an order.
     *
     * @param  int   $id
     * @param  array $data
     * @return bool
     */
    public function update($id, array $data): bool
    {
        $payload = $this->prepareOrderPayload($data, false, $id);

        if (empty($payload)) {
            return false;
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');
        $payload['updated_by'] = get_staff_user_id();

        $this->db->where('id', $id);

        return $this->db->update($this->table, $payload);
    }

    /**
     * Update order status only.
     *
     * @param  int    $id
     * @param  string $status
     * @return bool
     */
    public function update_status($id, string $status): bool
    {
        $status = $this->sanitizeStatus($status);

        $this->db->where('id', $id);

        return $this->db->update($this->table, [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => get_staff_user_id(),
        ]);
    }

    /**
     * Delete an order record.
     *
     * @param  int $id
     * @return bool
     */
    public function delete($id): bool
    {
        $this->db->where('id', $id);

        return $this->db->delete($this->table);
    }

    /**
     * Generate a unique order number.
     *
     * @return string
     */
    public function generate_order_number(): string
    {
        $prefix = 'RO-' . date('Ymd') . '-';

        $this->db->select('order_number');
        $this->db->from($this->table);
        $this->db->like('order_number', $prefix, 'after');
        $this->db->order_by('order_number', 'DESC');
        $this->db->limit(1);

        $lastOrder = $this->db->get()->row();

        if (!$lastOrder) {
            $next = 1;
        } else {
            $suffix = (int) preg_replace('/[^0-9]/', '', str_replace($prefix, '', $lastOrder->order_number));
            $next   = $suffix + 1;
        }

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Check if order number already exists.
     *
     * @param  string   $orderNumber
     * @param  int|null $excludeId
     * @return bool
     */
    public function order_number_exists(string $orderNumber, $excludeId = null): bool
    {
        $this->db->where('order_number', $orderNumber);

        if ($excludeId !== null) {
            $this->db->where('id !=', $excludeId);
        }

        return $this->db->count_all_results($this->table) > 0;
    }

    /**
     * Normalise priority value.
     *
     * @param  string|null $priority
     * @return string
     */
    public function sanitizePriority(?string $priority): string
    {
        $priority = strtolower((string) $priority);

        $allowed = array_keys(ramos_order_priorities());

        if (!in_array($priority, $allowed, true)) {
            return RAMOS_PRIORITY_NORMAL;
        }

        return $priority;
    }

    /**
     * Normalise status value.
     *
     * @param  string|null $status
     * @return string
     */
    public function sanitizeStatus(?string $status): string
    {
        $status = strtolower((string) $status);

        $allowed = array_keys(ramos_order_statuses());

        if (!in_array($status, $allowed, true)) {
            return RAMOS_ORDER_STATUS_NEW;
        }

        return $status;
    }

    /**
     * Clean common fields before insert/update.
     *
     * @param  array $data
     * @param  bool  $isCreate
     * @return array
     */
    protected function prepareOrderPayload(array $data, bool $isCreate, $recordId = null): array
    {
        $payload = [];

        if (array_key_exists('order_number', $data)) {
            $candidate = trim((string) $data['order_number']);
            if ($candidate === '' || $this->order_number_exists($candidate, $recordId)) {
                $candidate = $this->generateUniqueOrderNumber();
            }
            $payload['order_number'] = $candidate;
        } elseif ($isCreate) {
            $payload['order_number'] = $this->generateUniqueOrderNumber();
        }

        if (array_key_exists('customer_name', $data)) {
            $payload['customer_name'] = trim((string) $data['customer_name']);
        }

        if (array_key_exists('customer_reference', $data)) {
            $payload['customer_reference'] = $data['customer_reference'] ?: null;
        }

        if (array_key_exists('client_id', $data)) {
            $payload['client_id'] = $data['client_id'] ?: null;
        }

        if (array_key_exists('delivery_address', $data)) {
            $payload['delivery_address'] = trim((string) $data['delivery_address']);
        }

        if (array_key_exists('delivery_datetime', $data)) {
            $payload['delivery_datetime'] = $data['delivery_datetime'] ?: null;
        }

        if (array_key_exists('priority', $data)) {
            $payload['priority'] = $this->sanitizePriority($data['priority']);
        } elseif ($isCreate) {
            $payload['priority'] = RAMOS_PRIORITY_NORMAL;
        }

        if (array_key_exists('status', $data)) {
            $payload['status'] = $this->sanitizeStatus($data['status']);
        } elseif ($isCreate) {
            $payload['status'] = RAMOS_ORDER_STATUS_NEW;
        }

        if (array_key_exists('notes', $data)) {
            $note = trim((string) $data['notes']);
            $payload['notes'] = $note === '' ? null : $note;
        }

        if (array_key_exists('invoice_id', $data)) {
            $payload['invoice_id'] = $data['invoice_id'] ?: null;
        }

        if (array_key_exists('invoiced_at', $data)) {
            $payload['invoiced_at'] = $data['invoiced_at'] ?: null;
        }

        if (array_key_exists('import_batch', $data)) {
            $payload['import_batch'] = $data['import_batch'];
        }

        if ($isCreate) {
            $payload['created_at'] = date('Y-m-d H:i:s');
            $payload['created_by'] = get_staff_user_id();
        }

        return $payload;
    }

    /**
     * Generate guaranteed unique order number.
     *
     * @return string
     */
    protected function generateUniqueOrderNumber(): string
    {
        do {
            $orderNumber = $this->generate_order_number();
        } while ($this->order_number_exists($orderNumber));

        return $orderNumber;
    }

    protected function trigger_new_order_notification(int $orderId, array $data): void
    {
        if (!isset($this->notifications_model)) {
            $this->load->model('ramos/notifications_model', 'notifications_model');
        }

        if (!isset($this->notifications_model)) {
            return;
        }

        $orderNumber = $data['order_number'] ?? ('#' . $orderId);
        $customer    = $data['customer_name'] ?? _l('ramos_orders_label');

        $title   = _l('ramos_notifications_order_new_title', $orderNumber);
        $message = _l('ramos_notifications_order_new_body', $customer);

        $this->notifications_model->record('order_new', $title, $message, [
            'severity'     => 'info',
            'context_type' => 'order',
            'context_id'   => $orderId,
            'metadata'     => [
                'priority' => $data['priority'] ?? RAMOS_PRIORITY_NORMAL,
                'status'   => $data['status'] ?? RAMOS_ORDER_STATUS_NEW,
            ],
        ]);
    }
}
