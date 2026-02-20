<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Suppliers_model extends App_Model
{
    protected $table;

    public function __construct()
    {
        parent::__construct();
        $this->table = db_prefix() . 'ramos_suppliers';
    }

    /**
     * Get suppliers.
     *
     * @param  int|null $id
     * @param  bool     $onlyActive
     * @return array
     */
    public function get($id = null, bool $onlyActive = false): array
    {
        if ($id !== null) {
            $this->db->where('id', $id);
            $supplier = $this->db->get($this->table)->row_array();

            return $supplier ?: [];
        }

        if ($onlyActive) {
            $this->db->where('active', 1);
        }

        $this->db->order_by('supplier_name', 'ASC');

        return $this->db->get($this->table)->result_array();
    }

    /**
     * Create supplier.
     *
     * @param  array $data
     * @return int
     */
    public function create(array $data): int
    {
        $payload = $this->preparePayload($data, true);

        $this->db->insert($this->table, $payload);

        return (int) $this->db->insert_id();
    }

    /**
     * Update supplier.
     *
     * @param  int   $id
     * @param  array $data
     * @return bool
     */
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

    /**
     * Delete supplier.
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
     * Prepare payload.
     *
     * @param  array $data
     * @param  bool  $isCreate
     * @return array
     */
    protected function preparePayload(array $data, bool $isCreate): array
    {
        $payload = [];

        if (array_key_exists('supplier_name', $data)) {
            $payload['supplier_name'] = trim((string) $data['supplier_name']);
        }

        if (array_key_exists('contact_name', $data)) {
            $payload['contact_name'] = trim((string) $data['contact_name']);
        }

        if (array_key_exists('phone', $data)) {
            $payload['phone'] = trim((string) $data['phone']);
        }

        if (array_key_exists('email', $data)) {
            $email = trim((string) $data['email']);
            $payload['email'] = $email === '' ? null : $email;
        }

        if (array_key_exists('priority', $data)) {
            $priority = (int) $data['priority'];
            $payload['priority'] = $priority > 0 && $priority <= 999 ? $priority : 999;
        } elseif ($isCreate) {
            $payload['priority'] = 999;
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
