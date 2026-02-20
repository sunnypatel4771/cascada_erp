<?php
defined('BASEPATH') || exit('No direct script access allowed');

class Whatsapp_interaction_model extends App_Model
{
  protected $whatsappLibrary;

  public function __construct()
  {
    parent::__construct();
    $this->initialize();
  }

  private function initialize()
  {
    $this->set_charset_utf8mb4();
    $this->load->library('whatsappLibrary');
    $this->whatsappLibrary = new whatsappLibrary();
  }

  private function set_charset_utf8mb4()
  {

    $this->db->query("SET NAMES utf8mb4");
    $this->db->query("SET character_set_connection=utf8mb4");
    $this->db->query("SET character_set_results=utf8mb4");
    $this->db->query("SET character_set_client=utf8mb4");
  }
public function load_templates($accessToken = '', $accountId = '')
{
    // Fetch templates from WhatsApp API
    $templates = $this->whatsappLibrary->loadTemplatesFromWhatsApp($accessToken, $accountId);

    if (!$templates['status']) {
        return [
            'success' => false,
            'type'    => 'danger',
            'message' => $templates['message'],
        ];
    }

    $data = $templates['data'];
    $insertData = [];

    foreach ($data as $templateData) {
        $components = array_column($templateData->components, null, 'type');

        $headerText  = isset($components['HEADER']) ? $components['HEADER']->text : null;
        $bodyText    = isset($components['BODY']) ? $components['BODY']->text : null;
        $footerText  = isset($components['FOOTER']) ? $components['FOOTER']->text : null;
        $buttons     = isset($components['BUTTONS']) ? json_encode($components['BUTTONS']) : '[]';

        // Safe examples
        $headerExample  = isset($components['HEADER']->example) ? json_encode($components['HEADER']->example) : null;
        $bodyExample    = isset($components['BODY']->example) ? json_encode($components['BODY']->example) : null;
        $footerExample  = isset($components['FOOTER']->example) ? json_encode($components['FOOTER']->example) : null;
        $buttonsExample = isset($components['BUTTONS']->example) ? json_encode($components['BUTTONS']->example) : null;

        // Safe parameter count parsing
        preg_match_all('/{{(.*?)}}/', (string)$headerText, $headerMatches);
        preg_match_all('/{{(.*?)}}/', (string)$bodyText, $bodyMatches);
        preg_match_all('/{{(.*?)}}/', (string)$footerText, $footerMatches);

        $record = [
            'template_id'           => $templateData->id,
            'template_name'         => $templateData->name,
            'language'              => $templateData->language,
            'status'                => $templateData->status,
            'category'              => $templateData->category,
            'sub_category'          => $templateData->sub_category ?? null,
            'parameter_format'      => $templateData->parameter_format ?? 'NAMED',
            'header_data_format'    => isset($components['HEADER']) ? $components['HEADER']->format : '',
            'header_data_text'      => $headerText,
            'header_params_count'   => count($headerMatches[0]),
            'header_example'        => $headerExample,
            'body_data'             => $bodyText,
            'body_params_count'     => count($bodyMatches[0]),
            'body_example'          => $bodyExample,
            'footer_data'           => $footerText,
            'footer_params_count'   => count($footerMatches[0]),
            'footer_example'        => $footerExample,
            'buttons_data'          => $buttons,
            'buttons_example'       => $buttonsExample,
            'template_description'  => $templateData->description ?? null,
            'is_custom'             => $templateData->is_custom ?? 0,
        ];

        $insertData[] = $record;
    }

    // Insert or update
    foreach ($insertData as $record) {
        $existing = $this->db->where('template_id', $record['template_id'])
                             ->get(db_prefix() . 'whatsapp_templates')
                             ->row();

        if ($existing) {
            $this->db->where('template_id', $record['template_id']);
            $this->db->update(db_prefix() . 'whatsapp_templates', $record);
        } else {
            $this->db->insert(db_prefix() . 'whatsapp_templates', $record);
        }
    }

    return [
        'success' => true,
        'type'    => 'success',
        'message' => 'Templates loaded successfully'
    ];
}


