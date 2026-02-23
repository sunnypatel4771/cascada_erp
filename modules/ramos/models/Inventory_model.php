<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Inventory_model extends App_Model
{
    /**
     * @var string
     */
    protected $table;

    public function __construct()
    {
        parent::__construct();
        $this->table = db_prefix() . 'ramos_inventory_items';
    }

    /**
     * Retrieve inventory items.
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

        if (isset($filters['active'])) {
            $this->db->where('active', (int) $filters['active']);
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $this->db
                ->group_start()
                ->like('item_name', $search)
                ->or_like('sku', $search)
                ->group_end();
        }

        $this->db->order_by('item_name', 'ASC');

        return $this->db->get($this->table)->result_array();
    }

    /**
     * Create inventory item.
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
     * Update inventory item.
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
     * Adjust quantity by delta.
     *
     * @param  int    $id
     * @param  float  $delta
     * @return bool
     */
    public function adjust_quantity($id, float $delta): bool
    {
        $item = $this->get($id);

        if (empty($item)) {
            return false;
        }

        $newQuantity = (float) $item['quantity'] + $delta;

        $this->db->where('id', $id);

        return $this->db->update($this->table, [
            'quantity'   => $newQuantity,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => get_staff_user_id(),
        ]);
    }

    /**
     * Delete inventory item.
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
     * Persist an image path for an inventory item.
     * Deletes the old file from disk if one already exists.
     *
     * @param  int    $id
     * @param  string $relativePath  Path relative to FCPATH (e.g. modules/ramos/uploads/item_img/3/photo.jpg)
     * @return bool
     */
    public function save_image(int $id, string $relativePath): bool
    {
        $existing = $this->get($id);
        if (!empty($existing['image_path']) && $existing['image_path'] !== $relativePath) {
            $oldFile = rtrim(FCPATH, '/') . '/' . ltrim($existing['image_path'], '/');
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        }

        $this->db->where('id', $id);

        return $this->db->update($this->table, [
            'image_path' => $relativePath,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => get_staff_user_id(),
        ]);
    }

    /**
     * Remove the image for an inventory item and delete the file.
     *
     * @param  int $id
     * @return bool
     */
    public function remove_image(int $id): bool
    {
        $existing = $this->get($id);
        if (!empty($existing['image_path'])) {
            $file = rtrim(FCPATH, '/') . '/' . ltrim($existing['image_path'], '/');
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        $this->db->where('id', $id);

        return $this->db->update($this->table, [
            'image_path' => null,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => get_staff_user_id(),
        ]);
    }

    /**
     * Prepare payload for create/update.
     *
     * @param  array $data
     * @param  bool  $isCreate
     * @return array
     */
    protected function preparePayload(array $data, bool $isCreate): array
    {
        $payload = [];

        if (array_key_exists('item_name', $data)) {
            $payload['item_name'] = trim((string) $data['item_name']);
        }

        if (array_key_exists('sku', $data)) {
            $sku = trim((string) $data['sku']);
            $payload['sku'] = $sku === '' ? null : $sku;
        }

        if (array_key_exists('unit', $data)) {
            $unit = trim((string) $data['unit']);
            $payload['unit'] = $unit === '' ? 'unit' : $unit;
        }

        if (array_key_exists('quantity', $data)) {
            $payload['quantity'] = (float) $data['quantity'];
        } elseif ($isCreate) {
            $payload['quantity'] = 0.0;
        }

        if (array_key_exists('safety_stock', $data)) {
            $payload['safety_stock'] = max(0, (float) $data['safety_stock']);
        } elseif ($isCreate) {
            $payload['safety_stock'] = 0.0;
        }

        if (array_key_exists('buffer_percent', $data)) {
            $payload['buffer_percent'] = max(0, (float) $data['buffer_percent']);
        }

        if (array_key_exists('supplier_id', $data)) {
            $payload['supplier_id'] = !empty($data['supplier_id']) ? (int) $data['supplier_id'] : null;
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

        if (array_key_exists('image_path', $data)) {
            $value = $data['image_path'];
            $payload['image_path'] = ($value === '' || $value === null) ? null : $value;
        }

        if ($isCreate) {
            $payload['created_at'] = date('Y-m-d H:i:s');
            $payload['created_by'] = get_staff_user_id();
        }

        return $payload;
    }
}
