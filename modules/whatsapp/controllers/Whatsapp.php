<?php
defined('BASEPATH') || exit('No direct script access allowed');

use WpOrg\Requests\Requests as WhatsappRequests;
use Pusher\Pusher;

/**
 * Class Whatsapp
 *
 * Controller for WhatsApp integration functionalities.
 */
class Whatsapp extends AdminController
{
    /**
     * @var object
     */
    private $whatsappLibrary;

    /**
     * Constructor for Whatsapp controller.
     * Loads necessary models and libraries.
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->model([
            'whatsapp_interaction_model',
            'QuickReply_model',
            'leads_model',
            'staff_model'
        ]);
        $this->load->library('whatsappLibrary');
        $this->whatsappLibrary = new whatsappLibrary();
    }

    /* =========================================================================
       VIEW HANDLERS
    ========================================================================= */

    /**
     * Default entry point.
     * Redirects to the chat view.
     */
    public function index()
    {
        if (!staff_can('view', 'whatsapp_chat')) {
            access_denied();
        }

        $data['title'] = _l('chat');
        $this->load->view('admin/interaction', $data);
    }
public function connection()
{
    if (!is_admin()) {
        access_denied('WhatsApp Connection Page');
    }

    $data['title'] = 'WA Cloud Connection';
    $this->load->view('admin/connection', $data);
}

    /**
     * Loads the second version of the chat interface.
     */
    public function interaction_v2()
    {
        if (!staff_can('view', 'whatsapp_chat')) {
            access_denied();
        }

        $data['title'] = _l('chat');
        $this->load->view('admin/interaction_v2', $data);
    }

    /**
     * Displays the chat interactions view.
     */
    public function interaction()
    {
        if (!staff_can('view', 'whatsapp_chat')) {
            access_denied();
        }

        $data['title']         = _l('chat');
        $data['numbers']       = $this->whatsapp_interaction_model->get_numbers();
        $data['quick_replies'] = $this->QuickReply_model->get_all_replies();
        $data['staffs']        = $this->staff_model->get('', ['active' => 1]);
        $data['statuses']      = $this->leads_model->get_status();

        $this->load->view('admin/interaction', $data);
    }

    /**
     * Displays the numbers view.
     */
    public function numbers()
    {
        if (!staff_can('view', 'whatsapp_chat')) {
            access_denied();
        }

        $data['title'] = _l('numbers');
        $apiNumbers = $this->whatsappLibrary->getPhoneNumbers();

        log_message('error', 'Phone Numbers Data: ' . json_encode($apiNumbers));

        $profileImageDir = FCPATH . 'uploads/whatsapp/profiles/';
        if (!is_dir($profileImageDir)) {
            mkdir($profileImageDir, 0755, true);
        }

        if ($apiNumbers['status'] && !empty($apiNumbers['data'])) {
            foreach ($apiNumbers['data'] as $number) {
                log_message('error', 'Phone Number Data: ' . json_encode($number));

                $phoneNumber = preg_replace('/[^\d]/', '', $number->display_phone_number);
                $updateData = [
                    'phone_number'             => $phoneNumber,
                    'display_phone_number'     => $phoneNumber,
                    'verified_name'            => $number->verified_name ?? '',
                    'code_verification_status' => $number->code_verification_status ?? '',
                    'quality_rating'           => $number->quality_rating ?? '',
                    'platform_type'            => $number->platform_type ?? '',
                    'throughput_level'         => $number->throughput->level ?? '',
                    'external_id'              => $number->id ?? '',
                    'phone_number_id'          => $number->id,
                ];

                $this->db->where('phone_number_id', $number->id);
                $existingRecord = $this->db->get(db_prefix() . 'whatsapp_numbers')->row();

                if ($existingRecord) {
                    $this->db->where('phone_number_id', $number->id);
                    $this->db->update(db_prefix() . 'whatsapp_numbers', $updateData);
                } else {
                    $this->db->insert(db_prefix() . 'whatsapp_numbers', $updateData);
                }
            }
        }

        $data['numbers'] = $this->db->get(db_prefix() . 'whatsapp_numbers')->result_array();

        $this->db->where('is_default', 1);
        $defaultNumber = $this->db->get(db_prefix() . 'whatsapp_numbers')->row();

        if (!$defaultNumber) {
            $firstNumber = $this->db->order_by('phone_number_id', 'ASC')->limit(1)->get(db_prefix() . 'whatsapp_numbers')->row();
            if ($firstNumber) {
                $this->db->where('phone_number_id', $firstNumber->phone_number_id);
                $this->db->update(db_prefix() . 'whatsapp_numbers', ['is_default' => 1]);
                update_option('whatsapp_default_phone_number_id', $firstNumber->phone_number_id);
                update_option('whatsapp_default_phone_number', $firstNumber->phone_number);
            }
        }

        $this->load->view('admin/numbers', $data);
    }