  public function format_button_data($data)
  {
    // Retrieve the buttons data from the input $data array
    $buttons = $data['buttons_data'] ?? null;

    // Initialize a variable to store the formatted HTML
    $formatted_html = '';
    if (isset($buttons)) {


      // Check if buttons is not an array, try to decode JSON
      if (!is_array($buttons)) {
        $buttons = json_decode($buttons, true);
      }

      // Check if buttons is now an array before processing
      if (is_array($buttons)) {
        // Check for top-level button type
        if (isset($buttons['type']) && strtoupper($buttons['type']) === 'BUTTONS') {
          // Loop through each button and format it as an HTML button
          foreach ($buttons['buttons'] as $button) {
            if (isset($button['type']) && isset($button['text'])) {
              $type = strtoupper($button['type']); // Normalize type to uppercase
              $text = htmlspecialchars($button['text']);

              switch ($type) {
                case 'URL':
                  if (isset($button['url'])) {
                    $url = htmlspecialchars($button['url']);
                    $formatted_html .= '<a href="' . $url . '" class="btn btn-primary" target="_blank">'
                      . $text . '</a> ';
                  }
                  break;

                case 'REPLY':
                  if (isset($button['id'])) {
                    $id = htmlspecialchars($button['id']);
                    $formatted_html .= '<button type="button" class="btn btn-secondary" onclick="handleReply(\'' . $id . '\')">'
                      . $text . '</button> ';
                  }
                  break;

                case 'CATALOG':
                  if (isset($button['catalog_id'])) { // Assuming there's an ID for the catalog
                    $catalog_id = htmlspecialchars($button['catalog_id']);
                    $formatted_html .= '<button type="button" class="btn btn-info" onclick="viewCatalog(\'' . $catalog_id . '\')">'
                      . $text . '</button> ';
                  }
                  break;

                case 'PHONE_NUMBER':
                  if (isset($button['phone_number'])) {
                    $phone_number = htmlspecialchars($button['phone_number']);
                    $formatted_html .= '<button type="button" class="btn btn-success" onclick="callNumber(\'' . $phone_number . '\')">'
                      . $text . '</button> ';
                  }
                  break;

                case 'OTP':
                  if (isset($button['otp_code'])) {
                    $otp_code = htmlspecialchars($button['otp_code']);
                    $formatted_html .= '<button type="button" class="btn btn-warning" onclick="verifyOTP(\'' . $otp_code . '\')">'
                      . $text . '</button> ';
                  }
                  break;


                default:
                  break;
              }
            }
          }
        }
      }
    }
    // Return the formatted HTML buttons
    return $formatted_html;
  }

  public function save_template($data)
  {
    // Prepare the components for the template
    $headerComponent = $this->whatsappLibrary->getHeaderTextComponent($data);
    $bodyComponent = $this->whatsappLibrary->getBodyTextComponent($data);
    $footerComponent = $this->whatsappLibrary->getFooterTextComponent($data);
    $buttonsComponent = $this->whatsappLibrary->getButtonsComponent($data);

    // Send the template to WhatsApp via the API
    $response = $this->whatsappLibrary->createTemplate($data, $headerComponent, $bodyComponent, $footerComponent, $buttonsComponent, $this->whatsappLibrary->getAccountID(), $this->whatsappLibrary->getToken());

    if ($response != 200) {
      return ['success' => false, 'message' => _l('template_creation_failed')];
    }

    // Save template data in the local database
    $this->db->insert(db_prefix() . 'whatsapp_templates', [
      'template_id' => uniqid(),
      'template_name' => $data['template_name'],
      'category' => $data['category'],
      'status' => 'APPROVED', // Assuming newly created templates are approved
      'language' => $data['language'],
      'created_at' => date('Y-m-d H:i:s'),
    ]);

    return ['success' => true, 'message' => _l('template_saved_successfully')];
  }

  /**
   * Get Template
   *
   * Retrieves a template by its ID.
   *
   * @param int $id The template ID.
   * @return object|bool The template object if found, or false if not.
   */
  public function get_template($id)
  {
    $this->db->where('template_id', $id);
    $template = $this->db->get(db_prefix() . 'whatsapp_templates')->row();

    return $template ?: false;
  }

  /**
   * Update Template
   *
   * Updates an existing template in the local database and via the WhatsApp API.
   *
   * @param int $id The ID of the template to update.
   * @param array $data The new template data.
   * @return array Response containing success status and message.
   */
  public function update_template($id, $data)
  {
    // Retrieve the existing template
    $existingTemplate = $this->get_template($id);

    if (!$existingTemplate) {
      return ['success' => false, 'message' => _l('template_not_found')];
    }

    // Prepare the components for the template
    $headerComponent = $this->whatsappLibrary->getHeaderTextComponent($data);
    $bodyComponent = $this->whatsappLibrary->getBodyTextComponent($data);
    $footerComponent = $this->whatsappLibrary->getFooterTextComponent($data);
    $buttonsComponent = $this->whatsappLibrary->getButtonsComponent($data);

    // Update the template via the API
    $response = $this->whatsappLibrary->editTemplate($headerComponent, $bodyComponent, $footerComponent, $buttonsComponent, $existingTemplate->template_id, $this->whatsappLibrary->getToken());

    if (isset($response['error'])) {
      return ['success' => false, 'message' => _l('template_update_failed')];
    }

    // Update template data in the local database
    $this->db->where('template_id', $id);
    $this->db->update(db_prefix() . 'whatsapp_templates', [
      'template_name' => $data['template_name'],
      'category' => $data['category'],
      'status' => $data['status'],
      'language' => $data['language'],
      'updated_at' => date('Y-m-d H:i:s'),
    ]);

    return ['success' => true, 'message' => _l('template_updated_successfully')];
  }

