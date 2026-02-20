<?php

defined('BASEPATH') || exit('No direct script access allowed');

if (!function_exists('get_whatsapp_bot_types')) {
    /**
     * Retrieves a list of all WhatsApp bot types or a single bot type if an ID is specified.
     *
     * @param  int|string $id Optional bot type ID.
     * @return array|null      An array of all bot types, a single bot type, or null if not found.
     */
    function get_whatsapp_bot_types($id = '')
    {
        $bot_types = [
            // Basic Text Bots
            [
                'id'          => 1,
                'label'       => _l('message_bot'),
                'description' => _l('message_bot_description'),
            ],
            // Template Bots
            [
                'id'          => 2,
                'label'       => _l('template_bot'),
                'description' => _l('template_bot_description'),
            ],
            // Menu Bots (Interactive Menu)
            [
                'id'          => 3,
                'label'       => _l('menu_bot'),
                'description' => _l('menu_bot_description'),
            ],
            // Flow Bots (Interactive flows)
            [
                'id'          => 4,
                'label'       => _l('flow_bot'),
                'description' => _l('flow_bot_description'),
            ],
            // Media Bots (e.g., Image, Video, Document, Audio)
            [
                'id'          => 5,
                'label'       => _l('media_bot'),
                'description' => _l('media_bot_description'),
            ],
            // Location Bots
            [
                'id'          => 6,
                'label'       => _l('location_bot'),
                'description' => _l('location_bot_description'),
            ],
            // Interactive Buttons Bots
            [
                'id'          => 7,
                'label'       => _l('interactive_buttons_bot'),
                'description' => _l('interactive_buttons_bot_description'),
            ],
            // List Bots (Interactive List)
            [
                'id'          => 8,
                'label'       => _l('list_bot'),
                'description' => _l('list_bot_description'),
            ],
            // Quick Reply Bots (Interactive Quick Replies)
            [
                'id'          => 9,
                'label'       => _l('quick_reply_bot'),
                'description' => _l('quick_reply_bot_description'),
            ],
            // Sticker Bots
            [
                'id'          => 10,
                'label'       => _l('sticker_bot'),
                'description' => _l('sticker_bot_description'),
            ],
            // Contact Bots
            [
                'id'          => 11,
                'label'       => _l('contact_bot'),
                'description' => _l('contact_bot_description'),
            ],
            // AI Bot
            [
                'id'          => 12,
                'label'       => _l('ai_bot'),
                'description' => _l('ai_bot_description'),
            ],
            // Review Bot (for requesting star reviews, etc.)
            [
                'id'          => 13,
                'label'       => _l('review_bot'),
                'description' => _l('review_bot_description'),
            ],
        ];

        // If a specific ID is requested, search the array
        if (!empty($id)) {
            $key = array_search($id, array_column($bot_types, 'id'));
            return $bot_types[$key] ?? null; // Return null if ID is not found
        }

        // Otherwise, return the entire array of bot types
        return $bot_types;
    }
}

if (!function_exists('get_whatsapp_reply_triggers')) {
    /**
     * Retrieves an array of all WhatsApp reply triggers, or a single trigger if an ID is specified.
     *
     * @param  int|string $id Optional trigger ID.
     * @return array|null     An array of all triggers, a single trigger, or null if not found.
     */
    function get_whatsapp_reply_triggers($id = '')
    {
        $reply_types = [
            // Basic Triggers
            [
                'id'      => 1,
                'label'   => _l('on_exact_match'),
                'example' => _l('on_exact_match_description'),
            ],
            [
                'id'      => 2,
                'label'   => _l('when_message_contains'),
                'example' => _l('when_message_contains_description'),
            ],
            [
                'id'      => 3,
                'label'   => _l('when_client_send_the_first_message'),
                'example' => _l('when_client_send_the_first_message_description'),
            ],
            [
                'id'      => 4,
                'label'   => _l('on_keyword_match'),
                'example' => _l('on_keyword_match_description'),
            ],
            [
                'id'      => 5,
                'label'   => _l('within_office_time_range'),
                'example' => _l('within_office_time_range_description'),
            ],
            [
                'id'      => 6,
                'label'   => _l('outof_office_time_range'),
                'example' => _l('outof_office_time_range_description'),
            ],
            [
                'id'      => 7,
                'label'   => _l('first_message_in_session'),
                'example' => _l('first_message_in_session_description'),
            ],
            // Additional or custom triggers
            [
                'id'      => 8,
                'label'   => _l('on_user_idle_timeout'),
                'example' => _l('on_user_idle_timeout_description'),
            ],
            [
                'id'      => 9,
                'label'   => _l('on_user_requests_human'),
                'example' => _l('on_user_requests_human_description'),
            ],
            [
                'id'      => 10,
                'label'   => _l('on_synonym_match'),
                'example' => _l('on_synonym_match_description'),
            ],
            // New triggers focusing on your custom use cases:
            [
                'id'      => 11,
                'label'   => _l('on_chat_timeout_reminder'),
                'example' => _l('on_chat_timeout_reminder_description'),
            ],
            [
                'id'      => 12,
                'label'   => _l('on_no_response_inform_staff'),
                'example' => _l('on_no_response_inform_staff_description'),
            ],
            [
                'id'      => 13,
                'label'   => _l('on_chat_close_request_star_rating'),
                'example' => _l('on_chat_close_request_star_rating_description'),
            ],
        ];

        // If an ID is provided, attempt to find that trigger
        if (!empty($id)) {
            $key = array_search($id, array_column($reply_types, 'id'));
            return $reply_types[$key] ?? null; // null if ID not found
        }

        // Otherwise, return all triggers
        return $reply_types;
    }
}


