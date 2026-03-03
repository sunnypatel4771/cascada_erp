<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Automation Model
 *
 * Handles database operations for automation runs tracking
 */
class Automation_model extends App_Model
{
    protected $table;

    public function __construct()
    {
        parent::__construct();
        $this->table = db_prefix() . 'ramos_automation_runs';
    }

    /**
     * Create a new automation run record
     *
     * @param  array $data
     * @return int Run ID
     */
    public function create_run(array $data): int
    {
        $payload = [
            'run_type' => $data['run_type'] ?? 'purchase_generation',
            'run_by'   => $data['run_by'] ?? get_staff_user_id(),
            'status'   => $data['status'] ?? 'running',
            'run_at'   => date('Y-m-d H:i:s'),
        ];

        $this->db->insert($this->table, $payload);

        return (int) $this->db->insert_id();
    }

    /**
     * Complete an automation run
     *
     * @param  int   $runId
     * @param  array $data
     * @return bool
     */
    public function complete_run(int $runId, array $data): bool
    {
        $payload = [
            'completed_at' => date('Y-m-d H:i:s'),
        ];

        if (isset($data['status'])) {
            $payload['status'] = $data['status'];
        }

        if (isset($data['total_orders_processed'])) {
            $payload['total_orders_processed'] = (int) $data['total_orders_processed'];
        }

        if (isset($data['total_purchase_orders_created'])) {
            $payload['total_purchase_orders_created'] = (int) $data['total_purchase_orders_created'];
        }

        if (isset($data['notes'])) {
            $payload['notes'] = $data['notes'];
        }

        if (isset($data['summary'])) {
            $payload['summary'] = is_array($data['summary']) ? json_encode($data['summary']) : $data['summary'];
        }

        $this->db->where('id', $runId);

        return $this->db->update($this->table, $payload);
    }

    /**
     * Update the routes generated count for an automation run
     *
     * @param  int $runId
     * @param  int $routesCount
     * @return bool
     */
    public function update_run_routes(int $runId, int $routesCount): bool
    {
        $this->db->where('id', $runId);

        return $this->db->update($this->table, [
            'routes_generated_count' => max(0, (int) $routesCount)
        ]);
    }

    /**
     * Get recent automation runs
     *
     * @param  int $limit
     * @return array
     */
    public function get_recent_runs(int $limit = 10): array
    {
        $this->db->select('ar.*, CONCAT(s.firstname, " ", s.lastname) as run_by_name');
        $this->db->from($this->table . ' ar');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = ar.run_by', 'left');
        $this->db->order_by('ar.run_at', 'DESC');
        $this->db->limit($limit);

        return $this->db->get()->result_array();
    }

    /**
     * Get automation run by ID
     *
     * @param  int $runId
     * @return array
     */
    public function get_run(int $runId): array
    {
        $this->db->select('ar.*, CONCAT(s.firstname, " ", s.lastname) as run_by_name');
        $this->db->from($this->table . ' ar');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = ar.run_by', 'left');
        $this->db->where('ar.id', $runId);

        $run = $this->db->get()->row_array();

        return $run ?: [];
    }