  /**
   * Delete Template
   *
   * Deletes a template from the local database and optionally via the WhatsApp API.
   *
   * @param int $id The ID of the template to delete.
   * @return array Response containing success status and message.
   */
  public function delete_template($id)
  {
    // Retrieve the existing template
    $existingTemplate = $this->get_template($id);

    if (!$existingTemplate) {
      return ['success' => false, 'message' => _l('template_not_found')];
    }

    // Optionally delete the template via the WhatsApp API
    // Implement API delete if supported by WhatsApp API in future.

    // Delete template from local database
    $this->db->where('template_id', $id);
    $this->db->delete(db_prefix() . 'whatsapp_templates');

    return ['success' => true, 'message' => _l('template_deleted_successfully')];
  }
  public function send_campaign()
  {
    $logBatch = [];

    // Fetch scheduled campaign data
    $scheduledData = $this->fetch_scheduled_campaign_data();

    // Check if scheduled data is empty
    if (empty($scheduledData)) {
      return true; // Indicate success even if no campaigns are ready to run
    }

    foreach ($scheduledData as $data) {
      $phone_number = $this->get_phone_number($data['rel_type'], $data['rel_id'], $data);

      if ($phone_number) {
        // Call the function to send the message and log data
        $response = $this->send_whatsapp_message($phone_number, $data, $logBatch);

        // Prepare and collect chat messages if sending was successful
        $this->prepare_chat_message($response, $data, "campaign");
      }

      // Update campaign data regardless of success
      $this->update_campaign_data($data['id'], $response);
    }

    // Insert collected logs and chat messages into the database
    if (!empty($logBatch)) {
      $this->addWhatsbotLog($logBatch);
    }

    return true; // Indicate success
  }

  /**
   * Sends a WhatsApp message using the template data.
   *
   * @param string $phone_number    The recipient's phone number.
   * @param array  $data            The template data array.
   * @param array  &$logBatch       Reference to a log batch array for logging activity.
   * @param string $from_number_id  (Optional) The sender's phone number ID. If not provided, defaults to the default phone number.
   *
   * @return array The response from the WhatsApp library after sending the message.
   */
  public function send_whatsapp_message($phone_number, $data, &$logBatch, $from_number_id = '')
  {
    // Validate input parameters.
    if (empty($phone_number) || empty($data)) {
      return [
        'success' => false,
        'message' => 'Invalid phone number or data provided.'
      ];
    }

    // Prepare the template message data using the WhatsApp library.
    $message_data = $this->whatsappLibrary->prepare_template_message_data($phone_number, $data);
    if (empty($message_data)) {
      return [
        'success' => false,
        'message' => 'Failed to prepare message data.'
      ];
    }

    // Prepare log data for the message sending activity.
    $logdata = $this->prepare_log_data($data, $message_data);

    // Determine the sender's phone number (from_number_id).
    if (empty($from_number_id)) {
      $default_phone = whatsapp_default_phone_number();
      if (empty($default_phone) || empty($default_phone['phone_number_id'])) {
        return [
          'success' => false,
          'message' => 'Default phone number is not configured.'
        ];
      }
      $from_number_id = $default_phone['phone_number_id'];
    }


    $response = $this->whatsappLibrary->send_message(
      $from_number_id,
      $phone_number,
      $message_data,
      $logdata
    );


    // Append log data to the log batch if available.
    if (isset($response['log_data'])) {
      $logBatch[] = $response['log_data'];
    }

    return $response;
  }





  private function update_campaign_data($campaign_id, $response)
  {

    $update_data = [
      'status' => ($response['success'] == true) ? 2 : 0,
      'whatsapp_id' => $response['id'] ?? null,
      'message_status' => "sent",
      'response_message' => $response['log_data']['response_data'] ?? '',
    ];
    $this->db->update(db_prefix() . 'whatsapp_campaign_data', $update_data, ['id' => $campaign_id]);
  }
  private function get_phone_number($relType, $relId, $data)
  {
    if ($relType === 'whatsapp_groups') {
      // Directly fetch the phone number from 'whatsapp_contacts' table for a contact ID associated with the group
      $this->db->select('phonenumber');
      $this->db->where('id', $relId); // Assuming relId is the contact ID in whatsapp_contacts for group
      $rel_data = $this->db->get(db_prefix() . 'whatsapp_contacts')->row();
      $phone_number = $rel_data ? $rel_data->phonenumber : null;
    } else {
      // For 'contacts' or other relationship types, fetch directly from the specified table
      $this->db->select('phonenumber');
      $this->db->where('id', $relId);
      $rel_data = $this->db->get(db_prefix() . $relType)->row();
      $phone_number = $rel_data ? $rel_data->phonenumber : null;
    }

    return $phone_number;
  }


  /**
   * Fetch scheduled campaign data
   */
  private function fetch_scheduled_campaign_data()
  {
    $campaigns_table = db_prefix() . 'whatsapp_campaigns AS wc';
    $templates_table = db_prefix() . 'whatsapp_templates AS wt';
    $campaign_data_table = db_prefix() . 'whatsapp_campaign_data AS wcd';

    // Current timestamp
    $current_time = date('Y-m-d H:i:s');

    // Select the required columns from campaigns, templates, and campaign data
    return $this->db->select('wc.*, wt.*, wcd.*')
      ->from($campaign_data_table)
      ->join($campaigns_table, 'wc.id = wcd.campaign_id', 'left')
      ->join($templates_table, 'wc.template_id = wt.id', 'left')
      ->where('wc.pause_campaign', 0) // Ensure campaign is not paused
      ->where('wcd.status', 1) // Active campaign data
      ->group_start() // Group conditions for send_now or scheduled_send_time
      ->where('wc.send_now', 1) // Send immediately
      ->or_group_start() // Conditions for scheduled send
      ->where('wc.scheduled_send_time IS NOT NULL') // Ensure scheduled time exists
      ->where('wc.scheduled_send_time <=', $current_time) // Only fetch if scheduled time is due
      ->group_end()
      ->group_end()
      ->get()
      ->result_array();
  }