/**
 * Get WhatsApp template based on ID
 *
 * @param string $id
 * @return array
 */
if (!function_exists('get_whatsapp_template')) {
    function get_whatsapp_template($id = '')
    {
        $CI = get_instance();
        $CI->db->order_by('language', 'asc');

        if (is_numeric($id)) {
            // If $id is numeric, fetch by ID
            $query = $CI->db->get_where(db_prefix() . 'whatsapp_templates', ['id' => $id, 'status' => 'APPROVED']);
        } elseif (!empty($id)) {
            // If $id is a string, fetch by template_name
            $query = $CI->db->get_where(db_prefix() . 'whatsapp_templates', ['template_name' => $id, 'status' => 'APPROVED']);
        } else {
            // If no $id is provided, fetch all approved templates
            $query = $CI->db->get_where(db_prefix() . 'whatsapp_templates', ['status' => 'APPROVED']);
        }

        if (is_numeric($id) || !empty($id)) {
            return $query->row_array();
        }

        return $query->result_array();
    }
}

/**
 * Get campaign data based on campaign ID
 *
 * @param string $campaign_id
 * @return array
 */
if (!function_exists('whatsapp_get_campaign_data')) {
    function whatsapp_get_campaign_data($campaign_id = '')
    {
        return get_instance()->db->get_where(db_prefix().'whatsapp_campaign_data', ['campaign_id' => $campaign_id])->result_array();
    }
}
if (!function_exists('whatsapp_get_campaign')) {
    function whatsapp_get_campaign($campaign_id = '')
    {
        return get_instance()->db->get_where(db_prefix().'whatsapp_campaign', ['id' => $campaign_id])->result_array();
    }
}
/**
 * Check if a string is a valid JSON
 *
 * @param string $string
 * @return bool
 */
if (!function_exists('wbIsJson')) {
    function wbIsJson($string)
    {
        return ((is_string($string) &&
            (is_object(json_decode($string)) ||
                is_array(json_decode($string))))) ? true : false;
    }
}

/**
 * Get the relation types
 *
 * @return array
 */
if (!function_exists('whatsapp_get_rel_type')) {
    function whatsapp_get_rel_type()
    {
        return [
            [
                'key'  => 'leads',
                'name' => _l('leads'),
            ],
            [
                'key'  => 'contacts',
                'name' => _l('contacts'),
            ],
            [
                'key'  => 'whatsapp_groups', // New entry for WhatsApp Groups
                'name' => _l('whatsapp_groups'), // Display name for the type
            ],
        ];
    }
}

/**
 * Replaces all merge field placeholders in the given $data array using whatsappParseText().
 *
 * @param array $data
 * @return array
 */
