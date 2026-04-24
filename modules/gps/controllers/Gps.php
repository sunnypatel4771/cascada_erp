<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Gps extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('gps/gps_model');
    }

    public function index()
    {
        if (!(has_permission('settings', '', 'view') || is_admin())) {
            access_denied('GPS');
        }

        $targetUrl = get_option('gps_target_url');
        if (!$targetUrl) {
            $targetUrl = 'http://176.57.189.120';
        }

        $data['title'] = _l('gps_dashboard_title');
        $data['target_url'] = $targetUrl;
        $data['recent_logs'] = $this->gps_model->get_recent_logs(20);

        $this->load->view('dashboard', $data);
    }

    public function log_open()
    {
        if (!(has_permission('settings', '', 'view') || is_admin())) {
            ajax_access_denied();
        }

        $targetUrl = $this->input->post('target_url', true);
        if (!$targetUrl) {
            $targetUrl = get_option('gps_target_url');
        }

        $this->gps_model->log_access($targetUrl, 'opened');
        echo json_encode(['success' => true]);
    }

    public function ping()
    {
        if (!(has_permission('settings', '', 'view') || is_admin())) {
            ajax_access_denied();
        }

        $targetUrl = get_option('gps_target_url');
        if (!$targetUrl) {
            $targetUrl = 'http://176.57.189.120';
        }

        $status = 'offline';
        $httpCode = 0;
        $error = '';

        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $targetUrl);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (curl_errno($ch)) {
                $error = curl_error($ch);
            }
            curl_close($ch);
        }

        if ($httpCode >= 200 && $httpCode < 500) {
            $status = 'online';
        }

        echo json_encode([
            'success' => true,
            'status' => $status,
            'http_code' => $httpCode,
            'error' => $error,
        ]);
    }
}
