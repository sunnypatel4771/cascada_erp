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

        if (array_key_exists('purchase_price', $data)) {
            $value = $data['purchase_price'];
            $payload['purchase_price'] = ($value === '' || $value === null) ? null : max(0, (float) $value);
        }

        if (array_key_exists('has_maduracion', $data)) {
            $payload['has_maduracion'] = (int) (bool) $data['has_maduracion'];
        }

        if ($isCreate) {
            $payload['created_at'] = date('Y-m-d H:i:s');
            $payload['created_by'] = get_staff_user_id();
        }

        return $payload;
    }

    // -----------------------------------------------------------------------
    // Equivalencias (alternate unit definitions per catalog item)
    // -----------------------------------------------------------------------

    /**
     * Get all equivalencias for a catalog item (tblitems.id).
     *
     * @param  int $itemId  tblitems.id
     * @return array
     */
    public function get_equivalences(int $itemId): array
    {
        return $this->db
            ->where('item_id', $itemId)
            ->where('active', 1)
            ->order_by('sort_order', 'ASC')
            ->order_by('unit_name', 'ASC')
            ->get(db_prefix() . 'ramos_item_equivalences')
            ->result_array();
    }

    /**
     * Get all equivalencias grouped by item_id (bulk fetch).
     *
     * @param  array $itemIds  List of tblitems.id values
     * @return array  [ item_id => [ ['unit_name'=>..,'conversion_factor'=>..], ... ] ]
     */
    public function get_equivalences_bulk(array $itemIds): array
    {
        if (empty($itemIds)) {
            return [];
        }

        // Fetch all active equivalences (small table) and filter in PHP to avoid
        // large WHERE IN clauses that exceed CodeIgniter's query-builder regex limit.
        $rows = $this->db
            ->where('active', 1)
            ->order_by('item_id', 'ASC')
            ->order_by('sort_order', 'ASC')
            ->get(db_prefix() . 'ramos_item_equivalences')
            ->result_array();

        $idSet = array_flip($itemIds);
        $grouped = [];
        foreach ($rows as $row) {
            if (isset($idSet[(int) $row['item_id']])) {
                $grouped[(int) $row['item_id']][] = $row;
            }
        }

        return $grouped;
    }

    /**
     * Get has_maduracion for multiple tblitems by matching item description.
     *
     * @param  array $descriptions  List of item descriptions (tblitems.description)
     * @return array  [ description => has_maduracion (0|1) ]
     */
    public function get_maduracion_map(array $descriptions): array
    {
        if (empty($descriptions)) {
            return [];
        }

        $descSet = array_flip($descriptions);
        $map = [];

        // --- Source 1: tblramos_inventory_items.has_maduracion (legacy manual flag) ---
        $rows = $this->db
            ->select('item_name, has_maduracion')
            ->where('has_maduracion', 1)
            ->get($this->table)
            ->result_array();

        foreach ($rows as $row) {
            if (isset($descSet[$row['item_name']])) {
                $map[$row['item_name']] = 1;
            }
        }

        // --- Source 2: tblcustomfieldsvalues for the 'maduracion' custom field on items ---
        // This is the authoritative source shown on the admin commodity list (value = 'Sí').
        $cfTable  = db_prefix() . 'customfieldsvalues';
        $cfDef    = db_prefix() . 'customfields';
        $itemsTbl = db_prefix() . 'items';

        if ($this->db->table_exists($cfTable) && $this->db->table_exists($cfDef) && $this->db->table_exists($itemsTbl)) {
            $cfRows = $this->db->query(
                "SELECT i.description, cv.value
                   FROM `{$cfTable}` cv
                   JOIN `{$cfDef}` cf  ON cf.id = cv.fieldid
                   JOIN `{$itemsTbl}` i ON i.id  = cv.relid
                  WHERE cf.name = 'maduracion'
                    AND cf.fieldto = 'items'"
            )->result_array();

            foreach ($cfRows as $row) {
                $desc  = $row['description'];
                $value = trim(strtolower((string) $row['value']));
                if (!isset($descSet[$desc])) {
                    continue;
                }
                // 'sí', 'si', 'yes', '1', 'true' → has maduracion
                $isYes = in_array($value, ['sí', 'si', 'yes', '1', 'true', 's'], true);
                if ($isYes) {
                    $map[$desc] = 1;
                } elseif (!isset($map[$desc])) {
                    // Only set 0 when the legacy source didn't already mark it as 1.
                    $map[$desc] = 0;
                }
            }
        }

        return $map;
    }

    /**
     * Add or update an equivalencia entry.
     *
     * @param  array $data  ['item_id', 'unit_name', 'conversion_factor', 'sort_order']
     * @return int  Inserted/updated ID
     */
    public function save_equivalence(array $data): int
    {
        $payload = [
            'item_id'           => (int) $data['item_id'],
            'unit_name'         => trim((string) $data['unit_name']),
            'conversion_factor' => (float) ($data['conversion_factor'] ?? 1.0),
            'sort_order'        => (int) ($data['sort_order'] ?? 0),
            'active'            => 1,
            'created_by'        => get_staff_user_id(),
        ];

        if (!empty($data['id'])) {
            $this->db->where('id', (int) $data['id']);
            $this->db->update(db_prefix() . 'ramos_item_equivalences', $payload);
            return (int) $data['id'];
        }

        $this->db->insert(db_prefix() . 'ramos_item_equivalences', $payload);
        return (int) $this->db->insert_id();
    }

    /**
     * Delete an equivalencia entry.
     *
     * @param  int $id
     * @return bool
     */
    public function delete_equivalence(int $id): bool
    {
        $this->db->where('id', $id);
        return (bool) $this->db->delete(db_prefix() . 'ramos_item_equivalences');
    }

    /**
     * Get all catalog items (tblitems) with their maduracion and equivalencias.
     * Used for the admin equivalencias management page.
     *
     * @return array
     */
    public function get_items_with_equivalences(): array
    {
        $items = $this->db
            ->select('i.id, i.description, i.unit, COALESCE(inv.has_maduracion, 0) as has_maduracion')
            ->from(db_prefix() . 'items i')
            ->join(
                db_prefix() . 'ramos_inventory_items inv',
                'inv.item_name = i.description',
                'left'
            )
            ->where('i.parent_id', 0)
            ->or_where('i.parent_id IS NULL', null, false)
            ->order_by('i.description', 'ASC')
            ->get()
            ->result_array();

        if (empty($items)) {
            return [];
        }

        $ids = array_column($items, 'id');
        $equivMap = $this->get_equivalences_bulk($ids);

        foreach ($items as &$item) {
            $item['equivalences'] = $equivMap[(int) $item['id']] ?? [];
        }
        unset($item);

        return $items;
    }
}
