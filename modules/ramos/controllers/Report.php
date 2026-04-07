<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Report extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }
    }

    public function index(): void
    {
        $dateFrom = $this->input->get('date_from') ?: date('Y-m-01');
        $dateTo   = $this->input->get('date_to')   ?: date('Y-m-d');
        $moduleId = (int) ($this->input->get('module_id') ?: 0);
        $staffId  = (int) ($this->input->get('staff_id')  ?: 0);

        $records  = $this->_get_records($dateFrom, $dateTo, $moduleId, $staffId);
        $modules  = $this->_get_all_modules();
        $staff    = $this->_get_all_staff();

        $data['title']     = _l('ramos_report_title');
        $data['records']   = $records;
        $data['modules']   = $modules;
        $data['staff']     = $staff;
        $data['date_from'] = $dateFrom;
        $data['date_to']   = $dateTo;
        $data['module_id'] = $moduleId;
        $data['staff_id']  = $staffId;

        $this->load->view('ramos/report/index', $data);
    }

    public function export(): void
    {
        $dateFrom = $this->input->get('date_from') ?: date('Y-m-01');
        $dateTo   = $this->input->get('date_to')   ?: date('Y-m-d');
        $moduleId = (int) ($this->input->get('module_id') ?: 0);
        $staffId  = (int) ($this->input->get('staff_id')  ?: 0);

        $records = $this->_get_records($dateFrom, $dateTo, $moduleId, $staffId);

        $filename = 'module_usage_' . $dateFrom . '_' . $dateTo . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');

        fputcsv($out, [
            _l('ramos_report_col_date'),
            _l('ramos_report_col_module'),
            _l('ramos_report_col_staff'),
            _l('ramos_report_col_role'),
            _l('ramos_report_col_shift_start'),
            _l('ramos_report_col_shift_end'),
            _l('ramos_report_col_duration'),
        ]);

        foreach ($records as $row) {
            fputcsv($out, [
                $row['shift_date'],
                $row['module_name'],
                $row['staff_name'],
                ucfirst($row['role']),
                $row['shift_started_at'],
                $row['shift_ended_at'] ?: '',
                $row['duration'],
            ]);
        }

        fclose($out);
        exit;
    }

    // -------------------------------------------------------------------------

    protected function _moduleStaffHasRoleColumn(): bool
    {
        static $cache = null;
        if ($cache === null) {
            $cache = $this->db->field_exists('role', db_prefix() . 'ramos_module_staff');
        }

        return $cache;
    }

    protected function _get_records(string $dateFrom, string $dateTo, int $moduleId, int $staffId): array
    {
        $roleExpr = $this->_moduleStaffHasRoleColumn() ? 'ms.role' : "'operator' as role";

        $this->db
            ->select([
                'ms.id',
                'DATE(ms.shift_started_at) as shift_date',
                'm.display_name as module_name',
                "CONCAT(s.firstname, ' ', s.lastname) as staff_name",
                's.staffid',
                $roleExpr,
                'ms.shift_started_at',
                'ms.shift_ended_at',
                'ms.module_id',
            ], false)
            ->from(db_prefix() . 'ramos_module_staff ms')
            ->join(db_prefix() . 'ramos_modules m', 'm.id = ms.module_id', 'left')
            ->join(db_prefix() . 'staff s', 's.staffid = ms.staff_id', 'left')
            ->where('DATE(ms.shift_started_at) >=', $dateFrom)
            ->where('DATE(ms.shift_started_at) <=', $dateTo)
            ->order_by('ms.shift_started_at', 'DESC');

        if ($moduleId > 0) {
            $this->db->where('ms.module_id', $moduleId);
        }

        if ($staffId > 0) {
            $this->db->where('ms.staff_id', $staffId);
        }

        $rows = $this->db->get()->result_array();

        foreach ($rows as &$row) {
            $row['duration'] = $this->_format_duration($row['shift_started_at'], $row['shift_ended_at']);
        }
        unset($row);

        return $rows;
    }

    protected function _format_duration(?string $start, ?string $end): string
    {
        if (empty($start)) {
            return '';
        }

        $endTs = empty($end) ? time() : strtotime($end);
        $diff  = max(0, $endTs - strtotime($start));

        $hours   = (int) floor($diff / 3600);
        $minutes = (int) floor(($diff % 3600) / 60);

        return $hours . 'h ' . $minutes . 'm' . (empty($end) ? ' *' : '');
    }

    protected function _get_all_modules(): array
    {
        return $this->db
            ->select('id, display_name as name')
            ->order_by('display_name', 'ASC')
            ->get(db_prefix() . 'ramos_modules')
            ->result_array();
    }

    protected function _get_all_staff(): array
    {
        return $this->db
            ->select("staffid, CONCAT(firstname, ' ', lastname) as full_name", false)
            ->where('active', 1)
            ->order_by('firstname', 'ASC')
            ->get(db_prefix() . 'staff')
            ->result_array();
    }
}
