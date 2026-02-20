<?php

defined('BASEPATH') || exit('No direct script access allowed');

/**
 * Campaigns Controller
 * 
 * Handles the functionality related to campaign management.
 */
class Campaigns extends AdminController
{
    /**
     * Constructor
     * 
     * Loads necessary models.
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->model(['campaigns_model', 'leads_model', 'clients_model','Group_model']);
    }

    /**
     * Index method
     * 
     * Loads the main view for campaigns management.
     */
    public function index()
    {
        if (!staff_can('view', 'whatsapp_campaign')) {
            access_denied();
        }

        $data['title'] = _l('campaigns');
        $this->load->view('campaigns/manage', $data);
    }

    /**
     * Campaign method
     * 
     * Loads the view for creating or editing a campaign.
     * 
     * @param string $id The ID of the campaign (optional).
     */
    public function campaign($id = '')
    {
        $permission = empty($id) ? 'create' : 'edit';
        if (!staff_can($permission, 'whatsapp_campaign')) {
            access_denied();
        }

        $data['title']      = _l('campaigns');

        $data['leads']    = $this->leads_model->get();
        $data['contacts'] = $this->clients_model->get_contacts();
        $data['groups'] = $this->Group_model->get_all_groups();

        $data['templates']  = get_whatsapp_template();

        if (!empty($id)) {
            $data['campaign'] = $this->campaigns_model->get($id);

            $relationMapping = [
                'leads'    => 'lead_ids',
                'contacts' => 'contact_ids',
            ];


            if (isset($relationMapping[$data['campaign']['rel_type']])) {
                $data['campaign'][$relationMapping[$data['campaign']['rel_type']]] = !empty($data['campaign']['rel_ids']) ? json_decode($data['campaign']['rel_ids']) : [];
            }
        }
        $this->load->view('campaigns/campaign', $data);
    }
    public function get_filtered_leads() {
        // Load required model
    
        // Capture filter inputs from POST request
        $status_id = $this->input->post('status_id');
        $assigned_id = $this->input->post('assigned_id');
        $source_id = $this->input->post('source_id');
    
        // Prepare filters array
        $filters = [
            'status' => $status_id,
            'assigned' => $assigned_id,
            'source' => $source_id,
        ];
    
        // Fetch filtered leads from the model
        $leads = $this->campaigns_model->get_filtered_leads($filters);
    
        // Return the results as JSON response
        echo json_encode($leads);
    }

    /**
     * Save method
     * 
     * Saves campaign data from POST request.
     */
    public function save()
    {
        $permission = empty($this->input->post('id')) ? 'create' : 'edit';
        if (!staff_can($permission, 'whatsapp_campaign')) {
            access_denied();
        }

        $res = $this->campaigns_model->save($this->input->post());
        set_alert($res['type'], $res['message']);
        redirect(admin_url('whatsapp/campaigns'));
    }

    /**
     * Get Table Data method
     * 
     * Loads the data for the specified table.
     * 
     * @param string $table The table name.
     * @param string $id The ID associated with the table (optional).
     * @param string $rel_type The relationship type (optional).
     * 
     * @return bool Returns false if the request is not an AJAX request.
     */
    public function get_table_data($table, $id = '', $rel_type = '')
    {
        if (!$this->input->is_ajax_request()) {
            return false;
        }

        $this->app->get_table_data(module_views_path(WHATSAPP_MODULE, 'tables/'.$table), compact('id', 'rel_type'));
    }

    /**
     * Delete method
     * 
     * Deletes a campaign based on its ID.
     * 
     * @param string $id The ID of the campaign.
     */
    public function delete($id)
    {
        if (!staff_can('delete', 'whatsapp_campaign')) {
            access_denied();
        }

        $res = $this->campaigns_model->delete($id);
        set_alert('danger', $res['message']);
        redirect(admin_url('whatsapp/campaigns'));
    }

    /**
     * Get Template Map method
     * 
     * Loads the template map for a campaign.
     */
public function get_template_map()
{
    if ($this->input->is_ajax_request()) {
        // Load the helper if not already loaded
        $this->load->helper('whatsapp');

        // Call the helper function
        $templateId = $this->input->post('template_id');
        $type = $this->input->post('type') ?? 'campaign';  // Default to 'campaign'

        $result = get_template_maper($templateId, $type);

        echo json_encode($result);
    }
}

    /**
     * View method
     * 
     * Loads the view for a specific campaign.
     * 
     * @param string $id The ID of the campaign.
     */
    public function view($id)
    {
        if (!staff_can('show', 'whatsapp_campaign')) {
            access_denied();
        }

        $data['title']     = _l('view_campaign');
        $data['campaign']  = $this->campaigns_model->get($id);
        $total_leads       = total_rows(db_prefix().'leads');
        $total_contacts    = total_rows(db_prefix().'contacts');
        $campaign_data     = count(json_decode($data['campaign']['rel_ids']));
        $relation_type_map = [
            'leads'    => $total_leads,
            'contacts' => $total_contacts,
        ];
        $data['total_percent'] = number_format(($campaign_data / $relation_type_map[$data['campaign']['rel_type']]) * 100, 2);

        $data['delivered_to_count']   = total_rows(db_prefix().'whatsapp_campaign_data', ['status' => 2, 'campaign_id' => $id]);
        $data['read_by_count']        = total_rows(db_prefix().'whatsapp_campaign_data', ['message_status' => 'read', 'campaign_id' => $id]);
        $data['delivered_to_percent'] = $data['read_by_percent'] = 0;
        if (!empty($data['delivered_to_count'])) {
            $data['delivered_to_percent'] = number_format(($data['delivered_to_count'] / $campaign_data) * 100, 2);
            $data['read_by_percent']      = number_format(($data['read_by_count'] / $data['delivered_to_count']) * 100, 2);
        }
        $this->load->view('campaigns/view', $data);
    }

    /**
     * Pause or Resume Campaign method
     * 
     * Pauses or resumes a campaign based on its ID.
     * 
     * @param string $id The ID of the campaign.
     */
    public function pause_resume_campaign($id)
    {
        $res = $this->campaigns_model->pause_resume_campaign($id);
        set_alert('success', $res['message']);
        redirect(admin_url('whatsapp/campaigns/view/'.$id));
    }

    /**
     * Delete campaign files
     * @param  string $id The ID of the campaign
     */
    public function delete_campaign_files($id)
    {
        $res = $this->campaigns_model->delete_campaign_files($id);
        set_alert('danger', $res['message']);
        redirect($res['url']);
    }
}
