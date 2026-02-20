<?php

defined('BASEPATH') || exit('No direct script access allowed');

/**
 * Bots Controller
 * 
 * Handles the functionality related to bots management.
 */
class Bots extends AdminController
{
    /**
     * Constructor
     * 
     * Loads necessary models.
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->model(['bots_model', 'campaigns_model']);
    }

    /**
     * Index method
     * 
     * Loads the main management page for bots.
     */
    public function index()
    {
        // Check if the staff has 'view' permission for bots
        if (!staff_can('view', 'whatsapp_bots')) {
            access_denied('whatsapp_bots');
        }

        $data['title'] = _l('bots');
        $this->load->view('bots/manage', $data);
    }

    /**
     * Table method
     *
     * Loads the data for the specified table.
     *
     * @param string $table The table name.
     */
    public function table($table)
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        // Check if the staff has 'view' permission for bots table
        if (!staff_can('view', 'whatsapp_bots')) {
            access_denied('whatsapp_bots');
        }

        $this->app->get_table_data(module_views_path(WHATSAPP_MODULE, 'tables/' . $table));
    }


        public function botflow($id = '')
    {
        $permission = (empty($id)) ? 'create' : 'edit';
        if (!staff_can($permission, 'whatsapp_bots_flow')) {
            access_denied();
        }

        $data['title'] = _l('whatsapp_bots_flow');

        if (!empty($id)) {
            $data['flow'] = $this->bots_model->get($id);
        }

        $this->load->view('bots/botflow', $data);
    }

    /**
     * Form method
     * 
     * Loads the view for creating or editing a bot.
     * 
     * @param string $id The ID of the bot (optional).
     */
    public function form($id = '')
    {
        $permission = empty($id) ? 'create' : 'edit';

        // Check if the staff has appropriate permissions
        if (!staff_can($permission, 'whatsapp_bots')) {
            access_denied('whatsapp_bots');
        }

        $data['bot_types'] = get_whatsapp_bot_types();
        $data['templates'] = get_whatsapp_template();

        if (!empty($id)) {
            $data['bot'] = $this->bots_model->get($id);
            $data['bot']['header_params'] = !empty($data['bot']['header_params']) ? json_decode($data['bot']['header_params'], true) : [];
            $data['bot']['body_params'] = !empty($data['bot']['body_params']) ? json_decode($data['bot']['body_params'], true) : [];
            $data['bot']['footer_params'] = !empty($data['bot']['footer_params']) ? json_decode($data['bot']['footer_params'], true) : [];
        } else {
            $data['bot'] = null;
        }

        $data['title'] = isset($data['bot']) ? _l('edit_bot') : _l('create_bot');
        $this->load->view('bots/form_bot', $data);
    }

    /**
     * Save Bots method
     * 
     * Saves bot data from POST request.
     */
public function saveBot()
{
    if (!$this->input->post()) {
        // Redirect if no POST data is received
        redirect(admin_url('whatsapp/bots'));
        return;
    }

    $postData = $this->input->post();
    $isEdit = !empty($postData['id']); // Check if it's an edit operation
    $permission = $isEdit ? 'edit' : 'create';

    // Check if the staff has the appropriate permission
    if (!staff_can($permission, 'whatsapp_bots')) {
        access_denied('whatsapp_bots');
        return;
    }

    // Encode menu items if they exist
    if (isset($postData['menu_items']) && is_array($postData['menu_items'])) {
        $postData['menu_items'] = json_encode($postData['menu_items']);
    }

    // Encode flow data if they exist
    if ($postData['bot_type']==4 && isset($postData['flow_data']) && is_array($postData['flow_data'])) {
        $postData['flow_data'] = json_encode($postData['flow_data']);
    }else{
        $postData['flow_data'] = null;
    }

    // Save bot data through the model
    $res = $this->bots_model->saveBots($postData);

    // Handle success or failure response
    if ($res['status']) {
        whatsapp_handle_bot_upload($res['id']);
        set_alert($res['status'], $res['message']);
    } else {
        set_alert('danger', $res['message']);
    }

    // Redirect after handling the request
    redirect(admin_url('whatsapp/bots'));
}


    /**
     * Change Active Status method
     * 
     * Changes the active status of a bot.
     * 
     * @param string $id The ID of the bot.
     * @param string $status The new status of the bot.
     */
    public function change_active_status($id, $status)
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        // Check if the staff has 'edit' permission
        if (!staff_can('edit', 'whatsapp_bots')) {
            access_denied('whatsapp_bots');
        }

        $response = $this->bots_model->change_active_status($id, $status);
        echo json_encode($response);
    }

    /**
     * Delete Bot Files method
     * 
     * Deletes the files associated with a bot.
     * 
     * @param string $id The ID of the bot.
     */
    public function delete_bot_files($id)
    {
        // Check if the staff has 'delete' permission
        if (!staff_can('delete', 'whatsapp_bots')) {
            access_denied('whatsapp_bots');
        }

        $res = $this->bots_model->delete_bot_files($id);

        if ($res['status']) {
            set_alert('success', $res['message']);
        } else {
            set_alert('danger', $res['message']);
        }

        redirect(admin_url('whatsapp/bots/form/' . $id));
    }

    /**
     * Delete Bot method
     *
     * Deletes a bot.
     *
     * @param string $id The ID of the bot.
     */
    public function delete($id)
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        // Check if the staff has 'delete' permission
        if (!staff_can('delete', 'whatsapp_bots')) {
            access_denied('whatsapp_bots');
        }

        $response = $this->bots_model->deleteBot($id);
        echo json_encode($response);
    }

    /**
     * Duplicate Bot method
     *
     * Duplicates an existing bot.
     *
     * @param string $id The ID of the bot.
     */
    public function duplicate($id)
    {
        // Check if the staff has 'duplicate' permission
        if (!staff_can('duplicate', 'whatsapp_bots')) {
            access_denied('whatsapp_bots');
        }

        $botData = $this->bots_model->get($id);

        if (!$botData) {
            set_alert('danger', 'Bot not found.');
            redirect(admin_url('whatsapp/bots'));
        }

        unset($botData['id']);
        $botData['name'] .= ' (Copy)';
        $botData['is_bot_active'] = 0;

        $newBotId = $this->bots_model->saveBots($botData);

        if ($newBotId) {
            set_alert('success', 'Bot duplicated successfully.');
        } else {
            set_alert('danger', 'Failed to duplicate bot.');
        }

        redirect(admin_url('whatsapp/bots'));
    }
}