  public function prepare_chat_message($responseData, $data, $type)
  {

    // 1) Decode 'response_data' and 'raw_data'
    $response_data = is_string($responseData['response_data'])
      ? json_decode($responseData['response_data'], true)
      : $responseData['response_data'];

    $raw_data = isset($responseData['log_data']['raw_data'])
      ? json_decode($responseData['log_data']['raw_data'], true)
      : null;

    // 2) Basic fields extraction
    $message_id   = isset($response_data['messages'][0]['id']) ? $response_data['messages'][0]['id'] : null;
    $message_type = $raw_data['type'] ?? 'unknown';

    // 3) Decide how to get the text we store:
    $message_content = '';

    // Retrieve header and footer if interactive, or fallback to $data
    $headerText = '';
    $footerText = '';

    if ($message_type === 'interactive' && isset($raw_data['interactive'])) {
      if (isset($raw_data['interactive']['header']['text'])) {
        $headerText = $raw_data['interactive']['header']['text'];
      } elseif (!empty($data['bot_header'])) {
        $headerText = $data['bot_header'];
      }

      if (isset($raw_data['interactive']['footer']['text'])) {
        $footerText = $raw_data['interactive']['footer']['text'];
      } elseif (!empty($data['bot_footer'])) {
        $footerText = $data['bot_footer'];
      }

      if (isset($raw_data['interactive']['body']['text'])) {
        $message_content = $raw_data['interactive']['body']['text'];
      } else {
        $message_content = $this->prepare_template_message($data, $type);
      }
    } else {
      $headerText = !empty($data['bot_header']) ? $data['bot_header'] : '';
      $footerText = !empty($data['bot_footer']) ? $data['bot_footer'] : '';

      $msgBodyRef = $raw_data[$message_type]['body'] ?? '';
      if (is_array($msgBodyRef) && isset($msgBodyRef['text'])) {
        $message_content = $msgBodyRef['text'];
      } elseif (!empty($msgBodyRef) && !is_array($msgBodyRef)) {
        $message_content = $msgBodyRef;
      } else {
        $message_content = $raw_data[$message_type]['body'] ?? $this->prepare_template_message($data, $type);
      }
    }

    // 4) Combine header, body, and footer into a single text string
    $combinedText = '';
    if ($headerText) {
      $combinedText .= $headerText . "\n";
    }
    $combinedText .= $message_content;
    if ($footerText) {
      $combinedText .= "\n" . $footerText;
    }
    if (is_array($combinedText)) {
      $combinedText = json_encode($combinedText, JSON_UNESCAPED_UNICODE);
    }


    // 5) If media => store basename in "url"
    $file_url = '';
    if (in_array($message_type, ['image', 'video', 'document', 'audio'], true)) {
      $file_url_raw = $raw_data[$message_type]['link'] ?? null;
      if ($file_url_raw) {
        $file_url = basename($file_url_raw);
      }
    }

    // 6) Insert or update the parent interaction
    $interaction_id = $this->whatsapp_interaction_model->insert_interaction([
      'receiver_id'  => $responseData['receiver_no'],
      'last_message' => $combinedText,
      'wa_no'        => $responseData['wa_no'],
      'wa_no_id'     => $responseData['wa_no_id'],
      'time_sent'    => date("Y-m-d H:i:s"),
    ]);

    // 7) Insert the message record
    $this->whatsapp_interaction_model->insert_interaction_message([
      'interaction_id' => $interaction_id,
      'sender_id'      => $responseData['wa_no'],
      'message'        => $combinedText,
      'message_id'     => $message_id,
      'type'           => $message_type,
      'staff_id'       => get_staff_user_id() ?? 0,
      'status'         => $message_id ? 'sent' : 'failed',
      'nature'         => 'sent',
      'url'            => $file_url,
      'time_sent'      => date("Y-m-d H:i:s"),
      'ref_message_id' => $responseData['ref_message_id'] ?? null,
      'status_message' => $response_data['error']['message'] ?? null,
    ]);
  }



  public function prepare_template_message($data, $type = 'campaign')
  {
        log_message('error', "Data received: " . print_r($data, true));
    // Initialize message variable
    $message = '';

    // Ensure the header_data_format and filename keys exist in $data
    $headerDataFormat = $data['header_data_format'] ?? 'TEXT';
    $filename = $data['filename'] ?? '';

    // Determine the base URL for the file
    $baseUrl = ($type === 'message')
      ? base_url('uploads/' . $filename)  // No additional folder
      : base_url(get_upload_path_by_type($type) . '/' . $filename);

    // Handle different header data formats
    switch ($headerDataFormat) {
      case 'IMAGE':
        // Format image header
        if ($filename) {
          $message .= '<a href="' . $baseUrl . '" data-lightbox="image-group">
                                <img src="' . $baseUrl . '" class="img-responsive img-rounded" style="width: 300px" />
                             </a>';
        }
        break;

      case 'DOCUMENT':
        // Format document header
        if ($filename) {
          $message .= '<a href="' . $baseUrl . '" target="_blank">
                                Download Document
                             </a>';
        }
        break;

      case 'VIDEO':
        // Format video header
        if ($filename) {
          $message .= '<video controls style="width: 300px;">
                                <source src="' . $baseUrl . '" type="video/mp4">
                                Your browser does not support the video tag.
                             </video>';
        }
        break;

      case 'TEXT':
      default:
        // Format text header
        $header = $data['header'] ?? '';
        if (isset($data['header'])) $message .= "<span class='tw-mb-3 bold'>" . nl2br(whatsappDecodeWhatsAppSigns($header)) . "</span>";
        break;
    }

    // Parse body and footer
    $body = whatsappParseText($data['rel_type'], 'body', $data);
    $footer = whatsappParseText($data['rel_type'], 'footer', $data);

    // Format button data
    $buttonHtml = $this->format_button_data($data);

    // Combine all parts into the final message
    if ($body) $message .= "<p>" . nl2br(whatsappDecodeWhatsAppSigns($body)) . "</p>";

    // Ensure $footer is a string before using it
    $footerString = is_array($footer) ? implode(" ", $footer) : $footer;

    // Append footer if it's not empty
    if (!empty($footerString)) {
      $message .= "<span class='text-muted tw-text-xs'>" . nl2br(whatsappDecodeWhatsAppSigns($footerString)) . "</span>";
    }

    // Append buttons if there are any
    if (!empty($buttonHtml)) {
      $message .= "<div class='btn-container'>" . $buttonHtml . "</div>";
    }

    return $message; // Return the complete formatted message
  }