function whatsappReplaceAllParamsInData(array $data): array
{
    $rel_type = $data['rel_type'] ?? '';
    $types = ['header', 'body', 'footer'];

    foreach ($types as $type) {
        // Skip if data or param count is not available
        if (empty($data["{$type}_params_count"]) || (int)$data["{$type}_params_count"] <= 0) {
            continue;
        }

        // Always call the parser to apply merge field replacements
        $parsed = whatsappParseText($rel_type, $type, $data, 'data');

        // Replace original param values (header_params/body_params/footer_params)
        if (!empty($parsed)) {
            if (strtoupper($data['parameter_format'] ?? 'POSITIONAL') === 'NAMED') {
                $cleaned_params = [];

                foreach ($parsed as $item) {
                    if (isset($item['parameter_name'])) {
                        $cleaned_params[$item['parameter_name']] = ['value' => $item['text']];
                    }
                }

                $data["{$type}_params"] = json_encode($cleaned_params);
            } else {
                // For POSITIONAL, use simple index-based structure
                $cleaned_params = [];
                foreach ($parsed as $val) {
                    $cleaned_params[] = ['value' => $val];
                }
                $data["{$type}_params"] = json_encode($cleaned_params);
            }
        }

        // Also update the data string itself (header_data, body_data, etc.)
        $data["{$type}_data"] = whatsappParseText($rel_type, $type, $data, 'text');
    }

    return $data;
}


if (!function_exists('whatsappParseText')) {
    /**
     * Parse WhatsApp template data with CRM merge fields.
     *
     * @param string|null $rel_type      e.g. 'leads', 'contacts'
     * @param string      $type          'body', 'header', or 'footer'
     * @param array       $data          All template + relation data
     * @param string      $return_type   'text', 'data', or 'log'
     * @param bool        $debug         Return debug info (optional)
     * @return string|array
     */
    function whatsappParseText($rel_type, $type, $data, $return_type = 'text', $debug = false)
    {
        $rel_type = $rel_type ?? '';
        $debug_log = [];

        // Load merge fields unless general/non-relational.
        if (!empty($rel_type) && $rel_type !== 'whatsapp_groups' && $rel_type !== 'general') {
            // Convert "contacts" to "client" if needed.
            $rel_type = ($rel_type === 'contacts') ? 'client' : $rel_type;
            $CI = get_instance();
            $CI->load->library('merge_fields/app_merge_fields');

            // Get merge fields for the relation type.
            $merge_fields = $CI->app_merge_fields->format_feature(
                $rel_type . '_merge_fields',
                $data['userid'] ?? $data['rel_id'],
                $data['rel_id'] ?? null
            );
            // Get fallback ("other") merge fields.
            $other_merge_fields = $CI->app_merge_fields->format_feature('other_merge_fields');
            $merge_fields = array_merge($other_merge_fields, $merge_fields);
        } else {
            $merge_fields = [];
        }

        $parse_data = [];
        // Get the JSON string for parameters (e.g., header_params, body_params, footer_params).
        $params = $data["{$type}_params"] ?? '[]';

        // Decode parameters if the string is valid JSON.
        if (wbIsJson($params)) {
            $parsed_text = json_decode($params, true);

            // Determine NAMED vs POSITIONAL by testing if keys are sequential numbers.
            $is_named = array_keys($parsed_text) !== range(0, count($parsed_text) - 1);

            if ($is_named) {
                foreach ($parsed_text as $key => $item) {
                    $value = isset($item['value']) ? trim($item['value']) : '';

                    // Replace fallback placeholder: e.g. {{lead_name|Customer}}
                    if (preg_match('/{{(.*?)\|(.*?)}}/', $data["{$type}_data"], $matches)) {
                        $value = $value ?: $matches[2];
                    }

                    // Replace any merge field keys found inside the value.
                    foreach ($merge_fields as $mf_key => $mf_val) {
                        $value = str_replace($mf_key, $mf_val ?: ' ', $value);
                    }

                    // Legacy support: replace @{...} with { ... }.
                    $value = preg_replace('/@{(.*?)}/', '{$1}', $value);
                    // Reduce multiple spaces.
                    $parsed_text[$key] = preg_replace('/\s+/', ' ', $value);

                    if ($debug) {
                        $debug_log[$key] = $parsed_text[$key];
                    }

                    // If we're returning text, replace the corresponding placeholder in the main data text.
                    if ($return_type === 'text') {
                        $data["{$type}_data"] = str_replace("{{{$key}}}", $parsed_text[$key], $data["{$type}_data"]);
                    }

                    $parse_data[] = [
                        'parameter_name' => $key,
                        'text'           => $parsed_text[$key],
                    ];
                }
            } else {
                // POSITIONAL format.
                $parsed_text = array_map(function ($item) use ($merge_fields, &$debug_log) {
                    $value = trim($item['value']);
                    foreach ($merge_fields as $k => $v) {
                        $value = str_replace($k, $v ?: ' ', $value);
                    }
                    $value = preg_replace('/\s+/', ' ', $value);
                    $debug_log[] = $value;
                    return $value;
                }, $parsed_text);

                $param_count = (int)($data["{$type}_params_count"] ?? count($parsed_text));
                for ($i = 1; $i <= $param_count; $i++) {
                    $val = $parsed_text[$i - 1] ?? ' ';
                    if ($return_type === 'text') {
                        $data["{$type}_data"] = str_replace("{{{$i}}}", $val, $data["{$type}_data"]);
                    }
                    $parse_data[] = $val;
                }
            }
        }

        // Return result based on requested type.
        if ($return_type === 'text') {
            return preg_replace('/\s+/', ' ', trim($data["{$type}_data"] ?? ''));
        } elseif ($return_type === 'data') {
            return $parse_data;
        } elseif ($return_type === 'log') {
            return $debug_log;
        }

        return $parse_data;
    }
}



