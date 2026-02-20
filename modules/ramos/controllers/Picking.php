<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Picking extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $this->load->model('ramos/modules_model', 'modules_model');
        $this->load->model('ramos/inventory_model', 'inventory_model');
        $this->load->model('ramos/picking_model', 'picking_model');
        $this->load->model('staff_model');
    }

    public function index(): void
    {
        $modules   = $this->modules_model->get_modules(true, true);

        // COMMENTED: Ramos inventory - replaced with warehouse commodity list
        // $inventory = $this->inventory_model->get(null, ['active' => 1]);

        // NEW: Get warehouse commodity list items (tblitems)
        $inventory = $this->get_warehouse_commodities();

        $staff     = $this->staff_model->get('', ['active' => 1]);

        $inventoryOptions = [];
        foreach ($inventory as $item) {
            // NEW: Using warehouse commodity fields (description, unit_name)
            $inventoryOptions[] = [
                'id'   => $item['id'],
                'name' => trim($item['description'] . ' (' . $item['unit_name'] . ')'),
            ];
        }

        $data['title']             = _l('ramos_picking_title');
        $data['subtitle']          = _l('ramos_picking_subtitle');
        $data['modules']           = $modules;
        $data['inventory_options'] = $inventoryOptions;
        $data['staff_members']     = $staff;

        $this->load->view('picking/manage', $data);
    }

    public function update_module($moduleId): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $module = $this->modules_model->get_module($moduleId);
        if (empty($module)) {
            show_404();
        }

        $displayName = $this->input->post('display_name');
        $isActive    = $this->input->post('is_active') ? 1 : 0;

        $success = $this->modules_model->update_module($moduleId, [
            'display_name' => $displayName,
            'is_active'    => $isActive,
        ]);

        if ($success) {
            set_alert('success', _l('ramos_picking_module_updated'));
        } else {
            set_alert('warning', _l('ramos_picking_module_update_failed'));
        }

        redirect(admin_url('ramos/picking'));
    }

    public function update_products($moduleId): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $module = $this->modules_model->get_module($moduleId);
        if (empty($module)) {
            show_404();
        }

        $products = $this->input->post('product_ids');
        $products = is_array($products) ? $products : [];

        $this->modules_model->set_module_products($moduleId, $products);

        $this->picking_model->sync_module_assignments($moduleId);

        set_alert('success', _l('ramos_picking_products_updated'));

        redirect(admin_url('ramos/picking'));
    }

    public function start_shift($moduleId): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $module = $this->modules_model->get_module($moduleId);
        if (empty($module)) {
            show_404();
        }

        $staffId = (int) $this->input->post('staff_id');
        $role = $this->input->post('role');

        // Validate role - default to operator if invalid
        if (!in_array($role, ['operator', 'supervisor'], true)) {
            $role = 'operator';
        }

        if ($staffId <= 0) {
            set_alert('warning', _l('ramos_picking_select_staff'));
            redirect(admin_url('ramos/picking'));
        }

        $success = $this->modules_model->start_shift($moduleId, $staffId, $role);

        if ($success) {
            set_alert('success', _l('ramos_picking_shift_started'));
        } else {
            set_alert('warning', _l('ramos_picking_shift_start_failed'));
        }

        redirect(admin_url('ramos/picking'));
    }

    public function end_shift($recordId): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $record = $this->db->get_where(db_prefix() . 'ramos_module_staff', ['id' => $recordId])->row_array();
        if (!$record) {
            show_404();
        }

        $success = $this->modules_model->end_shift($recordId);

        if ($success) {
            set_alert('success', _l('ramos_picking_shift_finished'));
        } else {
            set_alert('warning', _l('ramos_picking_shift_finish_failed'));
        }

        redirect(admin_url('ramos/picking'));
    }

    public function console(): void
    {
        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        [$moduleData, $hasModules] = $this->get_console_modules();

        // Supervisors can edit items on any module they can see
        $staffId = get_staff_user_id();
        $isSupervisor = $this->modules_model->staff_is_supervisor($staffId);

        $data['title']          = _l('ramos_picking_console_title');
        $data['subtitle']       = _l('ramos_picking_console_subtitle');
        $data['module_data']    = $moduleData;
        $data['can_edit']       = staff_can('edit', RAMOS_MODULE_NAME) || is_admin() || $isSupervisor;
        $data['has_modules']    = $hasModules;

        $this->load->view('picking/console', $data);
    }

    public function console_refresh(): void
    {
        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        [$moduleData, $hasModules] = $this->get_console_modules();

        $statusLabels = [
            'red'    => 'label-danger',
            'yellow' => 'label-warning',
            'green'  => 'label-success',
        ];

        // Supervisors can edit items on any module they can see
        $staffId = get_staff_user_id();
        $isSupervisor = $this->modules_model->staff_is_supervisor($staffId);
        $canEdit = staff_can('edit', RAMOS_MODULE_NAME) || is_admin() || $isSupervisor;

        $html = '';
        if ($hasModules) {
            $html = $this->render_console_modules($moduleData, $statusLabels, $canEdit);
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => true,
                'html'    => $html,
                'hasData' => $hasModules,
            ]));
    }

    public function update_item($pickId): void
    {
        if (!$this->input->post()) {
            redirect(admin_url('ramos/picking/console'));
        }

        $pick = $this->picking_model->get_pick_item($pickId);

        if (empty($pick)) {
            show_404();
        }

        $staffId  = get_staff_user_id();
        $moduleId = (int) $pick['module_id'];
        $isSupervisor = $this->modules_model->staff_is_supervisor($staffId);

        // Managers, admins, and supervisors can update any pick item
        // Regular operators must have an active shift on the module
        if (!is_admin() && !staff_can('edit', RAMOS_MODULE_NAME) && !$isSupervisor) {
            if (!$this->modules_model->staff_has_active_shift($moduleId, $staffId)) {
                access_denied();
            }
        }

        $pickedQty = (float) $this->input->post('picked_qty');
        $weight    = (float) $this->input->post('weight');

        $this->picking_model->update_pick_item($pickId, $pickedQty, $weight, $staffId);

        set_alert('success', _l('ramos_picking_item_updated'));

        redirect(admin_url('ramos/picking/console'));
    }

    protected function get_console_modules(): array
    {
        $staffId      = get_staff_user_id();
        $allModules   = $this->modules_model->get_modules(true, true);
        $visible      = [];

        // Supervisors, admins, and users with edit permission can see all modules
        $isSupervisor = $this->modules_model->staff_is_supervisor($staffId);

        if (is_admin() || staff_can('edit', RAMOS_MODULE_NAME) || $isSupervisor) {
            $visible = $allModules;
        } else {
            // Operators only see modules where they have an active shift
            $allowedIds = $this->modules_model->get_active_module_ids_for_staff($staffId);
            foreach ($allModules as $module) {
                if (in_array((int) $module['id'], $allowedIds, true)) {
                    $visible[] = $module;
                }
            }
        }

        $moduleData = [];
        foreach ($visible as $module) {
            $orders = $this->picking_model->get_orders_for_module((int) $module['id']);
            $moduleData[] = [
                'module' => $module,
                'orders' => $orders,
            ];
        }

        return [$moduleData, count($moduleData) > 0];
    }

    protected function render_console_modules(array $moduleData, array $statusLabels, bool $canEdit): string
    {
        $buffer = '';
        foreach ($moduleData as $entry) {
            $buffer .= $this->load->view('ramos/picking/partials/module_card', [
                'module'       => $entry['module'],
                'orders'       => $entry['orders'],
                'statusLabels' => $statusLabels,
                'can_edit'     => $canEdit,
            ], true);
        }

        return $buffer;
    }

    /**
     * Get warehouse commodity list items
     *
     * Fetches items from tblitems (warehouse commodity list)
     * with unit names from tblware_unit_type
     *
     * @return array
     */
    protected function get_warehouse_commodities(): array
    {
        $this->db->select('i.id');
        $this->db->select('i.description');
        $this->db->select('i.commodity_code');
        $this->db->select('i.unit_id');
        $this->db->select('u.unit_name');
        $this->db->from(db_prefix() . 'items i');
        $this->db->join(db_prefix() . 'ware_unit_type u', 'u.unit_type_id = i.unit_id', 'left');
        $this->db->where('i.id IS NOT NULL', null, false);
        $this->db->where('i.id > 0', null, false);
        $this->db->order_by('i.description', 'ASC');

        return $this->db->get()->result_array();
    }
}