  private function prepare_log_data($data, $message_data)
  {
    return [
      'phone_number_id' => whatsapp_default_phone_number()['phone_number_id'],
      'category' => 'Marketing Campaign',
      'category_id' => $data['id'],
      'rel_type' => $data['rel_type'] ?? "",
      'rel_id' => $data['rel_id'] ?? "",
      'recorded_at' => date('Y-m-d H:i:s'),
      'category_params' => json_encode($message_data),
    ];
  }


  public function insert_interaction($data)
  {
    $existing_interaction = $this->db->where('receiver_id', $data['receiver_id'])->where('wa_no', $data['wa_no'])->where('wa_no_id', $data['wa_no_id'])->get(db_prefix() . 'whatsapp_interactions')->row();

    if ($existing_interaction) {
      // Existing interaction found with matching 'receiver_id' and 'wa_no'
      $this->db->where('id', $existing_interaction->id)->update(db_prefix() . 'whatsapp_interactions', $data);

      return $existing_interaction->id;
    }
    // No existing interaction found with matching 'receiver_id' and 'wa_no'
    $this->db->insert(db_prefix() . 'whatsapp_interactions', $data);

    return $this->db->insert_id();
  }

  public function get_numbers()
  {
    $query = $this->db->get(db_prefix() . 'whatsapp_numbers');
    return $query->result_array(); // or $query->result();
  }
  public function chat_received_messages_mark_as_read($id)
  {
    // Retrieve the interaction from the database
    $interaction = $this->db->where('id', $id)
      ->get(db_prefix() . 'whatsapp_interactions')
      ->row();

    if (!$interaction) {
      return false;
    }

    // Retrieve all delivered, received messages for this interaction
    $messages = $this->db->where([
      'interaction_id' => $id,
      'status'         => 'delivered',
      'nature'         => 'received'
    ])->get(db_prefix() . 'whatsapp_interaction_messages')->result();

    if (empty($messages)) {
      return false;
    }

    // Collect message IDs for bulk update
    $message_ids = array_map(function ($message) {
      return $message->id;
    }, $messages);

    // If blue ticks are enabled, mark each message as read via the WhatsApp API
    if (get_option('whatsapp_blueticks_status') === 'enable') {
      foreach ($messages as $message) {
        $this->whatsappLibrary->readmessage($interaction->wa_no_id, $message->message_id);
      }
    }

    // Start a transaction to update messages and interaction unread count atomically
    $this->db->trans_start();

    $this->db->where_in('id', $message_ids)
      ->update(db_prefix() . 'whatsapp_interaction_messages', ['status' => 'read']);

    $this->db->where('id', $id)
      ->update(db_prefix() . 'whatsapp_interactions', ['unread' => 0]);

    $this->db->trans_complete();

    if ($this->db->trans_status() === FALSE) {
      return false;
    }

    return true;
  }

  public function insert_interaction_message($data)
  {
    if (isset($data['message_id'])) {
      $this->db->where('message_id', $data['message_id']);
      if ($this->db->get(db_prefix() . 'whatsapp_interaction_messages')->num_rows() > 0) {
        return false; // Message ID already exists
      }
    }

    $this->db->insert(db_prefix() . 'whatsapp_interaction_messages', $data);
    return $this->db->insert_id();
  }

  public function getContactData($contactNumber, $name)
  {
    $contact = $this->db->get_where(db_prefix() . 'contacts', ['phonenumber' => $contactNumber])->row();
    if ($contact) {
      $contact->rel_type = 'contacts';
      $contact->name = $contact->firstname . ' ' . $contact->lastname;
      return $contact;
    }

    $lead = $this->db->get_where(db_prefix() . 'leads', ['phonenumber' => $contactNumber])->row();
    if ($lead) {
      $lead->rel_type = 'leads';
      return $lead;
    }

    return false;
  }

  public function update_message_status($message_id, $status, $status_message = null)
  {
    // Update message status in whatsapp_interaction_messages
    $this->db->where('message_id', $message_id)
      ->update(db_prefix() . 'whatsapp_interaction_messages', ['status' => $status, 'status_message' => $status_message]);

    // Update message status in whatsapp_campaign_data
    $this->db->where('whatsapp_id', $message_id)
      ->update(db_prefix() . 'whatsapp_campaign_data', ['message_status' => $status]);

    // Fetch interaction_id for the provided message_id
    $interaction = $this->db->select('interaction_id')
      ->where('message_id', $message_id)
      ->get(db_prefix() . 'whatsapp_interaction_messages')
      ->row();

    if ($interaction) {
      // Update the unread count for the interaction
      $this->update_unread_count($interaction->interaction_id);
    }
  }