/**
 * Get the campaign status based on status ID
 *
 * @param string $status_id
 * @return array
 */
if (!function_exists('whatsapp_campaign_status')) {
    function whatsapp_campaign_status($status_id = '')
    {
        $statusid              = ['0', '1', '2'];
        $status['label']       = ['Failed', 'Pending', 'Success'];
        $status['label_class'] = ['label-danger', 'label-warning', 'label-success'];
        if (in_array($status_id, $statusid)) {
            $index = array_search($status_id, $statusid);
            if (false !== $index && isset($status['label'][$index])) {
                $status['label'] = $status['label'][$index];
            }
            if (false !== $index && isset($status['label_class'][$index])) {
                $status['label_class'] = $status['label_class'][$index];
            }
        } else {
            $status['label']       = _l('draft');
            $status['label_class'] = 'label-default';
        }

        return $status;
    }
}

/**
 * Get all staff members
 *
 * @return array
 */
if (!function_exists('whatsapp_get_all_staff')) {
    function whatsapp_get_all_staff()
    {
        return get_instance()->db->get(db_prefix().'staff')->result_array();
    }
}

/**
 * Get staff members allowed to view message templates
 *
 * @return array
 */
if (!function_exists('wbGetStaffMembersAllowedToViewMessageTemplates')) {
    function wbGetStaffMembersAllowedToViewMessageTemplates()
    {
        get_instance()->db->join(db_prefix().'staff_permissions', db_prefix().'staff_permissions.staff_id = '.db_prefix().'staff.staffid', 'LEFT');
        get_instance()->db->where([db_prefix().'staff_permissions.capability' => 'view', db_prefix().'staff_permissions.feature' => 'whatsapp_template']);
        get_instance()->db->or_where([db_prefix().'staff.admin' => '1']);

        return get_instance()->db->get(db_prefix().'staff')->result_array();
    }
}

/**
 * Get the interaction ID based on data, relation type, ID, name, and phone number
 *
 * @param array $data
 * @param string $relType
 * @param string $id
 * @param string $name
 * @param string $phonenumber
 * @return int
 */


/**
 * Decode WhatsApp signs to HTML tags
 *
 * @param string $text
 * @return string
 */
if (!function_exists('whatsappDecodeWhatsAppSigns')) {
    function whatsappDecodeWhatsAppSigns($text)
    {
        $patterns = [
            '/\*(.*?)\*/',       // Bold
            '/_(.*?)_/',         // Italic
            '/~(.*?)~/',         // Strikethrough
            '/```(.*?)```/',      // Monospace
        ];
        $replacements = [
            '<strong>$1</strong>',
            '<em>$1</em>',
            '<del>$1</del>',
            '<code>$1</code>',
        ];

        return preg_replace($patterns, $replacements, $text);
    }
}
/**
 * Generate a random filename with a number
 *
 * @param string $originalName The original file name
 * @return string The new random file name
 */
/**
 * Generate a unique filename using current date, time, and a random number
 *
 * @param string $originalName The original file name
 * @return string The new unique file name
 */
if (!function_exists('generate_random_filename')) {
function generate_random_filename($originalName)
{
    // Extract the file extension
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    
    // Get the current timestamp
    $timestamp = date('YmdHis'); // Format: YYYYMMDDHHMMSS

    // Generate a random number
    $randomNumber = rand(1000, 9999); // You can adjust the range as needed

    // Generate a new filename
    return $timestamp . '_' . $randomNumber . '.' . $extension;
}
}





