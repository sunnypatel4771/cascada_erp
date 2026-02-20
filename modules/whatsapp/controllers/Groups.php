<?php

class Groups extends AdminController {
    public function index() {
        $this->load->model('Group_model');
        $data['groups'] = $this->Group_model->get_all_groups();
        $this->load->view('groups/index', $data);
    }

    public function form($id = null) {
        $this->load->model('Group_model');
        
        if ($id) {
            // Fetch existing group data for editing
            $data['group'] = $this->Group_model->get_group($id);
            $data['action'] = 'update';
        } else {
            // Set data for a new group creation
            $data['group'] = null;
            $data['action'] = 'create';
        }

        $this->load->view('groups/form', $data);
    }

    public function save($id = null) {
        $this->load->model('Group_model');
        $data = $this->input->post();

        if ($id) {
            // Update the existing group
            $this->Group_model->update_group($id, $data);
        } else {
            // Create a new group
            $this->Group_model->create_group($data);
        }

        redirect('whatsapp/groups');
    }

    public function delete($id) {
        $this->load->model('Group_model');
        $this->Group_model->delete_group($id);
        redirect('whatsapp/groups');
    }
}