  public function addWhatsbotLog($logBatch)
  {
    if (!empty($logBatch)) {
      // Insert the log data in batch
      $this->db->insert_batch(db_prefix() . 'whatsapp_activity_log', $logBatch);
    }
  }

  // Define the method to get log details
  public function getWhatsappLogDetails($interaction_id)
  {
    // Fetch interaction details based on interaction_id
    $this->db->where('id', $interaction_id);
    $query = $this->db->get(db_prefix() . 'whatsapp_activity_log');

    // Check if any result is returned
    if ($query->num_rows() > 0) {
      return $query->row();
    } else {
      return null;
    }
  }
  /**
   * Get interaction by ID
   */
  public function get_interaction($id)
  {
    return $this->db->get_where(db_prefix() . 'whatsapp_interactions', ['id' => $id])->row_array();
  }
  public function get_interaction_history($interaction_id)
  {
    // Fetch the interaction details
    $interaction = $this->get_interaction($interaction_id);

    if ($interaction) {
      // Fetch the messages associated with this interaction
      $messages = $this->db->where('interaction_id', $interaction_id)
        ->order_by('time_sent', 'ASC') // Get all messages in ascending order (oldest first)
        ->get(db_prefix() . 'whatsapp_interaction_messages')
        ->result_array();

      // Filter only text messages
      $textMessages = array_filter($messages, function ($message) {
        return $message['type'] === 'text';
      });

      // Assign filtered text messages back to interaction
      $interaction['messages'] = $textMessages;

      // Fetch staff name for each message if available
      foreach ($interaction['messages'] as &$message) {
        if (!empty($message['staff_id'])) {
          $message['staff_name'] = get_staff_full_name($message['staff_id']);
        } else {
          $message['staff_name'] = null;
        }
      }
    }

    // Format messages for OpenAI API
    $formattedMessages = [];
    foreach ($interaction['messages'] as $chatMessage) {
      $formattedMessages[] = [
        'role' => $interaction['wa_no'] === $chatMessage['sender_id'] ? 'assistant' : 'user', // Assuming 'wa_no' is the user identifier
        'content' => $chatMessage['message'] // Assuming 'message_content' contains the message text
      ];
    }

    return $formattedMessages;
  }

  public function get_contacts()
  {
    // Start building the query
    $this->db->select('whatsapp_interactions.*, leads_status.name as lead_status_name, leads.assigned as assigned_staff_id')
      ->from(db_prefix() . 'whatsapp_interactions')
      ->join(db_prefix() . 'leads', db_prefix() . 'leads.id = ' . db_prefix() . 'whatsapp_interactions.type_id', 'left')
      ->join(db_prefix() . 'leads_status', db_prefix() . 'leads_status.id = ' . db_prefix() . 'leads.status', 'left');


    $this->db->order_by('whatsapp_interactions.time_sent', 'DESC');

    // Fetch interactions
    $interactions = $this->db->get()->result_array();


    return $interactions;
  }
  public function get_interactions_count($filters = [])
  {
    // Start building the query
    $this->db->from(db_prefix() . 'whatsapp_interactions')
      ->join(db_prefix() . 'leads', db_prefix() . 'leads.id = ' . db_prefix() . 'whatsapp_interactions.rel_id', 'left')
      ->join(db_prefix() . 'leads_status', db_prefix() . 'leads_status.id = ' . db_prefix() . 'leads.status', 'left');

    // Apply filters
    $this->apply_filters($filters);

    // Return the count
    return $this->db->count_all_results();
  }

  public function get_interactions($filters = [])
  {
    // Start building the query with necessary joins
    $this->db->select('whatsapp_interactions.*, leads_status.name as lead_status_name, leads.assigned as assigned_staff_id')
      ->from(db_prefix() . 'whatsapp_interactions')
      ->join(db_prefix() . 'leads', db_prefix() . 'leads.id = ' . db_prefix() . 'whatsapp_interactions.rel_id', 'left')
      ->join(db_prefix() . 'leads_status', db_prefix() . 'leads_status.id = ' . db_prefix() . 'leads.status', 'left');

    // Apply filters from the provided filters array
    $this->apply_filters($filters);

    // Order by pinned status and then by the last message time in descending order
    $this->db->order_by('whatsapp_interactions.is_pinned', 'DESC'); // Pinned interactions first
    $this->db->order_by('whatsapp_interactions.time_sent', 'DESC'); // Then by last message time


    // Fetch interactions from the database
    $interactions = $this->db->get()->result_array();

    // Process each interaction (e.g., map related information and determine status)
    $this->process_interactions($interactions);

    return $interactions;
  }