if (!function_exists('whatsapp_get_allowed_extension')) {
    function whatsapp_get_allowed_extension()
    {
        return [
            'image' => [
                'extension' => '.jpeg, .png',
                'size'      => 5
            ],
            'video' => [
                'extension' => '.mp4, .3gp',
                'size'      => 16,
            ],
            'audio' => [
                'extension' => '.aac, .amr, .mp3, .m4a, .ogg',
                'size'      => 16,
            ],
            'document' => [
                'extension' => '.pdf, .doc, .docx, .txt, .xls, .xlsx, .ppt, .pptx',
                'size'      => 100,
            ],
        ];
    }
}
if (!function_exists('truncate_message')) {
    function truncate_message($message, $limit = 25) {
    // Remove HTML tags
    $message = strip_tags($message);
    // Check if the message length exceeds the limit
    if (strlen($message) > $limit) {
        // Truncate the message and add ellipsis
        $message = substr($message, 0, $limit) . '...';
    }
    return $message;
}

}
if (!function_exists('whatsapp_get_media_types')) {
    function whatsapp_get_media_types()
    {
        return [
            [
                'id'    => 'image',
                'label' => _l('Image'),
            ],
            [
                'id'    => 'video',
                'label' => _l('Video'),
            ],
            [
                'id'    => 'document',
                'label' => _l('Document'),
            ],
            [
                'id'    => 'audio',
                'label' => _l('Audio'),
            ],
        ];
    }
}
if (!function_exists('whatsapp_default_phone_number')) {
    /**
     * Fetches the details of the default phone number from the database.
     *
     * @return array|null Default phone number details or null if not found.
     */
    function whatsapp_default_phone_number()
    {
        $CI =& get_instance(); // Get CI instance

        // Load database library
        $CI->load->database();

        // Query to get the default phone number details
        $CI->db->select('*');
        $CI->db->from(db_prefix() . 'whatsapp_numbers');
        $CI->db->where('is_default', 1);
        $query = $CI->db->get();

        // Return the result
        return $query->row_array(); // Return as associative array
    }
}
if (!function_exists('whatsapp_bots')) {
    /**
     * Fetches the details of all bots linked to a specific interaction ID.
     *
     * @param int $interaction_id The ID of the interaction to fetch bots for.
     * @return array|null An array of bot details or null if not found.
     */
    function whatsapp_bots($interaction_id)
    {
        $CI =& get_instance(); // Get CI instance

        // Start building the query
        $CI->db->select('wb.id as bot_id, wb.*, wt.*, wi.*');
        $CI->db->from(db_prefix() . 'whatsapp_bot as wb');

        // Join with whatsapp_templates table
        $CI->db->join(db_prefix() . 'whatsapp_templates as wt', 'wb.template_id = wt.id', 'left');

        // Join with whatsapp_interaction table using interaction_id
        $CI->db->join(db_prefix() . 'whatsapp_interactions as wi', 'wi.id = ' . $CI->db->escape($interaction_id), 'left');

        // Ensure bot is active
        $CI->db->where('wb.is_bot_active', 1);

        // Filter by the specific interaction
        $CI->db->where('wi.id', $interaction_id);

        // Execute the query and return the result
        $result = $CI->db->get()->result_array();

        // Return the result
        return $result;
    }
}


if (!function_exists('whatsapp_unreadmessages')) {
    /**
     * Fetches the count of unread WhatsApp messages from the database.
     *
     * @return int The total number of unread messages.
     */
    function whatsapp_unreadmessages()
    {
        $CI =& get_instance(); // Get the CodeIgniter instance

        // Load the database library if not already loaded
        $CI->load->database();

        // Build the query to sum up the 'unread' values
        $CI->db->select('SUM(unread) as unread_count');
        $CI->db->from(db_prefix() . 'whatsapp_interactions');
        $CI->db->where('unread >', 0);
        $query = $CI->db->get();

        // Retrieve the row as an associative array
        $result = $query->row_array();

        // Return the count if it exists, or 0 otherwise
        return isset($result['unread_count']) ? (int)$result['unread_count'] : 0;
    }
}

