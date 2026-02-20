<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Picking_model extends App_Model
{
    protected $pickTable;

    public function __construct()
    {
        parent::__construct();
        $this->pickTable = db_prefix() . 'ramos_pick_items';
    }

    public function ensure_pick_records_for_order($orderId): void
    {
        $orderId = (int) $orderId;
        if ($orderId <= 0) {
            return;
        }

        $this->ensure_pick_records($this->db
            ->select('oi.id as order_item_id, oi.order_id, oi.quantity, mp.module_id')
            ->from(db_prefix() . 'ramos_order_items oi')
            ->join(db_prefix() . 'ramos_orders o', 'o.id = oi.order_id', 'inner')
            ->join(db_prefix() . 'ramos_module_products mp', 'mp.inventory_item_id = oi.inventory_item_id', 'inner')
            ->where('oi.order_id', $orderId)
            ->get()
            ->result_array());
    }

    public function ensure_pick_record_for_item($orderItemId): void
    {
        $orderItemId = (int) $orderItemId;
        if ($orderItemId <= 0) {
            return;
        }

        $rows = $this->db
            ->select('oi.id as order_item_id, oi.order_id, oi.quantity, mp.module_id')
            ->from(db_prefix() . 'ramos_order_items oi')
            ->join(db_prefix() . 'ramos_module_products mp', 'mp.inventory_item_id = oi.inventory_item_id', 'inner')
            ->where('oi.id', $orderItemId)
            ->get()
            ->result_array();

        $this->ensure_pick_records($rows);
    }

    public function ensure_pick_records_for_module($moduleId): void
    {
        $moduleId = (int) $moduleId;
        if ($moduleId <= 0) {
            return;
        }

        $rows = $this->db
            ->select('oi.id as order_item_id, oi.order_id, oi.quantity, mp.module_id')
            ->from(db_prefix() . 'ramos_order_items oi')
            ->join(db_prefix() . 'ramos_orders o', 'o.id = oi.order_id', 'inner')
            ->join(db_prefix() . 'ramos_module_products mp', 'mp.inventory_item_id = oi.inventory_item_id', 'inner')
            ->where('mp.module_id', $moduleId)
            ->where_in('o.status', ['new', 'processing'])
            ->get()
            ->result_array();

        $this->ensure_pick_records($rows);
    }

    public function sync_module_assignments($moduleId): void
    {
        $moduleId = (int) $moduleId;
        if ($moduleId <= 0) {
            return;
        }

        $validItemIds = $this->db
            ->select('oi.id')
            ->from(db_prefix() . 'ramos_order_items oi')
            ->join(db_prefix() . 'ramos_module_products mp', 'mp.inventory_item_id = oi.inventory_item_id', 'inner')
            ->where('mp.module_id', $moduleId)
            ->get()
            ->result_array();

        $validItemIds = array_map('intval', array_column($validItemIds, 'id'));

        $currentRecords = $this->db
            ->select('id, order_item_id')
            ->from($this->pickTable)
            ->where('module_id', $moduleId)
            ->get()
            ->result_array();

        foreach ($currentRecords as $record) {
            if (!in_array((int) $record['order_item_id'], $validItemIds, true)) {
                $this->db->delete($this->pickTable, ['id' => (int) $record['id']]);
            }
        }

        $this->ensure_pick_records_for_module($moduleId);
    }

    protected function ensure_pick_records(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $moduleId     = (int) ($row['module_id'] ?? 0);
            $orderItemId  = (int) ($row['order_item_id'] ?? 0);
            $orderId      = (int) ($row['order_id'] ?? 0);
            $requiredQty  = (float) ($row['quantity'] ?? 0);

            if ($moduleId <= 0 || $orderItemId <= 0 || $orderId <= 0) {
                continue;
            }

            $exists = $this->db
                ->where('order_item_id', $orderItemId)
                ->where('module_id', $moduleId)
                ->count_all_results($this->pickTable) > 0;

            if ($exists) {
                $this->db
                    ->where('order_item_id', $orderItemId)
                    ->where('module_id', $moduleId)
                    ->update($this->pickTable, ['required_qty' => $requiredQty]);
                continue;
            }

            $this->db->insert($this->pickTable, [
                'order_id'       => $orderId,
                'order_item_id'  => $orderItemId,
                'module_id'      => $moduleId,
                'required_qty'   => $requiredQty,
                'picked_qty'     => 0,
                'weight'         => 0,
                'status'         => 'pending',
                'updated_by'     => null,
                'updated_at'     => null,
            ]);
        }
    }

    public function get_pick_item($pickId): array
    {
        $pick = $this->db->get_where($this->pickTable, ['id' => (int) $pickId])->row_array();

        return $pick ?: [];
    }

    public function update_pick_item($pickId, float $pickedQty, float $weight, int $userId): bool
    {
        $pick = $this->get_pick_item($pickId);
        if (empty($pick)) {
            return false;
        }

        $requiredQty = (float) $pick['required_qty'];

        $data = [
            'picked_qty' => max(0, min($pickedQty, $requiredQty)),
            'weight'     => max(0, $weight),
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $status = 'pending';

        if ($data['picked_qty'] > 0 && $data['picked_qty'] < $requiredQty) {
            $status = 'in_progress';
        }

        if ($data['picked_qty'] >= $requiredQty) {
            if ($data['weight'] > 0) {
                $status = 'completed';
            } else {
                $status = 'weight_missing';
            }
        }

        if ($data['picked_qty'] <= 0) {
            $data['weight'] = 0;
        }

        $data['status'] = $status;

        $this->db->where('id', $pickId);
        $updated = $this->db->update($this->pickTable, $data);

        if ($updated) {
            $orderId = (int) $pick['order_id'];
            $this->refresh_order_status($orderId);
            $this->maybeGenerateInvoice($orderId);
        }

        return $updated;
    }

    public function remove_pick_records_for_item($orderItemId): void
    {
        $record = $this->db->get_where($this->pickTable, ['order_item_id' => (int) $orderItemId])->row_array();
        $orderId = $record ? (int) $record['order_id'] : null;

        $this->db->delete($this->pickTable, ['order_item_id' => (int) $orderItemId]);

        if ($orderId) {
            $this->refresh_order_status($orderId);
        }
    }

    public function get_orders_for_module($moduleId): array
    {
        $moduleId = (int) $moduleId;
        if ($moduleId <= 0) {
            return [];
        }

        $this->ensure_pick_records_for_module($moduleId);

        $rows = $this->db
            ->select([
                'o.id as order_id',
                'o.order_number',
                'o.customer_name',
                'o.delivery_address',
                'o.priority',
                'o.status as order_status',
                'pi.id as pick_id',
                'pi.required_qty',
                'pi.picked_qty',
                'pi.weight',
                'pi.status as pick_status',
                'oi.item_name',
                'oi.quantity',
                'pi.module_id',
                'inv.unit'
            ])
            ->from($this->pickTable . ' pi')
            ->join(db_prefix() . 'ramos_orders o', 'o.id = pi.order_id', 'inner')
            ->join(db_prefix() . 'ramos_order_items oi', 'oi.id = pi.order_item_id', 'inner')
            ->join(db_prefix() . 'ramos_module_products mp', 'mp.module_id = pi.module_id AND mp.inventory_item_id = oi.inventory_item_id', 'left')
            ->join(db_prefix() . 'ramos_inventory_items inv', 'inv.id = oi.inventory_item_id', 'left')
            ->where('pi.module_id', $moduleId)
            ->where_in('o.status', ['new', 'processing'])
            ->order_by('o.priority', 'DESC')
            ->order_by('o.id', 'ASC')
            ->get()
            ->result_array();

        if (empty($rows)) {
            return [];
        }

        $orders = [];
        foreach ($rows as $row) {
            $orderId = (int) $row['order_id'];

            if (!isset($orders[$orderId])) {
                $orders[$orderId] = [
                    'order_id'        => $orderId,
                    'order_number'    => $row['order_number'],
                    'customer_name'   => $row['customer_name'],
                    'delivery_address'=> $row['delivery_address'],
                    'priority'        => $row['priority'],
                    'items'           => [],
                ];
            }

            $orders[$orderId]['items'][] = [
                'pick_id'     => (int) $row['pick_id'],
                'item_name'   => $row['item_name'],
                'unit'        => $row['unit'],
                'required_qty'=> (float) $row['required_qty'],
                'picked_qty'  => (float) $row['picked_qty'],
                'weight'      => (float) $row['weight'],
                'pick_status' => $row['pick_status'],
            ];
        }

        $result = [];

        foreach ($orders as $order) {
            $statuses = array_column($order['items'], 'pick_status');

            $totalItems = count($order['items']);
            $completed  = count(array_filter($statuses, fn($status) => $status === 'completed'));

            if ($completed === $totalItems) {
                // exclude fully completed orders
                continue;
            }

            $displayStatus = 'red';

            if (!in_array('pending', $statuses, true) && !in_array('in_progress', $statuses, true)) {
                if (in_array('weight_missing', $statuses, true)) {
                    $displayStatus = 'yellow';
                } else {
                    $displayStatus = 'green';
                }
            }

            $order['status'] = $displayStatus;
            $order['progress'] = $totalItems > 0 ? round(($completed / $totalItems) * 100) : 0;

            $result[] = $order;
        }

        return $result;
    }

    protected function refresh_order_status(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $statuses = $this->db
            ->select('status')
            ->from($this->pickTable)
            ->where('order_id', $orderId)
            ->get()
            ->result_array();

        if (empty($statuses)) {
            return;
        }

        $statusValues = array_column($statuses, 'status');

        $allCompleted = count(array_filter($statusValues, fn($status) => $status === 'completed')) === count($statusValues);
        $anyInProgress = in_array('in_progress', $statusValues, true) || in_array('weight_missing', $statusValues, true) || in_array('completed', $statusValues, true);

        if ($allCompleted) {
            $newStatus = RAMOS_ORDER_STATUS_READY;
        } elseif ($anyInProgress) {
            $newStatus = RAMOS_ORDER_STATUS_PROCESSING;
        } else {
            $newStatus = RAMOS_ORDER_STATUS_NEW;
        }

        $this->db->where('id', $orderId);
        $this->db->update(db_prefix() . 'ramos_orders', ['status' => $newStatus]);
    }

    protected function maybeGenerateInvoice(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $order = $this->db
            ->select('id, status, invoice_id, order_number')
            ->from(db_prefix() . 'ramos_orders')
            ->where('id', $orderId)
            ->get()
            ->row_array();

        if (empty($order) || $order['status'] !== RAMOS_ORDER_STATUS_READY || !empty($order['invoice_id'])) {
            return;
        }

        $CI = &get_instance();
        $CI->load->library('ramos/ramos_invoice_generator', null, 'ramos_invoice_generator');

        $result = $CI->ramos_invoice_generator->generate_for_order($orderId);

        if (!empty($result['message'])) {
            set_alert($result['success'] ? 'success' : 'warning', $result['message']);
        }

        if (!empty($result['warnings'])) {
            foreach ($result['warnings'] as $warning) {
                set_alert('warning', $warning);
            }
        }
    }
}