  private function apply_filters($filters)
  {
    // Apply filter on WhatsApp number ID
    if (!empty($filters['wa_no_id']) && $filters['wa_no_id'] !== '*') {
      $this->db->where('wa_no_id', $filters['wa_no_id']);
    }

    // Apply filter on interaction type
    if (!empty($filters['interaction_type']) && $filters['interaction_type'] !== '*') {
      $this->db->where('rel_type', $filters['interaction_type']);
    }

    // Apply filter on lead status
    if (!empty($filters['status_id']) && $filters['status_id'] !== '*') {
      $this->db->where('leads.status', $filters['status_id']);
    }

    // Apply filter on assigned staff ID
    if (!empty($filters['assigned_staff_id']) && $filters['assigned_staff_id'] !== '*') {
      $this->db->where('leads.assigned', $filters['assigned_staff_id']);
    }

    // Filter for unread status
    if (isset($filters['status']) && $filters['status'] === 'unread') {
      $this->db->where('unread >', 0);
    }

    // Filter for active and expired interactions
    if (isset($filters['status'])) {
      $now = new DateTime();
      $activeTimeThreshold = $now->sub(new DateInterval('PT24H'))->format('Y-m-d H:i:s');

      if ($filters['status'] === 'active') {
        $this->db->where('last_msg_time >', $activeTimeThreshold);
      } elseif ($filters['status'] === 'expired') {
        $this->db->where('last_msg_time <=', $activeTimeThreshold);
      }
    }

    // Apply search text filter on name, number, and tags in whatsapp_interactions table
    if (!empty($filters['searchtext'])) {
      $this->db->group_start(); // Begin grouping for OR conditions
      $this->db->like('whatsapp_interactions.name', $filters['searchtext']);
      $this->db->or_like('whatsapp_interactions.receiver_id', $filters['searchtext']);
      $this->db->or_like('whatsapp_interactions.tags', $filters['searchtext']);
      $this->db->group_end(); // End grouping for OR conditions
    }
  }



  private function process_interactions(&$interactions)
  {
    foreach ($interactions as &$interaction) {
      $this->map_interaction($interaction);

      // Include lead status and assigned staff name if applicable
      if ($interaction['rel_type'] === 'lead') {
        $interaction['lead_status_name'] = $interaction['lead_status_name'] ?? 'Unknown';
        $interaction['assigned_staff_name'] = get_staff_full_name($interaction['assigned_staff_id']);
      }

      // Determine if the interaction is active or expired based on the last message time
      $interaction['status'] = $this->determine_status($interaction['last_msg_time']);

      // Truncate long messages for preview
      if (isset($interaction['last_message'])) {
        $interaction['last_message'] = $this->truncate_message($interaction['last_message']);
      }
    }
  }

  private function determine_status($lastMsgTime)
  {
    if (!empty($lastMsgTime)) {
      $lastMsgDateTime = new DateTime($lastMsgTime);  // Convert last message time to DateTime object
      $now = new DateTime();  // Get the current time
      $interval = $now->diff($lastMsgDateTime);  // Calculate the time difference

      // Calculate the total number of hours between the last message time and now
      $totalHours = ($interval->days * 24) + $interval->h;  // Total hours including the difference in days

      // If the total hours are less than 24, it's active; otherwise, it's expired
      return ($totalHours < 24) ? 'active' : 'expired';
    }

    return 'expired';  // Default status if no last message time exists
  }

  private function truncate_message($message, $length = 30)
  {
    return mb_strimwidth($message, 0, $length, '...');
  }

  private function map_interaction(&$interaction)
  {
    if (
      is_null($interaction['rel_type']) || is_null($interaction['rel_id']) ||
      in_array($interaction['rel_type'], ['general', 'contact', 'lead', 'customer'])
    ) {
      $this->auto_map_interaction($interaction);
    }
  }

  private function auto_map_interaction(&$interaction)
  {
    // Try to map to an existing entity in leads, contacts, or staff
    $entityId = $this->map_interaction_entity($interaction, 'leads', 'id', 'leads')
      ?? $this->map_interaction_entity($interaction, 'contacts', 'id', 'contacts')
      ?? $this->map_interaction_entity($interaction, 'staff', 'staffid', 'staffs');

    // If no existing entity was found, create a new lead if the option is enabled
    $type = null;
    if (!$entityId && get_option('whatsapp_auto_lead_settings') === 'enable') {
      $entityId = $this->create_lead_for_interaction($interaction);
      $type = 'leads';
    } elseif (!$entityId) {
      // If no match and auto lead creation is disabled, default to 'general'
      $type = 'general';
    }

    // Determine the type based on the mapping result
    $type = $type ?? ($entityId ? 'contacts' : 'general'); // Use 'contact' if found in contacts or staff

    // Update the interaction record with the mapped entity type and ID
    $data = [
      'rel_type' => $type,
      'rel_id' => $entityId ?? null,
      'wa_no' => $interaction['wa_no'] ?? whatsapp_default_phone_number()['phone_number'],
      'receiver_id' => $interaction['receiver_id'],
    ];

    $this->db->where('id', $interaction['id'])->update(db_prefix() . 'whatsapp_interactions', $data);
  }

  private function map_interaction_entity($interaction, $table, $column, $type)
  {
    // Adjust phone number format if necessary before lookup
    $formattedPhone = preg_replace('/\D/', '', $interaction['receiver_id']); // Remove non-numeric characters

    $entity = $this->db->where('REPLACE(REPLACE(phonenumber, " ", ""), "+", "") =', $formattedPhone)
      ->get(db_prefix() . $table)
      ->row();

    return $entity ? $entity->$column : null;
  }