    /**
     * Get automation runs with filters
     *
     * @param  array $filters
     * @return array
     */
    public function get_runs(array $filters = []): array
    {
        $this->db->select('ar.*, CONCAT(s.firstname, " ", s.lastname) as run_by_name');
        $this->db->from($this->table . ' ar');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = ar.run_by', 'left');

        if (isset($filters['status'])) {
            $this->db->where('ar.status', $filters['status']);
        }

        if (isset($filters['run_type'])) {
            $this->db->where('ar.run_type', $filters['run_type']);
        }

        if (isset($filters['date_from'])) {
            $this->db->where('DATE(ar.run_at) >=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $this->db->where('DATE(ar.run_at) <=', $filters['date_to']);
        }

        $this->db->order_by('ar.run_at', 'DESC');

        if (isset($filters['limit'])) {
            $this->db->limit($filters['limit']);
        }

        return $this->db->get()->result_array();
    }

    /**
     * Get unprocessed orders from omni_sales module (tblcart)
     *
     * All orders except cancelled/returned AND processed_for_purchase = 0
     * Excludes cancelled orders (status 5) and return orders (original_order_id IS NOT NULL)
     *
     * @return array
     */
    public function get_unprocessed_omni_orders(): array
    {
        $this->db->select('c.*');
        $this->db->from(db_prefix() . 'cart c');
        // REMOVED: Status check - now includes all orders except cancelled
        // Old: $this->db->where('c.status', 2); // Status 2 = confirmed orders
        // NEW: Exclude only cancelled orders (status 5)
        $this->db->where('c.status !=', 5); // Exclude cancelled orders
        $this->db->where('(c.processed_for_purchase IS NULL OR c.processed_for_purchase = 0)', null, false);
        $this->db->where('c.channel_id IN (1,2,4,6)', null, false); // Valid sales channels
        $this->db->where('c.original_order_id IS NULL', null, false); // Exclude return orders
        $this->db->order_by('c.datecreator', 'ASC');

        return $this->db->get()->result_array();
    }

    /**
     * Count unprocessed orders from omni_sales module
     *
     * @return int
     */
    public function count_unprocessed_omni_orders(): int
    {
        $this->db->from(db_prefix() . 'cart');
        // REMOVED: Status check - now includes all orders except cancelled
        // Old: $this->db->where('status', 2); // Status 2 = confirmed orders
        // NEW: Exclude only cancelled orders (status 5)
        $this->db->where('status !=', 5); // Exclude cancelled orders
        $this->db->where('(processed_for_purchase IS NULL OR processed_for_purchase = 0)', null, false);
        $this->db->where('channel_id IN (1,2,4,6)', null, false); // Valid sales channels
        $this->db->where('original_order_id IS NULL', null, false); // Exclude return orders

        return $this->db->count_all_results();
    }

    /**
     * Get required quantities from omni_sales cart detail items
     *
     * Aggregates quantities by product_id (which maps to inventory_item_id)
     *
     * @param  array $orderIds Array of cart IDs
     * @return array Keyed by inventory_item_id (product_id)
     */
    public function get_required_quantities_from_omni_orders(array $orderIds): array
    {
        if (empty($orderIds)) {
            return [];
        }

        // Get all cart detail items for the specified orders
        // product_id in cart_detailt maps to id in tblitems (inventory items)
        $this->db->select('cd.product_id as inventory_item_id, SUM(cd.quantity) as required_qty');
        $this->db->from(db_prefix() . 'cart_detailt cd');
        $this->db->where_in('cd.cart_id', $orderIds);
        $this->db->where('cd.product_id IS NOT NULL', null, false);
        $this->db->where('cd.product_id > 0', null, false);
        $this->db->group_by('cd.product_id');

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

    /**
     * Mark omni_sales orders (tblcart) as processed
     *
     * Adds processed_for_purchase flag and tracking fields to tblcart table
     *
     * @param  array $orderIds Array of cart IDs
     * @param  int   $automationRunId
     * @return bool
     */
    public function mark_omni_orders_as_processed(array $orderIds, int $automationRunId): bool
    {
        if (empty($orderIds)) {
            return false;
        }

        // Check if columns exist in tblcart, if not we'll need to add them
        // For now, we'll attempt the update
        $updateData = [
            'processed_for_purchase' => 1,
            'processed_at'           => date('Y-m-d H:i:s'),
            'automation_run_id'      => $automationRunId
        ];

        $this->db->where_in('id', $orderIds);
        $this->db->update(db_prefix() . 'cart', $updateData);

        return $this->db->affected_rows() > 0;
    }

    /**
     * Get warehouse inventory aggregated by product (commodity_id)
     *
     * Uses warehouse module's tblinventory_manage table which tracks actual stock
     * Returns array keyed by commodity_id (product_id from tblitems)
     *
     * @return array
     */
    public function get_warehouse_inventory_by_product(): array
    {
        // Get aggregated stock from warehouse module
        // tblinventory_manage.commodity_id links to tblitems.id (product_id)
        $this->db->select('im.commodity_id as id');
        $this->db->select('i.description as item_name');
        $this->db->select('i.commodity_code as sku');
        $this->db->select('i.unit_id');
        $this->db->select('SUM(im.inventory_number) as quantity', false);
        $this->db->from(db_prefix() . 'inventory_manage im');
        $this->db->join(db_prefix() . 'items i', 'i.id = im.commodity_id', 'left');
        $this->db->where('im.commodity_id IS NOT NULL', null, false);
        $this->db->where('im.commodity_id > 0', null, false);
        $this->db->group_by('im.commodity_id');

        $results = $this->db->get()->result_array();

        $inventoryMap = [];
        foreach ($results as $row) {
            // Add default values for fields expected by the purchase model
            $row['safety_stock'] = 0;
            $row['buffer_percent'] = 0;
            $row['active'] = 1;
            $row['supplier_id'] = null; // Warehouse items don't have supplier mapping yet
            $row['unit'] = $row['unit_id'] ?? null; // Map unit_id to unit field
            $inventoryMap[$row['id']] = $row;
        }

        return $inventoryMap;
    }

    /**
     * Get unprocessed ERP portal orders
     *
     * ERP orders are distinguished by having clientnote like "portal"
     * Filters for unpaid orders (status = 1) that haven't been processed yet
     *
     * @return array
     */
    public function get_unprocessed_erp_orders(): array
    {
        $this->db->select('i.id, i.clientid, i.date as datecreator, i.total, i.status');
        $this->db->select('"invoice" as order_source'); // Mark as ERP
        $this->db->from(db_prefix() . 'invoices i');
        $this->db->where('i.status', 1); // Status 1 = sent/unpaid
        $this->db->where('i.clientnote IS NOT NULL', null, false);
        $this->db->where('i.clientnote LIKE "%portal%"', null, false); // Portal orders only
        $this->db->where('i.recurring', 0); // Not recurring (recurring field is 0, not NULL)
        $this->db->where('(i.processed_for_purchase IS NULL OR i.processed_for_purchase = 0)', null, false);
        $this->db->order_by('i.date', 'ASC');

        return $this->db->get()->result_array();
    }

    /**
     * Count unprocessed ERP portal orders
     *
     * @return int
     */
    public function count_unprocessed_erp_orders(): int
    {
        $this->db->from(db_prefix() . 'invoices');
        $this->db->where('status', 1); // Unpaid
        $this->db->where('clientnote IS NOT NULL', null, false);
        $this->db->where('clientnote LIKE "%portal%"', null, false);
        $this->db->where('recurring', 0); // Not recurring
        $this->db->where('(processed_for_purchase IS NULL OR processed_for_purchase = 0)', null, false);

        return $this->db->count_all_results();
    }

    /**
     * Get required quantities from ERP invoice items
     *
     * ERP invoices store line items in tblitemable with rel_type='invoice'
     * Maps item descriptions to inventory items by matching descriptions
     *
     * @param  array $invoiceIds Array of invoice IDs
     * @return array Keyed by inventory_item_id with quantities
     */
    public function get_required_quantities_from_erp_orders(array $invoiceIds): array
    {
        if (empty($invoiceIds)) {
            return [];
        }

        // Get invoice items from tblitemable
        // ERP portal invoices store items in tblitemable with rel_type='invoice'
        $this->db->select('ia.description, SUM(ia.qty) as qty, i.id as inventory_item_id');
        $this->db->from(db_prefix() . 'itemable ia');
        $this->db->join(db_prefix() . 'items i', 'i.description = ia.description', 'left');
        $this->db->where_in('ia.rel_id', $invoiceIds);
        $this->db->where('ia.rel_type', 'invoice');
        $this->db->where('ia.qty IS NOT NULL', null, false);
        $this->db->where('ia.qty > 0', null, false);
        $this->db->group_by('ia.description, i.id');

        $results = $this->db->get()->result_array();

        // Return quantities keyed by inventory_item_id
        // Only include items that could be matched to inventory
        $quantities = [];
        foreach ($results as $row) {
            if (!empty($row['inventory_item_id'])) {
                $itemId = (int) $row['inventory_item_id'];
                $qty = (float) $row['qty'];
                if ($itemId > 0 && $qty > 0) {
                    // Add quantities if same item appears multiple times
                    if (isset($quantities[$itemId])) {
                        $quantities[$itemId] += $qty;
                    } else {
                        $quantities[$itemId] = $qty;
                    }
                }
            }
        }

        return $quantities;
    }

    /**
     * Mark ERP orders as processed
     *
     * Updates processed_for_purchase flag in tblinvoices table
     * Note: processed_for_purchase column must exist in tblinvoices
     *
     * @param  array $invoiceIds Array of invoice IDs
     * @param  int   $automationRunId
     * @return bool
     */
    public function mark_erp_orders_as_processed(array $invoiceIds, int $automationRunId): bool
    {
        if (empty($invoiceIds)) {
            return false;
        }

        $updateData = [
            'processed_for_purchase' => 1,
            'automation_run_id'      => $automationRunId
        ];

        $this->db->where_in('id', $invoiceIds);
        $this->db->update(db_prefix() . 'invoices', $updateData);

        return $this->db->affected_rows() > 0;
    }

    /**
     * Update ERP invoice priority and zone
     *
     * @param int $invoiceId Invoice ID
     * @param int|null $priority Priority (1-9, null to not update)
     * @param string|null $zone Zone name (null to not update)
     * @return bool
     */
    public function update_erp_order_priority_zone(int $invoiceId, ?int $priority = null, ?string $zone = null): bool
    {
        $updateData = [];

        if ($priority !== null) {
            $priority = max(1, min(9, (int) $priority)); // Constrain to 1-9
            $updateData['priority'] = $priority;
        }

        if ($zone !== null) {
            $updateData['zone'] = trim((string) $zone) ?: null;
        }

        if (empty($updateData)) {
            return false;
        }

        $this->db->where('id', (int) $invoiceId);
        $this->db->update(db_prefix() . 'invoices', $updateData);

        return $this->db->affected_rows() > 0;
    }

    /**
     * Get ERP orders that need zone/priority assignment
     * Used for admin dashboard to see which orders need setup before routing
     *
     * @return array
     */
    public function get_erp_orders_needing_zone_assignment(): array
    {
        return $this->db
            ->select('i.id, i.number as order_number, cl.company as customer_name, i.date, i.duedate, i.priority, i.zone')
            ->from(db_prefix() . 'invoices i')
            ->join(db_prefix() . 'clients cl', 'cl.userid = i.clientid', 'left')
            ->where('i.status', 1) // Unpaid invoices
            ->where('i.zone IS NULL', null, false) // No zone assigned
            ->where("i.clientnote LIKE '%portal%'", null, false) // Portal orders only
            ->order_by('i.date', 'DESC')
            ->get()
            ->result_array();
    }

    /**
     * Backfill ERP orders with customer's zone and priority from profile custom fields
     * Updates all unpaid ERP portal orders that have NULL zones or default priority
     *
     * @return array Summary of updated orders
     */
    public function backfill_erp_orders_from_customer_profile(): array
    {
        // Get all unpaid ERP portal orders
        $orders = $this->db
            ->select('i.id, i.clientid, i.zone, i.priority')
            ->from(db_prefix() . 'invoices i')
            ->where('i.status', 1) // Unpaid invoices
            ->where("i.clientnote LIKE '%portal%'", null, false) // Portal orders only
            ->get()
            ->result_array();

        $updated = 0;
        $summary = [];

        foreach ($orders as $order) {
            // Get customer's zone and priority from custom fields
            $customer_zone = get_validated_customer_zone($order['clientid'], DEFAULT_DELIVERY_ZONE);
            $customer_priority = get_validated_customer_priority($order['clientid'], DEFAULT_PRIORITY_LEVEL);

            // Check if update is needed (zone is NULL or priority is default and customer has custom priority)
            $needs_update = false;
            $update_data = [];

            if ($order['zone'] !== $customer_zone) {
                $update_data['zone'] = $customer_zone;
                $needs_update = true;
            }

            if ((int) $order['priority'] !== $customer_priority) {
                $update_data['priority'] = $customer_priority;
                $needs_update = true;
            }

            if ($needs_update) {
                $this->db->where('id', (int) $order['id']);
                $this->db->update(db_prefix() . 'invoices', $update_data);
                $updated++;

                $summary[] = [
                    'invoice_id' => $order['id'],
                    'zone' => $customer_zone,
                    'priority' => $customer_priority,
                ];
            }
        }

        return [
            'total_orders' => count($orders),
            'updated' => $updated,
            'details' => $summary,
        ];
    }
}

