<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Order_items_model extends App_Model
{
    protected $table;

    public function __construct()
    {
        parent::__construct();
        $this->table = db_prefix() . 'ramos_order_items';
    }

    /**
     * Get order items.
     *
     * @param  int|null $id
     * @param  array    $filters
     * @return array
     */
    public function get($id = null, array $filters = []): array
    {
        if ($id !== null) {
            $this->db->where('id', $id);
            $item = $this->db->get($this->table)->row_array();

            return $item ?: [];
        }

        if (!empty($filters['order_id'])) {
            $this->db->where('order_id', (int) $filters['order_id']);
        }

        $this->db->order_by('item_name', 'ASC');

        return $this->db->get($this->table)->result_array();
    }

    /**
     * Store order item.
     *
     * @param  array $data
     * @return int
     */
    public function create(array $data): int
    {
        $payload = $this->preparePayload($data);

        $this->db->insert($this->table, $payload);

        return (int) $this->db->insert_id();
    }

    /**
     * Delete order item.
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
     * Aggregate required quantities per inventory item.
     *
     * @param  array $orderStatuses
     * @return array
     */
    public function get_required_quantities(array $orderStatuses = []): array
    {
        $this->db->select('inventory_item_id, SUM(quantity) as required_qty');
        $this->db->from($this->table . ' oi');
        $this->db->join(db_prefix() . 'ramos_orders o', 'o.id = oi.order_id', 'inner');

        if (!empty($orderStatuses)) {
            $this->db->where_in('o.status', $orderStatuses);
        }

        $this->db->group_by('inventory_item_id');

        $results = $this->db->get()->result_array();

        $quantities = [];
        foreach ($results as $row) {
            $itemId = (int) $row['inventory_item_id'];
            if ($itemId <= 0) {
                continue;
            }
            $quantities[$itemId] = (float) $row['required_qty'];
        }

        return $quantities;
    }

    /**
     * Prepare payload.
     *
     * @param  array $data
     * @return array
     */
    protected function preparePayload(array $data): array
    {
        $payload = [
            'order_id'          => (int) $data['order_id'],
            'inventory_item_id' => !empty($data['inventory_item_id']) ? (int) $data['inventory_item_id'] : null,
            'item_name'         => trim((string) $data['item_name']),
            'quantity'          => (float) $data['quantity'],
            'created_at'        => date('Y-m-d H:i:s'),
            'created_by'        => get_staff_user_id(),
        ];

        if (!empty($data['ripeness'])) {
            $allowed = ['maduro', 'verde'];
            $ripeness = strtolower(trim((string) $data['ripeness']));
            $payload['ripeness'] = in_array($ripeness, $allowed) ? $ripeness : null;
        } else {
            $payload['ripeness'] = null;
        }

        return $payload;
    }

    /**
     * Get required quantities for specific order IDs
     *
     * @param  array $orderIds
     * @return array Keyed by inventory_item_id
     */
    public function get_required_quantities_by_orders(array $orderIds): array
    {
        if (empty($orderIds)) {
            return [];
        }

        $this->db->select('inventory_item_id, SUM(quantity) as required_qty');
        $this->db->from($this->table);
        $this->db->where_in('order_id', $orderIds);
        $this->db->where('inventory_item_id IS NOT NULL', null, false);
        $this->db->group_by('inventory_item_id');

        $results = $this->db->get()->result_array();

        $quantities = [];
        foreach ($results as $row) {
            $itemId = (int) $row['inventory_item_id'];
            if ($itemId > 0) {
                $quantities[$itemId] = (float) $row['required_qty'];
            }
        }

        return $quantities;
    }
}