  private function create_lead_for_interaction($interaction)
  {
    // Prepare data for the new lead
    $lead_data = [
      'phonenumber' => $interaction['receiver_id'],
      'name'        => $interaction['name'],
      'status'      => get_option('whatsapp_lead_status'),
      'source'      => get_option('whatsapp_lead_source'),
      'assigned'    => get_option('whatsapp_lead_assigned'),
      'dateadded'   => date('Y-m-d H:i:s'),
    ];

    // Insert the new lead and return its ID
    get_instance()->load->model('leads_model');
    return get_instance()->leads_model->add($lead_data);
  }


  private function get_asset_url($url)
  {
    if ($url && strpos($url, '/') === false) {
      return WHATSAPP_MODULE_UPLOAD_URL . $url;
    }
    return $url ?? null;
  }


  public function get_interaction_messages($interaction_id, $limit = 20, $offset = 0)
  {
    // Retrieve the total number of messages for the interaction
    $total_records = $this->db->where('interaction_id', $interaction_id)
      ->count_all_results(db_prefix() . 'whatsapp_interaction_messages');

    // Retrieve the most recent messages with limit and offset for pagination
    $messages = $this->db->where('interaction_id', $interaction_id)
      ->order_by('time_sent', 'DESC') // Get the most recent messages first
      ->limit($limit, $offset)
      ->get(db_prefix() . 'whatsapp_interaction_messages')
      ->result_array();

    // Reverse the order to make it ascending (oldest at the top, most recent at the bottom)
    $messages = array_reverse($messages);

    // Process messages if needed
    $this->process_messages($messages);

    // Calculate if there are more records beyond the current set
    $hasMoreRecords = ($offset + count($messages)) < $total_records;

    // Return messages along with pagination info
    return [
      'messages' => $messages,
      'total_records' => $total_records,
      'hasMoreMessages' => $hasMoreRecords,
      'limit' => $limit,
      'offset' => $offset
    ];
  }




  private function process_messages(&$messages)
  {
    foreach ($messages as &$message) {
      $message['staff_name'] = !empty($message['staff_id']) ? get_staff_full_name($message['staff_id']) : null;
      $message['asset_url'] = $this->get_asset_url($message['url']);
    }
  }
  // Get the current menu state by interaction ID
  public function get_menu_state($interaction_id)
  {
    return $this->db->select('menu_path, bot_id')
      ->from(db_prefix() . 'interaction_menu_state')
      ->where('interaction_id', $interaction_id)
      ->get()
      ->row_array();
  }

  // Create a new menu state record with bot_id
  public function create_menu_state($interaction_id, $menu_path, $bot_id)
  {
    $this->db->insert(db_prefix() . 'interaction_menu_state', [
      'interaction_id' => $interaction_id,
      'menu_path' => $menu_path,
      'bot_id' => $bot_id,
      'created_at' => date('Y-m-d H:i:s'),
    ]);
  }

  // Update an existing menu state record with bot_id
  public function update_menu_state($interaction_id, $menu_path, $bot_id)
  {
    $this->db->where('interaction_id', $interaction_id)
      ->update(db_prefix() . 'interaction_menu_state', [
        'menu_path' => $menu_path,
        'bot_id' => $bot_id,
        'updated_at' => date('Y-m-d H:i:s'),
      ]);
  }

  // Clear the menu state for an interaction (called when the user exits the menu)
  public function clear_menu_state($interaction_id)
  {
    $this->db->where('interaction_id', $interaction_id)
      ->delete(db_prefix() . 'interaction_menu_state');
  }
  public function delete_interaction($interaction_id)
  {
    // Start a database transaction
    $this->db->trans_start();

    // Fetch the interaction details to ensure it exists
    $interaction = $this->get_interaction($interaction_id);

    if ($interaction) {
      // Delete messages associated with the interaction
      $this->db->delete(db_prefix() . 'whatsapp_interaction_messages', ['interaction_id' => $interaction_id]);

      // Delete the interaction itself
      $this->db->delete(db_prefix() . 'whatsapp_interactions', ['id' => $interaction_id]);

      // Complete the transaction
      $this->db->trans_complete();

      // Check if the transaction was successful
      if ($this->db->trans_status() === FALSE) {
        return [
          'success' => false,
          'message' => 'Failed to delete interaction. Please try again.'
        ];
      }

      return [
        'success' => true,
        'message' => 'Interaction deleted successfully.'
      ];
    }

    return [
      'success' => false,
      'message' => 'Interaction not found.'
    ];
  }
  public function update_unread_count($interaction_id)
  {
    // Count the number of unread messages for the interaction
    $unread_count = $this->db->where([
      'interaction_id' => $interaction_id,
      'status' => 'delivered',
      'nature' => 'received'
    ])
      ->count_all_results(db_prefix() . 'whatsapp_interaction_messages');

    // Update the unread count in whatsapp_interactions
    $this->db->where('id', $interaction_id)
      ->update(db_prefix() . 'whatsapp_interactions', ['unread' => $unread_count]);
    return true;
  }
  public function updatePinnedState($chatId, $isPinned)
  {
    // Update the record in the database
    $this->db->where('id', $chatId);
    $this->db->update(db_prefix() . 'whatsapp_interactions', ['is_pinned' => $isPinned]); // Replace 'whatsapp_chats' with your actual table name

    // Check if the update was successful
    return $this->db->affected_rows() > 0;
  }
}
