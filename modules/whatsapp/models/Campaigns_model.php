<?php
defined('BASEPATH') || exit('No direct script access allowed');

class Campaigns_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->set_charset_utf8mb4();
        $this->load->model(['leads_model', 'clients_model', 'Contact_model']);
    }

    private function set_charset_utf8mb4()
    {
        $this->db->query("SET NAMES utf8mb4");
        $this->db->query("SET character_set_connection=utf8mb4");
        $this->db->query("SET character_set_results=utf8mb4");
        $this->db->query("SET character_set_client=utf8mb4");
    }

    public function save($post_data)
    {
        // Pre-process $post_data
        unset($post_data['image']);
        $post_data['scheduled_send_time'] = isset($post_data['scheduled_send_time']) ? to_sql_date($post_data['scheduled_send_time'], true) : null;
        $post_data['header_params'] = json_encode($post_data['header_params'] ?? []);
        $post_data['body_params'] = json_encode($post_data['body_params'] ?? []);
        $post_data['footer_params'] = json_encode($post_data['footer_params'] ?? []);

        // Determine relationship type and IDs
        list($rel_ids, $rel_type) = $this->get_relationship_data($post_data);
        unset($post_data['lead_ids'], $post_data['contact_ids'], $post_data['group_id']);

        // Update or Insert Campaign Data
        $insert = $update = false;
        $template = get_whatsapp_template($post_data['template_id']);
        if (!empty($post_data['id'])) {
            $update = $this->update_campaign($post_data, $rel_ids, $rel_type, $template);
        } else {
            $insert = $this->insert_campaign($post_data, $rel_ids, $rel_type, $template);
        }

        // Handle campaign upload
        $campaign_id = !empty($post_data['id']) ? $post_data['id'] : $this->db->insert_id();
        whatsapp_handle_campaign_upload($campaign_id);

        // Return response
        return [
            'type' => $insert || $update ? 'success' : 'danger',
            'message' => $insert ? _l('added_successfully', _l('campaign')) : ($update ? _l('updated_successfully', _l('campaign')) : _l('something_went_wrong')),
            'campaign_id' => $campaign_id,
        ];
    }

    private function get_relationship_data($post_data)
    {
        if (!empty($post_data['lead_ids'])) {
            return [$post_data['lead_ids'], 'leads'];
        } elseif (!empty($post_data['contact_ids'])) {
            return [$post_data['contact_ids'], 'contacts'];
        } elseif (!empty($post_data['group_id'])) {
            return [$this->get_group_contact_ids($post_data['group_id']), 'whatsapp_groups'];
        }
        return [[], ''];
    }

    private function get_all_rel_ids($rel_type, $group_id)
    {
        if ($rel_type === 'leads') {
            $leads = $this->leads_model->get();
            return array_column($leads, 'id');
        } elseif ($rel_type === 'contacts') {
            $contacts = $this->clients_model->get_contacts();
            return array_column($contacts, 'id');
        } elseif ($rel_type === 'whatsapp_groups') {
            return $this->get_group_contact_ids($group_id);
        }
        return [];
    }

    private function get_group_contact_ids($group_id)
    {
        $contacts = $this->Contact_model->get_contacts_by_group($group_id);
        return array_column($contacts, 'id');
    }

    private function insert_campaign($post_data, $rel_ids, $rel_type, $template)
    {
        $insert = $this->db->insert(db_prefix() . 'whatsapp_campaigns', $post_data);
        if ($insert) {
            $insert_id = $this->db->insert_id();
            $this->insert_campaign_data($insert_id, $rel_ids, $rel_type, $template);
        }
        return $insert;
    }

    private function update_campaign($post_data, $rel_ids, $rel_type, $template)
    {
        $update = $this->db->update(db_prefix() . 'whatsapp_campaigns', $post_data, ['id' => $post_data['id']]);
        if ($update) {
            $this->db->delete(db_prefix() . 'whatsapp_campaign_data', ['campaign_id' => $post_data['id']]);
            $this->insert_campaign_data($post_data['id'], $rel_ids, $rel_type, $template);
        }
        return $update;
    }

    private function insert_campaign_data($campaign_id, $rel_ids, $rel_type, $template)
    {
        foreach ($rel_ids as $rel_id) {
            $this->db->insert(db_prefix() . 'whatsapp_campaign_data', [
                'campaign_id' => $campaign_id,
                'rel_id' => $rel_id,
                'rel_type' => $rel_type,
                'header_data' => $template['header_data_text'],
                'body_data' => $template['body_data'],
                'footer_data' => $template['footer_data'],
                'status' => 1,
            ]);
        }
    }

    private function get_scheduled_data($campaign_id)
    {
        return $this->db
            ->select(db_prefix() . 'whatsapp_campaigns.*, ' . db_prefix() . 'whatsapp_templates.*, ' . db_prefix() . 'whatsapp_campaign_data.*')
            ->join(db_prefix() . 'whatsapp_campaigns', db_prefix() . 'whatsapp_campaigns.id = ' . db_prefix() . 'whatsapp_campaign_data.campaign_id', 'left')
            ->join(db_prefix() . 'whatsapp_templates', db_prefix() . 'whatsapp_campaigns.template_id = ' . db_prefix() . 'whatsapp_templates.id', 'left')
            ->where(db_prefix() . 'whatsapp_campaign_data.status', 1)
            ->where(db_prefix() . 'whatsapp_campaign_data.campaign_id', $campaign_id)
            ->get(db_prefix() . 'whatsapp_campaign_data')
            ->result_array();
    }

    public function get($id = '')
    {
        if (is_numeric($id)) {
            return $this->db->select(
                db_prefix() . 'whatsapp_campaigns.*, ' .
                db_prefix() . 'whatsapp_templates.template_name as template_name, ' .
                db_prefix() . 'whatsapp_templates.template_id as tmp_id, ' .
                db_prefix() . 'whatsapp_templates.header_params_count, ' .
                db_prefix() . 'whatsapp_templates.body_params_count, ' .
                db_prefix() . 'whatsapp_templates.footer_params_count, ' .
                'CONCAT("[", GROUP_CONCAT(' . db_prefix() . 'whatsapp_campaign_data.rel_id SEPARATOR ","), "]") as rel_ids'
            )
                ->join(db_prefix() . 'whatsapp_templates', db_prefix() . 'whatsapp_templates.id = ' . db_prefix() . 'whatsapp_campaigns.template_id')
                ->join(db_prefix() . 'whatsapp_campaign_data', db_prefix() . 'whatsapp_campaign_data.campaign_id = ' . db_prefix() . 'whatsapp_campaigns.id', 'LEFT')
                ->get_where(db_prefix() . 'whatsapp_campaigns', [db_prefix() . 'whatsapp_campaigns.id' => $id])
                ->row_array();
        }

        return $this->db->get(db_prefix() . 'whatsapp_campaigns')->result_array();
    }

    public function delete($id)
    {
        $campaign = $this->get($id);
        $delete = $this->db->delete(db_prefix() . 'whatsapp_campaigns', ['id' => $id]);

        if ($delete) {
            $this->db->delete(db_prefix() . 'whatsapp_campaign_data', ['campaign_id' => $id]);
            $path = WHATSAPP_MODULE_UPLOAD_FOLDER . '/campaign/' . $campaign['filename'];
            if (file_exists($path)) {
                unlink($path);
            }
        }

        return ['message' => $delete ? _l('deleted', _l('campaign')) : _l('something_went_wrong')];
    }

    public function pause_resume_campaign($id)
    {
        $campaign = $this->get($id);
        $update = $this->db->update(db_prefix() . 'whatsapp_campaigns', ['pause_campaign' => (1 == $campaign['pause_campaign'] ? 0 : 1)], ['id' => $id]);

        return ['message' => $update && 1 == $campaign['pause_campaign'] ? _l('campaign_resumed') : _l('campaign_paused')];
    }

    public function delete_campaign_files($id)
    {
        $campaign = $this->get($id);

        // Update database to set filename to NULL
        $update = $this->db->update(db_prefix() . 'whatsapp_campaigns', ['filename' => NULL], ['id' => $id]);
        $path = WHATSAPP_MODULE_UPLOAD_FOLDER . '/campaign/' . $campaign['filename'];

        // Delete the file
        if (file_exists($path)) {
            unlink($path);
        }

        return ['message' => $update ? _l('deleted', _l('campaign_files')) : _l('something_went_wrong')];
    }
    public function get_filtered_leads($filters = []) {
    // Select necessary fields
    $this->db->select('id, name, phonenumber');
    $this->db->from('tblleads');

    // Apply filters dynamically
    if (!empty($filters)) {
        if (!empty($filters['status'])) {
            $this->db->where('status', $filters['status']);
        }

        if (!empty($filters['assigned'])) {
            $this->db->where('assigned', $filters['assigned']);
        }

        if (!empty($filters['source'])) {
            $this->db->where('source', $filters['source']);
        }
    }

    // Order by name for consistent display
    $this->db->order_by('name', 'ASC');

    // Execute query and return results
    $query = $this->db->get();

    // Check if there are results, return an empty array if not
    if ($query->num_rows() > 0) {
        return $query->result_array();
    }

    return [];
}

}
