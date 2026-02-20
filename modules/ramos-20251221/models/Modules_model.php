<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Modules_model extends App_Model
{
    protected $modulesTable;
    protected $moduleProductsTable;
    protected $moduleStaffTable;

    public function __construct()
    {
        parent::__construct();
        $this->modulesTable        = db_prefix() . 'ramos_modules';
        $this->moduleProductsTable = db_prefix() . 'ramos_module_products';
        $this->moduleStaffTable    = db_prefix() . 'ramos_module_staff';
    }

    public function get_modules(bool $withProducts = true, bool $withStaff = true): array
    {
        $modules = $this->db
            ->order_by('display_name', 'ASC')
            ->get($this->modulesTable)
            ->result_array();

        if (empty($modules)) {
            return [];
        }

        $moduleIds = array_column($modules, 'id');

        $productsMap = [];
        if ($withProducts) {
            $productsMap = $this->get_products_map($moduleIds);
        }

        $staffMap = [];
        if ($withStaff) {
            $staffMap = $this->get_staff_map($moduleIds);
        }

        foreach ($modules as &$module) {
            $moduleId = (int) $module['id'];
            $module['products'] = $productsMap[$moduleId] ?? [];
            $module['staff']    = $staffMap[$moduleId] ?? [];
        }
        unset($module);

        return $modules;
    }

    public function get_module($moduleId): array
    {
        $this->db->where('id', $moduleId);
        $module = $this->db->get($this->modulesTable)->row_array();

        if (!$module) {
            return [];
        }

        $module['products'] = $this->get_products_map([$moduleId])[$moduleId] ?? [];
        $module['staff']    = $this->get_staff_map([$moduleId])[$moduleId] ?? [];

        return $module;
    }

    public function update_module($moduleId, array $data): bool
    {
        $payload = [];

        if (isset($data['display_name'])) {
            $payload['display_name'] = trim((string) $data['display_name']);
        }

        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = (int) (bool) $data['is_active'];
        }

        if (empty($payload)) {
            return false;
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');

        $this->db->where('id', $moduleId);

        return $this->db->update($this->modulesTable, $payload);
    }

    public function set_module_products($moduleId, array $inventoryIds): void
    {
        $inventoryIds = array_values(array_unique(array_filter(array_map('intval', $inventoryIds))));

        $this->db->where('module_id', $moduleId);
        $existing = $this->db->get($this->moduleProductsTable)->result_array();

        $existingIds = array_column($existing, 'inventory_item_id');

        $toInsert = array_diff($inventoryIds, $existingIds);
        $toDelete = array_diff($existingIds, $inventoryIds);

        if (!empty($toInsert)) {
            foreach ($toInsert as $inventoryId) {
                $this->db->insert($this->moduleProductsTable, [
                    'module_id'        => $moduleId,
                    'inventory_item_id'=> $inventoryId,
                ]);
            }
        }

        if (!empty($toDelete)) {
            $this->db->where('module_id', $moduleId);
            $this->db->where_in('inventory_item_id', $toDelete);
            $this->db->delete($this->moduleProductsTable);
        }
    }

    public function start_shift($moduleId, $staffId): bool
    {
        $moduleId = (int) $moduleId;
        $staffId  = (int) $staffId;

        if ($moduleId <= 0 || $staffId <= 0) {
            return false;
        }

        $this->db->where(['module_id' => $moduleId, 'staff_id' => $staffId, 'shift_ended_at' => null]);
        if ($this->db->count_all_results($this->moduleStaffTable) > 0) {
            return false;
        }

        $this->db->where(['module_id' => $moduleId, 'shift_ended_at' => null]);
        if ($this->db->count_all_results($this->moduleStaffTable) >= 2) {
            return false;
        }

        return $this->db->insert($this->moduleStaffTable, [
            'module_id'       => $moduleId,
            'staff_id'        => $staffId,
            'shift_started_at'=> date('Y-m-d H:i:s'),
            'shift_ended_at'  => null,
        ]);
    }

    public function end_shift($recordId): bool
    {
        $this->db->where('id', $recordId);
        $this->db->where('shift_ended_at IS NULL', null, false);

        return $this->db->update($this->moduleStaffTable, [
            'shift_ended_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function get_active_module_ids_for_staff($staffId): array
    {
        $rows = $this->db
            ->select('module_id')
            ->from($this->moduleStaffTable)
            ->where('staff_id', (int) $staffId)
            ->where('shift_ended_at IS NULL', null, false)
            ->get()
            ->result_array();

        return array_map('intval', array_column($rows, 'module_id'));
    }

    public function staff_has_active_shift($moduleId, $staffId): bool
    {
        return $this->db
            ->where('module_id', (int) $moduleId)
            ->where('staff_id', (int) $staffId)
            ->where('shift_ended_at IS NULL', null, false)
            ->count_all_results($this->moduleStaffTable) > 0;
    }

    protected function get_products_map(array $moduleIds): array
    {
        if (empty($moduleIds)) {
            return [];
        }

        $this->db->select('mp.module_id, mp.inventory_item_id, inv.item_name, inv.unit');
        $this->db->from($this->moduleProductsTable . ' mp');
        $this->db->join(db_prefix() . 'ramos_inventory_items inv', 'inv.id = mp.inventory_item_id', 'left');
        $this->db->where_in('mp.module_id', $moduleIds);
        $this->db->order_by('inv.item_name', 'ASC');

        $results = $this->db->get()->result_array();

        $map = [];
        foreach ($results as $row) {
            $map[(int) $row['module_id']][] = $row;
        }

        return $map;
    }

    protected function get_staff_map(array $moduleIds): array
    {
        if (empty($moduleIds)) {
            return [];
        }

        $this->db->select('ms.*, st.firstname, st.lastname');
        $this->db->from($this->moduleStaffTable . ' ms');
        $this->db->join(db_prefix() . 'staff st', 'st.staffid = ms.staff_id', 'left');
        $this->db->where_in('ms.module_id', $moduleIds);
        $this->db->order_by('ms.shift_started_at', 'DESC');

        $results = $this->db->get()->result_array();

        $map = [];
        foreach ($results as $row) {
            $map[(int) $row['module_id']][] = $row;
        }

        return $map;
    }
}
