<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Gps extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!is_staff_logged_in()) {
            redirect(admin_url('authentication'));
        }
    }

    public function index()
    {
        $targetUrl = function_exists('get_option') ? get_option('gps_target_url') : (defined('GPS_DEFAULT_URL') ? GPS_DEFAULT_URL : 'http://176.57.189.120');
        if (empty($targetUrl)) {
            $targetUrl = defined('GPS_DEFAULT_URL') ? GPS_DEFAULT_URL : 'http://176.57.189.120';
        }

        // Log access (best-effort)
        $this->log_access();

        $data = [];
        $data['title'] = _l('gps_menu_name');
        $data['target_url'] = $targetUrl;

        $this->load->view('gps', $data);
    }

    private function log_access()
    {
        try {
            $table = db_prefix() . 'gps_access_log';
            if (!$this->db->table_exists($table)) {
                return;
            }

            $payload = [
                'staff_id'    => (int) get_staff_user_id(),
                'accessed_at' => date('Y-m-d H:i:s'),
                'ip_address'  => $this->input->ip_address(),
                'user_agent'  => substr((string) $this->input->user_agent(), 0, 191),
            ];

            $this->db->insert($table, $payload);
        } catch (Exception $e) {
            // Silent fail; logging should not break the page
        }
    }
}
