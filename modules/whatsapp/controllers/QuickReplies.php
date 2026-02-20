<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class QuickReplies extends AdminController {

    public function __construct() {
        parent::__construct();
        $this->load->model('QuickReply_model');
        $this->load->helper('url');
        $this->load->library('form_validation');

        // Ensure the user has the necessary permissions
        if (!has_permission('quickreplies', '', 'view')) {
            access_denied('quickreplies');
        }
    }

    public function index() {
        if (!has_permission('quickreplies', '', 'view')) {
            access_denied('quickreplies');
        }

        $data['quick_replies'] = $this->QuickReply_model->get_all_replies();
        $this->load->view('quick_replies/index', $data);
    }

    public function store() {
        if (!has_permission('quickreplies', '', 'create')) {
            echo json_encode(['status' => 'error', 'message' => 'Access denied']);
            return;
        }

        $this->form_validation->set_rules('name', 'Name', 'required');
        $this->form_validation->set_rules('message', 'Message', 'required');
        
        if ($this->form_validation->run() == FALSE) {
            echo json_encode(['status' => 'error', 'message' => validation_errors()]);
        } else {
            $data = [
                'name' => $this->input->post('name'),
                'message' => $this->input->post('message')
            ];
            $this->QuickReply_model->insert_reply($data);
            echo json_encode(['status' => 'success']);
        }
    }

    public function update($id) {
        if (!has_permission('quickreplies', '', 'edit')) {
            echo json_encode(['status' => 'error', 'message' => 'Access denied']);
            return;
        }

        $this->form_validation->set_rules('name', 'Name', 'required');
        $this->form_validation->set_rules('message', 'Message', 'required');
        
        if ($this->form_validation->run() == FALSE) {
            echo json_encode(['status' => 'error', 'message' => validation_errors()]);
        } else {
            $data = [
                'name' => $this->input->post('name'),
                'message' => $this->input->post('message')
            ];
            $this->QuickReply_model->update_reply($id, $data);
            echo json_encode(['status' => 'success']);
        }
    }

    public function delete($id) {
        if (!has_permission('quickreplies', '', 'delete')) {
            echo json_encode(['status' => 'error', 'message' => 'Access denied']);
            return;
        }

        $this->QuickReply_model->delete_reply($id);
        echo json_encode(['status' => 'success']);
    }
}
?>
