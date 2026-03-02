<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Purchase_model extends App_Model
{
    protected $batchesTable;
    protected $batchItemsTable;

    public function __construct()
    {
        parent::__construct();
        $this->batchesTable     = db_prefix() . 'ramos_purchase_batches';
        $this->batchItemsTable  = db_prefix() . 'ramos_purchase_batch_items';
    }

    /**
     * Generate the next batch code.
     *
     * @return string
     */
    public function generate_batch_code(): string
    {
        $prefix = 'PO-' . date('Ymd') . '-';
        $this->db->like('batch_code', $prefix, 'after');
        $this->db->order_by('batch_code', 'DESC');
        $this->db->limit(1);

        $row = $this->db->get($this->batchesTable)->row();

        if (!$row) {
            $next = 1;
        } else {
            $suffix = (int) preg_replace('/[^0-9]/', '', str_replace($prefix, '', $row->batch_code));
            $next   = $suffix + 1;
        }

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Return deficits grouped by supplier.
     *
     * @param  array $requiredQuantities keyed by inventory item id
     * @param  array $inventoryItems keyed by id
     * @param  array $openPurchaseQuantities keyed by inventory item id
     * @return array
     */
    public function build_supplier_deficits(array $requiredQuantities, array $inventoryItems, array $openPurchaseQuantities = []): array
    {
        $groups = [];

        foreach ($requiredQuantities as $itemId => $requiredQty) {
            if (!isset($inventoryItems[$itemId])) {
                continue;
            }

            $inventoryRecord = $inventoryItems[$itemId];
            $currentStock    = (float) $inventoryRecord['quantity'];
            $safetyStock     = (float) $inventoryRecord['safety_stock'];
            $bufferPercent   = (float) $inventoryRecord['buffer_percent'];
            $supplierId      = $inventoryRecord['supplier_id'] ?? null;

            $openQty         = isset($openPurchaseQuantities[$itemId]) ? (float) $openPurchaseQuantities[$itemId] : 0.0;

            $netRequired     = (float) $requiredQty - $currentStock - $openQty;

            if ($netRequired <= 0) {
                continue;
            }

            if ($safetyStock > 0) {
                $netRequired = max($netRequired, $safetyStock - ($currentStock + $openQty));
            }

            $statusKey = ramos_inventory_status($currentStock, $safetyStock, $bufferPercent);

            $groups[$supplierId]['items'][] = [
                'inventory_item_id' => $itemId,
                'item_name'         => $inventoryRecord['item_name'],
                'unit'              => $inventoryRecord['unit'],
                'required_qty'      => round($netRequired, 2),
                'current_stock'     => $currentStock,
                'safety_stock'      => $safetyStock,
                'status'            => $statusKey,
            ];
        }

        return $groups;
    }

    /**
     * Fetch open purchase quantities per inventory item for statuses.
     *
     * @param  array $statuses
     * @return array
     */
    public function get_open_purchase_quantities(array $statuses = ['approved']): array
    {
        if (empty($statuses)) {
            return [];
        }

        $this->db->select('bi.inventory_item_id, SUM(GREATEST(bi.requested_qty - bi.received_qty, 0)) as qty');
        $this->db->from($this->batchItemsTable . ' bi');
        $this->db->join($this->batchesTable . ' b', 'b.id = bi.batch_id', 'inner');
        $this->db->where_in('b.status', $statuses);
        $this->db->group_by('bi.inventory_item_id');

        $result = $this->db->get()->result_array();

        $quantities = [];
        foreach ($result as $row) {
            $itemId = (int) $row['inventory_item_id'];
            if ($itemId <= 0) {
                continue;
            }

            $remaining = (float) $row['qty'];

            if ($remaining <= 0) {
                continue;
            }

            $quantities[$itemId] = $remaining;
        }

        return $quantities;
    }

    /**
     * Create purchase batch.
     *
     * @param  int|null $supplierId
     * @param  array    $items
     * @return int
     */
    public function create_batch($supplierId, array $items): int
    {
        $batchCode = $this->generate_batch_code();
        $now       = date('Y-m-d H:i:s');

        $batchData = [
            'batch_code'   => $batchCode,
            'supplier_id'  => $supplierId ?: null,
            'status'       => 'draft',
            'total_items'  => count($items),
            'created_at'   => $now,
            'created_by'   => get_staff_user_id(),
            'approved_at'  => null,
            'approved_by'  => null,
            'sent_at'      => null,
            'completed_at' => null,
            'notes'        => null,
        ];

        $this->db->insert($this->batchesTable, $batchData);
        $batchId = (int) $this->db->insert_id();

        foreach ($items as $item) {
            $this->db->insert($this->batchItemsTable, [
                'batch_id'         => $batchId,
                'inventory_item_id'=> $item['inventory_item_id'],
                'order_item_id'    => $item['order_item_id'] ?? null,
                'requested_qty'    => (float) $item['requested_qty'],
                'received_qty'     => 0.0,
                'current_stock'    => (float) $item['current_stock'],
                'safety_stock'     => (float) $item['safety_stock'],
            ]);
        }

        return $batchId;
    }

    public function get_batches(array $statuses = []): array
    {
        $this->db->select('b.*, s.supplier_name');
        $this->db->from($this->batchesTable . ' b');
        $this->db->join(db_prefix() . 'ramos_suppliers s', 's.id = b.supplier_id', 'left');

        if (!empty($statuses)) {
            $this->db->where_in('b.status', $statuses);
        }

        $this->db->order_by('b.created_at', 'DESC');

        return $this->db->get()->result_array();
    }

    public function get_batch($batchId): array
    {
        $this->db->select('b.*, s.supplier_name');
        $this->db->from($this->batchesTable . ' b');
        $this->db->join(db_prefix() . 'ramos_suppliers s', 's.id = b.supplier_id', 'left');
        $this->db->where('b.id', $batchId);

        $batch = $this->db->get()->row_array();

        return $batch ?: [];
    }

    public function get_batch_items($batchId): array
    {
        // Try joining with ramos inventory items first, then fall back to main items table
        $this->db->select('bi.*');
        $this->db->select('COALESCE(inv.item_name, items.description) as inventory_name');
        $this->db->select('COALESCE(inv.unit, items.unit_id) as unit');
        $this->db->from($this->batchItemsTable . ' bi');
        $this->db->join(db_prefix() . 'ramos_inventory_items inv', 'inv.id = bi.inventory_item_id', 'left');
        $this->db->join(db_prefix() . 'items items', 'items.id = bi.inventory_item_id', 'left');
        $this->db->where('bi.batch_id', $batchId);

        return $this->db->get()->result_array();
    }

    public function mark_sent($batchId, ?string $notes = null): bool
    {
        $data = [
            'status'  => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
        ];

        if ($notes !== null) {
            $notes = trim((string) $notes);
            $data['notes'] = $notes === '' ? null : $notes;
        }

        $this->db->where('id', $batchId);

        return $this->db->update($this->batchesTable, $data);
    }

    public function update_batch_notes($batchId, string $notes): bool
    {
        $this->db->where('id', $batchId);
        return $this->db->update($this->batchesTable, ['notes' => $notes]);
    }

    public function record_receipts($batchId, array $receipts): array
    {
        $this->db->trans_start();

        $applied = [];

        foreach ($receipts as $itemId => $quantity) {
            $quantity = (float) $quantity;

            if ($quantity <= 0) {
                continue;
            }

            $item = $this->db->get_where($this->batchItemsTable, [
                'id'       => $itemId,
                'batch_id' => $batchId,
            ])->row_array();

            if (!$item) {
                continue;
            }

            $requested = (float) $item['requested_qty'];
            $received  = (float) $item['received_qty'];
            $remaining = max($requested - $received, 0);

            if ($remaining <= 0) {
                continue;
            }

            $appliedQty = min($remaining, $quantity);

            $this->db->set('received_qty', 'received_qty + ' . $this->db->escape($appliedQty), false);
            $this->db->where('id', $itemId);
            $this->db->update($this->batchItemsTable);

            $applied[$itemId] = $appliedQty;
        }

        $this->db->trans_complete();

        if (!$this->db->trans_status()) {
            return [];
        }

        return $applied;
    }

    public function refresh_batch_status($batchId): string
    {
        $items = $this->get_batch_items($batchId);

        if (empty($items)) {
            return 'draft';
        }

        $totalItems    = count($items);
        $fullyReceived = 0;
        $anyReceived   = false;

        foreach ($items as $item) {
            if ((float) $item['received_qty'] > 0) {
                $anyReceived = true;
            }

            if ((float) $item['received_qty'] >= (float) $item['requested_qty']) {
                $fullyReceived++;
            }
        }

        if ($fullyReceived === $totalItems && $totalItems > 0) {
            $status = 'received';
            $update = [
                'status'       => $status,
                'completed_at' => date('Y-m-d H:i:s'),
                'approved_at'  => date('Y-m-d H:i:s'),
                'approved_by'  => get_staff_user_id(),
            ];
        } elseif ($anyReceived) {
            $status = 'partial';
            $update = [
                'status'      => $status,
                'approved_at' => date('Y-m-d H:i:s'),
                'approved_by' => get_staff_user_id(),
            ];
        } else {
            $status = $this->get_batch($batchId)['status'] ?? 'draft';
            $update = ['status' => $status];
        }

        $this->db->where('id', $batchId);
        $this->db->update($this->batchesTable, $update);

        return $status;
    }

    /**
     * Load recent batches.
     *
     * @param  int $limit
     * @return array
     */
    public function get_recent_batches($limit = 10): array
    {
        $this->db->select('b.*, s.supplier_name');
        $this->db->from($this->batchesTable . ' b');
        $this->db->join(db_prefix() . 'ramos_suppliers s', 's.id = b.supplier_id', 'left');
        $this->db->order_by('b.created_at', 'DESC');
        $this->db->limit($limit);

        return $this->db->get()->result_array();
    }
}