if (!function_exists('whatsapp_handle_bot_upload')) {
    function whatsapp_handle_bot_upload($bot_id)
    {
        // Check if a file is uploaded with the 'filename' key
        if (isset($_FILES['filename']['name']) && !empty($_FILES['filename']['name'])) {
            // Get the upload path for bot files
            $path = get_upload_path_by_type('bot');

            // Ensure the temporary file path exists
            $tmpFilePath = $_FILES['filename']['tmp_name'];
            if (!empty($tmpFilePath) && is_uploaded_file($tmpFilePath)) {

                // Ensure the upload path exists or create it
                _maybe_create_upload_path($path);

                // Generate a unique filename using time and a random number
                $filename = generate_random_filename($_FILES['filename']['name']);

                // Check if the file extension is allowed
                if (_upload_extension_allowed($filename)) {

                    // Define the new file path
                    $newFilePath = $path . $filename;

                    // Move the uploaded file to the new location
                    if (move_uploaded_file($tmpFilePath, $newFilePath)) {

                        // Update the database with the new filename
                        get_instance()->db->update(db_prefix() . 'whatsapp_bot', ['filename' => $filename], ['id' => $bot_id]);

                        // Return the filename on success
                        return $filename;
                    } else {
                        log_message('error', 'Failed to move uploaded file to destination: ' . $newFilePath);
                    }
                } else {
                    log_message('error', 'File extension not allowed for file: ' . $filename);
                }
            } else {
                log_message('error', 'Temporary file path is invalid or file not uploaded.');
            }
        } else {
            log_message('error', 'No file was uploaded or the file name is empty.');
        }
        // Return false if upload failed
        return false;
    }
}