    /**
     * Displays the WhatsApp activity log view.
     */
    public function activity_log()
    {
        if (!staff_can('view', 'whatsapp_log_activity')) {
            access_denied('activity_log');
        }
        $data['title'] = _l('activity_log');
        $this->load->view('activity_log/whatsapp_activity_log', $data);
    }

    /**
     * Displays detailed log information for a specific log entry.
     *
     * @param string $id Log ID.
     */
    public function view_log_details($id = '')
    {
        $data['title']    = _l('activity_log');
        $data['log_data'] = $this->whatsapp_interaction_model->getWhatsappLogDetails($id);
        $this->load->view('activity_log/view_log_details', $data);
    }

    /**
     * Displays the contacts view.
     */
    public function contacts()
    {
        $data['title']    = _l('contacts');
        $data['contacts'] = $this->whatsapp_interaction_model->get_contacts();
        $this->load->view('contacts', $data);
    }

    /**
     * Displays the documentation view.
     */
    public function documentation()
    {
        $data['title'] = _l('documentation');
        $this->load->view('documentation', $data);
    }
/**
 * Sets the default WhatsApp phone number.
 * Ensures only one default exists by resetting all and marking the selected one.
 */
public function set_default_phone_number()
{
    // Only allow GET requests with ID param
    $phone_number_id = $this->input->get('id', true);

    if (!$phone_number_id) {
        show_error(_l('Invalid phone number ID'), 400);
    }

    // Begin DB transaction
    $this->db->trans_start();

    try {
        // Reset all to non-default
        $this->db->update(db_prefix() . 'whatsapp_numbers', ['is_default' => 0]);

        // Check if the requested phone number exists
        $existing = $this->db->where('id', $phone_number_id)->get(db_prefix() . 'whatsapp_numbers')->row();

        if ($existing) {
            // Set requested number as default
            $this->db->where('id', $phone_number_id)->update(db_prefix() . 'whatsapp_numbers', ['is_default' => 1]);
        } else {
            // Set the first available number as default
            $first = $this->db->order_by('id', 'ASC')->limit(1)->get(db_prefix() . 'whatsapp_numbers')->row();

            if ($first) {
                $this->db->where('id', $first->id)->update(db_prefix() . 'whatsapp_numbers', ['is_default' => 1]);
                set_alert('warning', _l('Selected number not found. First number marked as default.'));
            } else {
                // No numbers exist
                $this->db->trans_rollback();
                set_alert('danger', _l('No phone numbers available to set as default'));
                redirect(admin_url('whatsapp/numbers')); // fallback URL
                return;
            }
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            set_alert('danger', _l('Failed to update default phone number.'));
        } else {
            set_alert('success', _l('Default phone number updated successfully.'));
        }

    } catch (Exception $e) {
        $this->db->trans_rollback();
        log_message('error', 'Error updating default phone number: ' . $e->getMessage());
        set_alert('danger', _l('An error occurred while updating the default phone number.'));
    }

    redirect($_SERVER['HTTP_REFERER'] ?? admin_url('whatsapp/numbers'));
}

      /*** Updates the profile data for a given phone number.
     * Uploads a new profile picture if provided and updates the profile data.
     */
    public function update_profile()
    {
        if ($this->input->post()) {
            $profileData = $this->input->post();
            $accessToken = get_option('whatsapp_access_token');
            $phoneNumberId = $profileData['phone_number_id'];

            

            $response = $this->whatsappLibrary->updateProfile($profileData, $phoneNumberId, $accessToken);
            
            redirect(admin_url('whatsapp/numbers'));
        }
    }

