<?php

defined('BASEPATH') || exit('No direct script access allowed');

class Bots_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->set_charset_utf8mb4();
    }

    /**
     * Set character set for the connection and results to utf8mb4
     */
    private function set_charset_utf8mb4()
    {
        $this->db->query("SET NAMES 'utf8mb4'");
        $this->db->query("SET character_set_connection = 'utf8mb4'");
        $this->db->query("SET character_set_results = 'utf8mb4'");
        $this->db->query("SET character_set_client = 'utf8mb4'");
    }

    /**
     * Save or update bot data for any type of bot
     *
     * @param array $data
     * @return array
     */
    public function saveBots($data)
    {
        
        $isInsert = empty($data['id']);

        // Handle file uploads for header_params
        $data['header_params'] = whatsapp_handle_header_upload($data['header_params'] ?? [], $data['id'] ?? null);
    
        // JSON encode the parameters
        $data['header_params'] = json_encode($data['header_params'] ?? []);
        $data['body_params'] = json_encode($data['body_params'] ?? []);
        $data['footer_params'] = json_encode($data['footer_params'] ?? []);
    
        // Check if 'menu_items' or 'menu_structure' is an array and encode them
        if (isset($data['menu_items']) && is_array($data['menu_items'])) {
            $data['menu_items'] = json_encode($data['menu_items']);
        } elseif (isset($data['menu_items']) && is_array($data['menu_structure'])) {
            $data['menu_items'] = json_encode($data['menu_structure']);
        }
    
        if ($isInsert) {
            $data['addedfrom'] = get_staff_user_id();
            $this->db->insert(db_prefix() . 'whatsapp_bot', $data);
            $bot_id = $this->db->insert_id();
        } else {
            $this->db->update(db_prefix() . 'whatsapp_bot', $data, ['id' => $data['id']]);
            $bot_id = $data['id'];
        }
        if(isset($data['filename']))whatsapp_handle_bot_upload($bot_id);
    
        return [
            'status' => ($isInsert || $this->db->affected_rows()) ? 'success' : 'danger',
            'message' => $isInsert ? _l('bot_create_successfully') : _l('bot_update_successfully'),
            'id' => $bot_id,
        ];
    }



    /**
     * Retrieve a bot by its ID or get all bots, regardless of type
     *
     * @param string $id
     * @return array
     */
    public function get($id = '')
    {
        if (!empty($id)) {
            return $this->db->get_where(db_prefix() . 'whatsapp_bot', ['id' => $id])->row_array();
        }

        return $this->db->get(db_prefix() . 'whatsapp_bot')->result_array();
    }

    /**
     * Delete a bot and its associated files, regardless of bot type
     *
     * @param int $id
     * @return array
     */
    public function deleteMessageBot($id)
    {
        $bot = $this->db->get_where(db_prefix() . 'whatsapp_bot', ['id' => $id])->row_array();
        $status = 'danger'; // Default status
        $message = _l('something_went_wrong');
    
        $this->db->delete(db_prefix() . 'whatsapp_bot', ['id' => $id]);
    
        if ($this->db->affected_rows() > 0) {
            $path = WHATSAPP_MODULE_UPLOAD_FOLDER . '/bot_files/' . $bot['filename'];
            if (file_exists($path)) {
                unlink($path);
            }
            $status = 'success'; // Update status on successful deletion
            $message = _l('bot_deleted_successfully');
        }
    
        return [
            'status' => $status,
            'message' => $message,
        ];
    }



    /**
     * Get bots by relation type and message, regardless of bot type
     *
     * @param string $relType
     * @param string $message
     * @return array
     */
public function getBotsByTrigger($interaction_id, $message)
{
    // Start building the query
    $this->db->select('wb.id as bot_id, wb.*, wt.template_name, wi.*');
    $this->db->from(db_prefix() . 'whatsapp_bot as wb');

    // Join with whatsapp_templates table
    $this->db->join(db_prefix() . 'whatsapp_templates as wt', 'wb.template_id = wt.id', 'left');

    // Join with whatsapp_interaction table using interaction_id
    $this->db->join(db_prefix() . 'whatsapp_interactions as wi', 'wi.id = ' . $this->db->escape($interaction_id), 'left');

    // Ensure bot is active
    $this->db->where('wb.is_bot_active', 1);

    // Filter by the specific interaction (optional if needed)
    $this->db->where('wi.id', $interaction_id);

    // Use LOCATE to check if the trigger exists in the message
    $this->db->where("LOCATE(" . $this->db->escape($message) . ", wb.trigger) > 0");

    // Execute the query and return the result
    $result = $this->db->get()->result_array();

    // Log the query for debugging purposes
    log_message('error', 'SQL Query: ' . $this->db->last_query());

    // Return the result
    return $result;
}





public function get_asset_url($url)
{
    // Check if the URL is a file name (does not contain a '/')
    if ($url && strpos($url, '/') === false) {
        // Return the complete file URL by appending the base path
        return WHATSAPP_MODULE_UPLOAD_URL . 'bot_files/' . $url;
    }
    
    // Return the original URL or null if empty
    return $url ?? null;
}


    /**
     * Change the active status of a bot
     *
     * @param int $id
     * @param int $status
     * @return bool
     */
    public function change_active_status($id, $status)
    {
        return $this->db->update(db_prefix() . 'whatsapp_bot', ['is_bot_active' => $status], ['id' => $id]);
    }

    /**
     * Update the sending count for a specific bot
     *
     * @param string $table
     * @param int $count
     * @param array $where
     * @return bool
     */
public function update_sending_count($bot)
{
    if (isset($bot['sending_count']) && isset($bot['id'])) {
        // Increment the sending count
        $newCount = (int)$bot['sending_count'] + 1;

        // Update the sending count in the database
        $this->db->update(db_prefix() . 'whatsapp_bot', ['sending_count' => $newCount], ['id' => (int)$bot['id']]);
    } else {
        log_message('error', 'Invalid bot data: ' . json_encode($bot));
    }
}


    /**
     * Delete bot files
     *
     * @param int $id
     * @return array
     */
    public function delete_bot_files($id)
    {
        $bot = $this->get($id);
        $update = $this->db->update(db_prefix() . 'whatsapp_bot', ['filename' => null], ['id' => $id]);
        
        $path = WHATSAPP_MODULE_UPLOAD_FOLDER . '/bot_files/' . $bot['filename'];
        if ($update && file_exists($path)) {
            unlink($path);
        }

        return [
            'message' => $update ? _l('image_deleted_successfully') : _l('something_went_wrong'),
        ];
    }
public function deleteBot($id)
{
    // Fetch the bot details to get the filename
    $bot = $this->db->get_where(db_prefix() . 'whatsapp_bot', ['id' => $id])->row_array();
    $message = _l('something_went_wrong');

    // Attempt to delete the bot from the database
    $this->db->delete(db_prefix() . 'whatsapp_bot', ['id' => $id]);

    // Check if the deletion was successful
    if ($this->db->affected_rows() > 0) {
        // Build the path to the file to be deleted
        $path = WHATSAPP_MODULE_UPLOAD_FOLDER . '/bot_files/' . $bot['filename'];
        
        // Check if the file exists and delete it
        if (file_exists($path)) {
            unlink($path);
        }
        
        $message = _l('bot_deleted_successfully');
        return [
            'status' => 'success',
            'message' => $message,
        ];
    }

    return [
        'status' => 'danger',
        'message' => $message,
    ];
}




}
