<?php

defined('BASEPATH') or exit('No direct script access allowed');


class Pricing_model extends App_Model
{
    protected $table;

    public function __construct()
    {
        parent::__construct();
        $this->table = db_prefix() . 'ramos_price_rules';
    }

    public function get($id = null, array $filters = []): array
    {
        if ($id !== null) {
            $this->db->where('id', $id);
            $row = $this->db->get($this->table)->row_array();

            return $row ?: [];
        }

        if (!empty($filters['inventory_item_id'])) {
            $this->db->where('inventory_item_id', (int) $filters['inventory_item_id']);
        }

        if (array_key_exists('customer_id', $filters)) {
            $customerId = $filters['customer_id'];
            if ($customerId === null) {
                $this->db->where('customer_id IS NULL', null, false);
            } else {
                $this->db->where('customer_id', (int) $customerId);
            }
        }

        if (isset($filters['active'])) {
            $this->db->where('active', (int) $filters['active']);
        }

        $this->db->order_by('customer_id IS NOT NULL', 'DESC', false);
        $this->db->order_by('created_at', 'DESC');

        return $this->db->get($this->table)->result_array();
    }

    public function create(array $data): int
    {
        $payload = $this->preparePayload($data, true);

        $this->db->insert($this->table, $payload);

        return (int) $this->db->insert_id();
    }

    public function update($id, array $data): bool
    {
        $payload = $this->preparePayload($data, false);

        if (empty($payload)) {
            return false;
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');
        $payload['updated_by'] = get_staff_user_id();

        $this->db->where('id', $id);

        return $this->db->update($this->table, $payload);
    }

    public function delete($id): bool
    {
        $this->db->where('id', $id);

        return $this->db->delete($this->table);
    }

    public function get_price_for_customer(int $inventoryItemId, ?int $customerId): ?array
    {
        if ($inventoryItemId <= 0) {
            return null;
        }

        $this->db->from($this->table);
        $this->db->where('inventory_item_id', $inventoryItemId);
        $this->db->where('active', 1);
        $this->db->where('(customer_id = ' . (int) $customerId . ' OR (customer_id IS NULL AND ' . ((int) $customerId) . ' IS NULL))', null, false);
        $this->db->order_by('customer_id IS NULL', 'ASC', false);
        $this->db->order_by('customer_id', 'DESC');
        $this->db->limit(1);

        $row = $this->db->get()->row_array();

        if ($row) {
            return $row;
        }

        // no exact match (customer), fallback to default
        $this->db->from($this->table);
        $this->db->where('inventory_item_id', $inventoryItemId);
        $this->db->where('active', 1);
        $this->db->where('customer_id IS NULL', null, false);
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit(1);

        $row = $this->db->get()->row_array();

        return $row ?: null;
    }

    protected function preparePayload(array $data, bool $isCreate): array
    {
        $payload = [];

        if ($isCreate) {
            $payload['inventory_item_id'] = (int) ($data['inventory_item_id'] ?? 0);
            if ($payload['inventory_item_id'] <= 0) {
                throw new \InvalidArgumentException('inventory_item_id required');
            }
        } elseif (array_key_exists('inventory_item_id', $data)) {
            $payload['inventory_item_id'] = (int) $data['inventory_item_id'];
        }

        if (array_key_exists('customer_id', $data)) {
            $payload['customer_id'] = !empty($data['customer_id']) ? (int) $data['customer_id'] : null;
        } elseif ($isCreate) {
            $payload['customer_id'] = null;
        }

        if (array_key_exists('price', $data)) {
            $payload['price'] = round((float) $data['price'], 2);
        } elseif ($isCreate) {
            $payload['price'] = 0.00;
        }

        if (array_key_exists('discount_percent', $data)) {
            $payload['discount_percent'] = max(0, min(100, (float) $data['discount_percent']));
        } elseif ($isCreate) {
            $payload['discount_percent'] = 0.00;
        }

        if (array_key_exists('currency', $data)) {
            $payload['currency'] = !empty($data['currency']) ? (int) $data['currency'] : null;
        } elseif ($isCreate) {
            $payload['currency'] = null;
        }

        if (array_key_exists('notes', $data)) {
            $note = trim((string) $data['notes']);
            $payload['notes'] = $note === '' ? null : $note;
        }

        if (array_key_exists('active', $data)) {
            $payload['active'] = (int) (bool) $data['active'];
        } elseif ($isCreate) {
            $payload['active'] = 1;
        }

        if ($isCreate) {
            $payload['created_at'] = date('Y-m-d H:i:s');
            $payload['created_by'] = get_staff_user_id();
        }

        return $payload;
    }
}