if (!function_exists('whatsapp_handle_campaign_upload')) {
    function whatsapp_handle_campaign_upload($id)
    {
        if (isset($_FILES['filename']['name']) && !empty($_FILES['filename']['name'])) {
            $path        = get_upload_path_by_type($type);
            $tmpFilePath = $_FILES['filename']['tmp_name'];
            if (!empty($tmpFilePath) && is_uploaded_file($tmpFilePath)) {
                _maybe_create_upload_path($path);

                // Generate a unique filename using current date, time, and a random number
                $filename = generate_random_filename($_FILES['filename']['name']);

                if (_upload_extension_allowed($filename)) {
                    $newFilePath = $path . $filename;
                    if (move_uploaded_file($tmpFilePath, $newFilePath)) {
                        get_instance()->db->update(db_prefix() . 'whatsapp_campaigns', ['filename' => $filename], ['id' => $id]);
                        return $filename;
                    } else {
                        log_message('error', 'Failed to move uploaded file to destination: ' . $newFilePath);
                    }
                } else {
                    log_message('error', 'File extension not allowed for file: ' . $filename);
                }
            } else {
                log_message('error', 'Temporary file path is invalid or file not uploaded.');
            }
        } else {
            log_message('error', 'No file was uploaded or the file name is empty.');
        }
        return false;
    }
}
if (!function_exists('whatsapp_handle_header_upload')) {
    /**
     * Handle file upload in header_params
     *
     * This function processes the `header_params` array, checks for files, uploads them,
     * and replaces the file path in the array.
     *
     * @param array $header_params The header_params array with potential files.
     * @param string $bot_id The ID of the bot (used to associate the uploaded files).
     * @return array The updated header_params array with file paths.
     */
    function whatsapp_handle_header_upload($header_params, $bot_id)
    {
        // Path to upload the files
        $upload_path = get_upload_path_by_type('bot');

        // Ensure the upload path exists
        _maybe_create_upload_path($upload_path);

        // Loop through each header param
        foreach ($header_params as &$param) {
            // Check if the param is a file type and the file is uploaded
            if (isset($param['type']) && $param['type'] == 'file' && isset($_FILES[$param['name']])) {
                $file = $_FILES[$param['name']];

                // Validate the temporary file path
                if (!empty($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
                    // Generate a random filename to avoid conflicts
                    $filename = generate_random_filename($file['name']);

                    // Check if the file extension is allowed
                    if (_upload_extension_allowed($filename)) {
                        $new_file_path = $upload_path . $filename;

                        // Move the file to the final destination
                        if (move_uploaded_file($file['tmp_name'], $new_file_path)) {
                            // Update the param with the new file path
                            $param['value'] = $new_file_path;

                            // Optionally, update the database with the full header_params array
                            get_instance()->db->update(db_prefix() . 'whatsapp_bot', ['header_params' => json_encode($header_params)], ['id' => $bot_id]);
                        } else {
                            log_message('error', 'Failed to move uploaded file to destination: ' . $new_file_path);
                        }
                    } else {
                        log_message('error', 'File extension not allowed for file: ' . $filename);
                    }
                }
            }
        }

        // Return the updated header_params array
        return $header_params;
    }
}
if (!function_exists('get_template_maper')) {
    function get_template_maper($templateId, $type, $type_id = null) {
        // Fetch the template details (assumes this function returns all template fields,
        // including the newly added ones)
        $template = get_whatsapp_template($templateId);

        // Initialize template data
        $header_data = $template['header_data_text'] ?? '';
        $body_data   = $template['body_data'] ?? '';
        $footer_data = $template['footer_data'] ?? '';
        $button_data = !empty($template['buttons_data']) ? json_decode($template['buttons_data'], true) : [];

        // Initialize parameters
        $campaign_or_bot = null;
        $header_params = $body_params = $footer_params = null;

        // Get the CI instance
        $CI =& get_instance();
        $CI->load->database();

        // Construct SQL based on the provided type_id
        if (!empty($type_id)) {
            // Fetch data for campaign or bot based on type
            if ($type === 'campaign') {
                $sql = "
                    SELECT 
                        wc.*, 
                        wt.template_name as template_name,
                        wt.template_id as tmp_id,
                        wt.header_params_count,
                        wt.body_params_count,
                        wt.footer_params_count,
                        wt.header_data_format,
                        wt.parameter_format,
                        wt.sub_category,
                        wt.header_example,
                        wt.body_example,
                        wt.footer_example,
                        wt.buttons_example,
                        wt.created_at,
                        wt.updated_at,
                        wt.template_description,
                        wt.is_custom,
                        CONCAT('[', GROUP_CONCAT(wcd.rel_id SEPARATOR ','), ']') as rel_ids,
                        wc.filename,
                        wc.header_params,
                        wc.body_params,
                        wc.footer_params
                    FROM 
                        " . db_prefix() . "whatsapp_campaigns wc
                    LEFT JOIN 
                        " . db_prefix() . "whatsapp_templates wt ON wt.id = wc.template_id
                    LEFT JOIN 
                        " . db_prefix() . "whatsapp_campaign_data wcd ON wcd.campaign_id = wc.id
                    WHERE 
                        wc.id = ?
                    GROUP BY wc.id
                ";
                $campaign_or_bot = $CI->db->query($sql, [$type_id])->row_array();
            } elseif ($type === 'bot') {
                $sql = "
                    SELECT 
                        wb.*, 
                        wt.template_name as template_name,
                        wt.template_id as tmp_id,
                        wt.header_params_count,
                        wt.body_params_count,
                        wt.footer_params_count,
                        wt.header_data_format,
                        wt.parameter_format,
                        wt.sub_category,
                        wt.header_example,
                        wt.body_example,
                        wt.footer_example,
                        wt.buttons_example,
                        wt.created_at,
                        wt.updated_at,
                        wt.template_description,
                        wt.is_custom,
                        wb.filename,
                        wb.header_params,
                        wb.body_params,
                        wb.footer_params
                    FROM 
                        " . db_prefix() . "whatsapp_bot wb
                    LEFT JOIN 
                        " . db_prefix() . "whatsapp_templates wt ON wt.id = wb.template_id
                    WHERE 
                        wb.id = ?
                    GROUP BY wb.id
                ";
                $campaign_or_bot = $CI->db->query($sql, [$type_id])->row_array();
            }

            // Decode parameters if available
            if ($campaign_or_bot) {
                $header_params = json_decode($campaign_or_bot['header_params'] ?? '', true);
                $body_params = json_decode($campaign_or_bot['body_params'] ?? '', true);
                $footer_params = json_decode($campaign_or_bot['footer_params'] ?? '', true);
            }
        } else {
            // Fetch all campaigns if type_id is null
            $sql = "
                SELECT 
                    wc.*, 
                    wt.template_name as template_name,
                    wt.template_id as tmp_id,
                    wt.header_params_count,
                    wt.body_params_count,
                    wt.footer_params_count,
                    CONCAT('[', GROUP_CONCAT(wcd.rel_id SEPARATOR ','), ']') as rel_ids,
                    wc.filename
                FROM 
                    " . db_prefix() . "whatsapp_campaigns wc
                LEFT JOIN 
                    " . db_prefix() . "whatsapp_templates wt ON wt.id = wc.template_id
                LEFT JOIN 
                    " . db_prefix() . "whatsapp_campaign_data wcd ON wcd.campaign_id = wc.id
                GROUP BY wc.id
            ";
            $campaign_or_bot = $CI->db->query($sql)->result_array(); // Get all campaigns
        }

        // Prepare the data array for the view
        $data = [
            'template'      => $template,
            'header_data'   => $header_data,
            'body_data'     => $body_data,
            'footer_data'   => $footer_data,
            'button_data'   => $button_data,
            'header_params' => $header_params,
            'body_params'   => $body_params,
            'footer_params' => $footer_params,
            'type'          => $type,
            'data'          => $campaign_or_bot, // This will be 'campaign' or 'bot'
        ];

        // Load the 'variables' view with all data
        $view = $CI->load->view('whatsapp/templates/variables', $data, true);

        // (Optional) You can still check for unsupported formats and errors if needed.
        // For now, we'll assume all data is sent to the view directly.

        // Return the complete response including the view and all data
        return [
            'view'         => $view,
            'header_data'  => $header_data,
            'body_data'    => $body_data,
            'footer_data'  => $footer_data,
            'button_data'  => $button_data,
            'header_params'=> $header_params,
            'body_params'  => $body_params,
            'footer_params'=> $footer_params,
            'type'         => $type,
            'data'         => $campaign_or_bot,
            'template'     => $template,
        ];
    }
}

if (!function_exists('getAIReply')) {
    function getAIReply($interaction_id, $usermessage = null, $mymessage = null)
    {
        // Get the CI instance
        $CI =& get_instance();

        // Fetch the interaction details based on the interaction ID
        $interaction_details = $CI->whatsapp_interaction_model->get_interaction($interaction_id);

        // Check if the interaction details are empty
        if (empty($interaction_details)) {
            log_message('error', "No interaction details found for ID: $interaction_id");
            return 'No interaction details available.';
        }

        // Retrieve the phone details from interaction (array or object)
        if (is_array($interaction_details)) {
            $wa_no = $interaction_details['wa_no'];
            $wa_no_id = $interaction_details['wa_no_id'];
        } else {
            $wa_no = $interaction_details->wa_no;
            $wa_no_id = $interaction_details->wa_no_id;
        }

        // Fetch the AI prompt for the specific phone number from the 'whatsapp_numbers' table
        $CI->db->select('ai_prompt');
        $CI->db->where('phone_number_id', $wa_no_id);
        $query = $CI->db->get(db_prefix() . 'whatsapp_numbers');
        $ai_prompt = $query->row()->ai_prompt ?? '';

        // Define a custom prompt to guide the AI's responses
        $custom_prompt = [
            [
                'role' => 'system',
                'content' => 'You are a helpful assistant. Please provide concise and accurate responses. ' . ($ai_prompt ?: 'You may include specific context here.')
            ],
            [
                'role' => 'user',
                'content' => 'You may include specific context here that you want the AI to consider.'
            ]
        ];

        // Fetch the interaction history for the given interaction ID
        $interaction_history = $CI->whatsapp_interaction_model->get_interaction_history($interaction_id);

        // Merge the interaction history with the custom prompt if history exists
        if (!empty($interaction_history)) {
            $messages = array_merge($custom_prompt, $interaction_history);
        } else {
            $messages = $custom_prompt;
        }

        // Append optional messages if provided
        if ($mymessage) {
            $messages[] = [
                'role' => 'system',
                'content' => $mymessage
            ];
        }
        if ($usermessage) {
            $messages[] = [
                'role' => 'user',
                'content' => $usermessage
            ];
        }

        // Prepare the API request
        $openai_token = get_option('whatsapp_openai_token');
        $api_url = 'https://api.openai.com/v1/chat/completions';
        $payload = json_encode([
            'model'    => 'gpt-4',
            'messages' => $messages,
        ]);

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer $openai_token"
        ]);

        // Execute the request and capture any cURL errors
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $error_message = curl_error($ch);
            log_message('error', "cURL Error: " . $error_message);
            curl_close($ch);
            return "Error: " . $error_message;
        }
        curl_close($ch);

        // Decode the response from OpenAI
        $response = json_decode($result);
        if (isset($response->choices[0]->message->content)) {
            $ai_reply_content = $response->choices[0]->message->content;
        } else {
            $ai_reply_content = 'Error: AI did not provide a valid reply.'.$response->error->message;
        }

        return $ai_reply_content;
    }
}
if (!function_exists('get_whatsapp_number_data')) {
    /**
     * Retrieves WhatsApp number data from the database.
     *
     * @param mixed $id Optional ID. If provided, returns a single record; otherwise, returns all records.
     * @return array|null An associative array of WhatsApp number data or an array of records.
     */
    function get_whatsapp_number_data($id = '')
    {
        $CI =& get_instance();
        $table = db_prefix() . 'whatsapp_numbers';

        if (!empty($id)) {
            // Get a single record based on ID.
            $CI->db->where('phone_number_id', $id);
            $query = $CI->db->get($table);
            return $query->row_array();
        }

        // Otherwise, return all records.
        $query = $CI->db->get($table);
        return $query->result_array();
    }
}