    /* =========================================================================
       MESSAGE AND INTERACTION HANDLERS
    ========================================================================= */

public function initiate_message()
{
    // Load the WhatsApp library if not already loaded.
    $this->load->library('whatsappLibrary');
    
    // Retrieve and sanitize input data.
    $phone_number   = $this->input->post('phone_number', true);      // Recipient phone number.
    $from_number_id = $this->input->post('from_number_id', true);       // Sender phone number ID.
    $template_id    = $this->input->post('template_id', true);          // Template ID.
    
    // Validate the required inputs.
    if (empty($phone_number) || empty($template_id) || empty($from_number_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'Sender number, recipient phone number and template ID are required.'
        ]);
        return;
    }
    
    // Save the interaction record.
    $interactionData = [
        'receiver_id' => $phone_number,
        'wa_no_id'    => $from_number_id,
        'wa_no'       => get_whatsapp_number_data($from_number_id)['phone_number'],
    ];
    $interaction_id = $this->whatsapp_interaction_model->insert_interaction($interactionData);
    
    // Check if interaction record was successfully created.
    if (!$interaction_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create interaction record.'
        ]);
        return;
    }
    
    // Retrieve the full interaction details after insertion.
    $existing_interaction = $this->whatsapp_interaction_model->get_interaction($interaction_id);
    if (empty($existing_interaction)) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to retrieve interaction details.'
        ]);
        return;
    }
    
    // Retrieve the template data by ID and check if it's approved.
    $template_data = get_whatsapp_template($template_id);
    if (empty($template_data)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid template ID or template not approved.'
        ]);
        return;
    }
    
    // Merge template data into the interaction record.
    $existing_interaction = array_merge($existing_interaction, $template_data);
    
    // Prepare message data using the WhatsApp library.
    $message_data = $this->whatsapplibrary->prepare_template_message_data($existing_interaction['wa_no_id'], $existing_interaction);
    log_message('error', 'prepare_template_message_data received: ' . print_r($message_data, true));
    
    if (empty($message_data)) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to prepare message data.'
        ]);
        return;
    }
    
    // Initialize a log batch array to store logging data.
    $logBatch = [];
    
    // Send the WhatsApp message.
    $response = $this->whatsapplibrary->send_message(
        $existing_interaction['wa_no_id'],
        $phone_number,
        $message_data
    );
    
    // Ensure the response has the 'response_data' key.
    if (!isset($response['response_data'])) {
        $response['response_data'] = [];
    }
    
    // Build a status message based on any errors from the response.
    $status_message = '';
    if (isset($response['response_data']['error'])) {
        $main_message = $response['response_data']['error']['message'] ?? 'Failed to send message';
        $error_details = isset($response['response_data']['error']['error_data']['details'])
            ? 'Details: ' . $response['response_data']['error']['error_data']['details']
            : '';
        $status_message = $main_message . ($error_details ? ' - ' . $error_details : '');
    }
    
    // Save the message in the chat history.
    $this->whatsapp_interaction_model->prepare_chat_message($response, $existing_interaction, "message");
    
    // Prepare the result based on the response.
    if (!empty($response['success'])) {
        $result = [
            'success' => true,
            'message' => 'Message sent successfully.'
        ];
    } else {
        $result = [
            'success' => false,
            'message' => 'Failed to send message: ' . ($response['message'] ?? 'Unknown error')
        ];
    }
    
    // Optionally log the activity if log data is available.
    if (!empty($logBatch)) {
        $this->whatsapp_interaction_model->addWhatsbotLog($logBatch);
    }
    
    // Return the result as a JSON response.
    echo json_encode($result);
}




    /**
     * Generates an AI reply for a given interaction and message.
     */
    public function get_aireply()
    {
        $interaction_id = $this->input->get('interaction_id');
        $message        = $this->input->get('message');

        if (!$interaction_id || !is_numeric($interaction_id)) {
            echo json_encode(['error' => 'Invalid interaction ID']);
            return;
        }

        if (empty($message)) {
            echo json_encode(['error' => 'Message content is empty']);
            return;
        }

        $ai_reply = getAIReply($interaction_id, null, $message);

        if ($ai_reply === null) {
            echo json_encode(['error' => 'Failed to generate AI response']);
            return;
        }

        echo json_encode(['reply' => $ai_reply]);
    }

    /**
     * Returns a mapped template using the provided template ID and type.
     */
    public function get_template_map()
    {
        if ($this->input->is_ajax_request()) {
            $this->load->helper('whatsapp');
    
            $templateId = $this->input->post('template_id');
            $type       = $this->input->post('type');
            $type_id    = $this->input->post('type_id') ?? null;
    
            $result = get_template_maper($templateId, $type, $type_id);
    
            echo json_encode($result);
        }
    }

    /**
     * Fetches and returns filtered interactions as JSON.
     */
    public function interactions()
    {
        $filters = [
            'wa_no_id'           => $this->input->get('wa_no_id'),
            'interaction_type'   => $this->input->get('interaction_type'),
            'status_id'          => $this->input->get('status_id'),
            'assigned_staff_id'  => $this->input->get('assigned_staff_id'),
            'status'             => $this->input->get('status'),
            'status_id'             => $this->input->get('status_id'),
            'searchtext'         => $this->input->get('searchtext')
        ];

        $data['interactions'] = $this->whatsapp_interaction_model->get_interactions($filters);
        $data['total_count']  = $this->whatsapp_interaction_model->get_interactions_count($filters);

        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Retrieves and returns messages for a specific interaction.
     */
    public function get_interaction_messages()
    {
        $interaction_id = $this->input->get('interaction_id');
        $limit          = isset($_GET['limit']) ? (int)$this->input->get('limit') : 20;
        $offset         = isset($_GET['offset']) ? (int)$this->input->get('offset') : 0;

        $messages = $this->whatsapp_interaction_model->get_interaction_messages($interaction_id, $limit, $offset);

        header('Content-Type: application/json');
        echo json_encode(['messages' => $messages]);
        exit;
    }

    /* =========================================================================
       CHAT AND ACTIVITY HANDLERS
    ========================================================================= */

    /**
     * Marks messages as read for a given interaction.
     */
    public function chat_mark_as_read()
    {
        $id = $this->input->post('interaction_id');
        $this->whatsapp_interaction_model->chat_received_messages_mark_as_read($id);
    }

    /**
     * Clears the activity log.
     */
    public function clear_log()
    {
        if (staff_can('clear_log', 'whatsapp_log_activity')) {
            $this->db->truncate(db_prefix() . 'whatsapp_activity_log');
            set_alert('danger', _l('log_cleared_successfully'));
        }
        redirect(admin_url('whatsapp/activity_log'));
    }

    /**
     * Deletes a specific log entry.
     *
     * @param int $id Log ID.
     */
    public function delete_log($id)
    {
        if (staff_can('clear_log', 'whatsapp_log_activity')) {
            $delete = $this->whatsapp_interaction_model->delete_log($id);
            set_alert('danger', $delete ? _l('deleted', _l('log')) : _l('something_went_wrong'));
        }
        redirect(admin_url('whatsapp/activity_log'));
    }

    /**
     * Deletes a chat interaction.
     */
    public function delete_chat()
    {
        $this->load->model('whatsapp_interaction_model');
        $chat_id = $this->input->post('chat_id');

        if ($chat_id && is_numeric($chat_id)) {
            $result = $this->whatsapp_interaction_model->delete_interaction((int)$chat_id);

            if ($result['success']) {
                $response = ['success' => true, 'message' => 'Chat deleted successfully.'];
                $this->output->set_status_header(200);
            } else {
                $response = ['success' => false, 'message' => $result['message'] ?? 'Failed to delete chat. Please try again.'];
                $this->output->set_status_header(500);
            }
        } else {
            $response = ['success' => false, 'message' => 'Chat ID is required and must be numeric.'];
            $this->output->set_status_header(400);
        }
    
        echo json_encode($response);
    }

    /**
     * Toggles the pinned state of a chat interaction.
     */
    public function pinorunpin()
    {
        $this->load->model('whatsapp_interaction_model');
    
        $chatId = $this->input->get('chat_id');
        if (!isset($chatId)) {
            echo json_encode(['success' => false, 'error' => 'Invalid input data.']);
            return;
        }
    
        $chat = $this->db->where('id', $chatId)->get(db_prefix() . 'whatsapp_interactions')->row();
        if (!$chat) {
            echo json_encode(['success' => false, 'error' => 'Chat ID does not exist.']);
            return;
        }
    
        $newPinnedState = ($chat->is_pinned === '1') ? '0' : '1';
        $this->db->where('id', $chatId);
        $this->db->update(db_prefix() . 'whatsapp_interactions', ['is_pinned' => $newPinnedState]);
    
        if ($this->db->affected_rows() > 0) {
            echo json_encode(['success' => true]);
        } else {
            $error = $this->db->error();
            echo json_encode([
                'success' => false,
                'error' => 'Unable to update pinned state or no changes made. ' . ($error['message'] ?? ''),
                'chatId' => $chatId,
                'isPinned' => $newPinnedState
            ]);
        }
    }

    /**
     * Saves a new tag for a chat interaction.
     */
    public function save_tag()
    {
        if (!$this->input->is_ajax_request() || !$this->input->post()) {
            show_404();
        }
    
        $interaction_id = $this->input->post('interaction_id');
        $new_tag = trim($this->input->post('tag'));
    
        if (empty($interaction_id) || empty($new_tag)) {
            echo json_encode(['success' => false, 'message' => 'Interaction ID and tag are required.']);
            return;
        }
    
        $interaction = $this->db->select('tags')
                                ->where('id', $interaction_id)
                                ->get(db_prefix() . 'whatsapp_interactions')
                                ->row();
    
        if (!$interaction) {
            echo json_encode(['success' => false, 'message' => 'Interaction not found.']);
            return;
        }
    
        $tags = array_map('strtolower', array_filter(array_map('trim', explode(',', $interaction->tags))));
        $new_tag_normalized = strtolower($new_tag);
    
        if (in_array($new_tag_normalized, $tags)) {
            echo json_encode(['success' => false, 'message' => 'Tag already exists for this interaction.']);
            return;
        }
    
        $tags[] = $new_tag_normalized;
        $updated_tags = implode(',', $tags);
    
        $this->db->where('id', $interaction_id)
                 ->update(db_prefix() . 'whatsapp_interactions', ['tags' => $updated_tags]);
    
        if ($this->db->affected_rows() > 0) {
            echo json_encode(['success' => true, 'message' => 'Tag added successfully.', 'updated_tags' => $tags]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save the tag.']);
        }
    }

    /**
     * Deletes a tag from a chat interaction.
     */
    public function delete_tag()
    {
        if (!$this->input->is_ajax_request() || !$this->input->post()) {
            show_404();
        }
    
        $interaction_id = $this->input->post('interaction_id');
        $tag_to_remove = trim($this->input->post('tag'));
    
        if (empty($interaction_id) || empty($tag_to_remove)) {
            echo json_encode(['success' => false, 'message' => 'Interaction ID and tag are required.']);
            return;
        }
    
        $interaction = $this->db->select('tags')
                                ->where('id', $interaction_id)
                                ->get(db_prefix() . 'whatsapp_interactions')
                                ->row();
    
        if (!$interaction) {
            echo json_encode(['success' => false, 'message' => 'Interaction not found.']);
            return;
        }
    
        $tags = array_map('strtolower', array_filter(array_map('trim', explode(',', $interaction->tags))));
        $tag_to_remove_normalized = strtolower($tag_to_remove);
    
        if (!in_array($tag_to_remove_normalized, $tags)) {
            echo json_encode(['success' => false, 'message' => 'Tag not found in this interaction.']);
            return;
        }
    
        $tags = array_filter($tags, fn($tag) => $tag !== $tag_to_remove_normalized);
        $updated_tags = implode(',', $tags);
    
        $this->db->where('id', $interaction_id)
                 ->update(db_prefix() . 'whatsapp_interactions', ['tags' => $updated_tags]);
    
        if ($this->db->affected_rows() > 0) {
            echo json_encode(['success' => true, 'message' => 'Tag deleted successfully.', 'updated_tags' => $tags]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete the tag.']);
        }
    }
        public function activity_log_table()
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $this->app->get_table_data(module_views_path(WHATSAPP_MODULE, 'tables/activity_log_table'));
    }
    public function debug_token()
    {
        $token = $this->input->post('token');
        $url = "https://graph.facebook.com/debug_token?input_token={$token}&access_token={$token}";
        echo $this->curl_get($url);
    }

public function connection_status()
{
    $token       = $this->input->post('token');
    $business_id = $this->input->post('business_id');

    $result = [
        'success'     => false,
        'message'     => '',
        'permissions' => null,
        'phones'      => null,
        'webhook'     => null,
        'waba_id'     => null,
        'status'      => 'disconnected'
    ];

    if (empty($token) || empty($business_id)) {
        $result['message'] = 'Token and Business ID are required.';
        echo json_encode($result);
        return;
    }

    // Step 1: Token Check
    $permissions = json_decode($this->curl_get("https://graph.facebook.com/debug_token?input_token={$token}&access_token={$token}"));
    if (empty($permissions->data)) {
        $result['message'] = 'Invalid or expired token.';
        update_option('whatsapp_connection_status', 'disconnected');
        echo json_encode($result);
        return;
    }

    // Step 2: Business Info
    $business = json_decode($this->curl_get("https://graph.facebook.com/v17.0/{$business_id}?fields=owned_whatsapp_business_accounts&access_token={$token}"));
    if (empty($business->owned_whatsapp_business_accounts->data[0]->id)) {
        $result['message'] = 'Unable to fetch WhatsApp Business Account.';
        update_option('whatsapp_connection_status', 'disconnected');
        echo json_encode($result);
        return;
    }

    $waba_id = $business->owned_whatsapp_business_accounts->data[0]->id;
    update_option('whatsapp_business_account_id', $waba_id); // Save fallback ID if applicable

    // Step 3: Phone Numbers
    $phones = json_decode($this->curl_get("https://graph.facebook.com/v17.0/{$waba_id}/phone_numbers?access_token={$token}"));

    // Step 4: Webhook
    $webhook = json_decode($this->curl_get("https://graph.facebook.com/v17.0/{$waba_id}/subscribed_apps?access_token={$token}"));

    // Step 5: Save Settings
    update_option('whatsapp_access_token', $token);
    update_option('whatsapp_business_id', $business_id);
    update_option('whatsapp_waba_id', $waba_id);
    update_option('whatsapp_connection_status', 'connected');

    $result['success']     = true;
    $result['message']     = 'Connected successfully!';
    $result['permissions'] = $permissions;
    $result['phones']      = $phones;
    $result['webhook']     = $webhook;
    $result['waba_id']     = $waba_id;
    $result['status']      = 'connected';

    echo json_encode($result);
}

public function disconnect()
{
    if (!$this->input->is_ajax_request() || !is_admin()) {
        show_404();
    }

    // Clear all WhatsApp Cloud API related options
    delete_option('whatsapp_access_token');
    delete_option('whatsapp_business_id');
    delete_option('whatsapp_business_account_id');
    delete_option('whatsapp_waba_id');
    delete_option('whatsapp_webhook_token');
    delete_option('whatsapp_webhook_url');
    update_option('whatsapp_connection_status', 'disconnected');

    echo json_encode([
        'success' => true,
        'message' => 'Connection disconnected and settings cleared.'
    ]);
}

public function register_webhook()
{
    $token        = $this->input->post('token');
    $waba_id      = $this->input->post('waba_id');
    $verify_token = $this->input->post('verify_token');
    $callback_url = $this->input->post('callback_url');

    $url = "https://graph.facebook.com/v17.0/{$waba_id}/subscribed_apps";
    $payload = [
        'access_token'       => $token,
        'object'             => 'whatsapp_business_account',
        'callback_url'       => $callback_url,
        'verify_token'       => $verify_token,
        'subscribed_fields' => [
            'messages',
            'message_template_status_update',
            'message_template_category_update',
            'account_review_update',
            'business_capability_update',
            'phone_number_name_verification_event',
            'quality_update',
            'template_update'
        ]
    ];

    $response = $this->curl_post($url, $payload);

    // Save to DB
    update_option('whatsapp_webhook_token', $verify_token);
    update_option('whatsapp_webhook_url', $callback_url);

    echo $response;
}

public function unregister_webhook()
{
    $token   = $this->input->post('token');
    $waba_id = $this->input->post('waba_id');

    $url = "https://graph.facebook.com/v17.0/{$waba_id}/subscribed_apps?access_token={$token}";
    echo $this->curl_delete($url);
}


    private function curl_get($url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    private function curl_post($url, $data)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    private function curl_delete($url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => 'DELETE'
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

}
