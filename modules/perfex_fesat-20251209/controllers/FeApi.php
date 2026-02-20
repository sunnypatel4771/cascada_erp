<?php

defined('BASEPATH') or exit('No direct script access allowed');

class FeApi extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('perfex_fesat/fe_sat_model', 'feSatModel');
    }

    public function callback()
    {
        $payload = $this->input->raw_input_stream;

        $result = $this->feSatModel->handle_webhook($payload, $this->input->server('HTTP_X_SIGNATURE') ?? '');

        $statusCode = $result['success'] ? 200 : 400;

        $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => $result['success'],
                'message' => $result['message'],
            ]));
    }
}
