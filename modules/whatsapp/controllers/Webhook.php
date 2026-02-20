<?php defined('BASEPATH') || exit('No direct script access allowed'); ?>

<?php
use WpOrg\Requests\Requests as WhatsappRequests;
use Pusher\Pusher; // Ensure you have installed the Pusher PHP library via Composer

class Webhook extends ClientsController
{
    public $is_first_time = false;
    private $interaction_menu_state_table = 'interaction_menu_state'; // Table for menu state tracking

    public function __construct()
    {
        parent::__construct();
        $this->load->model(['whatsapp_interaction_model', 'bots_model']);
        $this->load->library('WhatsappLibrary');
        $this->WhatsappLibrary = new WhatsappLibrary();
    }

    /* ================= Pusher Event Trigger ================= */

    /**
     * Triggers a Pusher event on the 'whatsapp-channel'.
     *
     * @param string $event The event name.
     * @param array  $data  The data payload to send.
     */
    private function triggerPusherEvent($event, $data)
    {
        $options = [
            'cluster' => get_option('pusher_cluster'),
            'useTLS'  => true,
        ];
        $pusher = new Pusher(
            get_option('pusher_app_key'),
            get_option('pusher_app_secret'),
            get_option('pusher_app_id'),
            $options
        );
        $pusher->trigger('whatsapp-channel', $event, $data);
    }

    /* ================= Campaign and Verification Methods ================= */

    public function send_campaign()
    {
        $CI = get_instance();
        $CI->load->model(WHATSAPP_MODULE . '/whatsapp_interaction_model');

        // Send campaign regardless of the template update schedule
        $result = $CI->whatsapp_interaction_model->send_campaign();

        // Current timestamp for both stamps
        $currentTime = date('Y-m-d H:i:s');
        $currentTimestamp = strtotime($currentTime);

        // Update the campaign timestamp every time the campaign is sent
        update_option('whatsapp_campaign_cronjob_run_at', $currentTime);

        // Get the last template update timestamp
        $lastTemplatesRunAt = get_option('whatsapp_templates_cronjob_run_at');
        $lastTemplatesTimestamp = $lastTemplatesRunAt ? strtotime($lastTemplatesRunAt) : 0;

        // Check if an hour (3600 seconds) has passed since the last template update
        if (($currentTimestamp - $lastTemplatesTimestamp) >= 3600) {
            // Load templates only once an hour
            $CI->whatsapp_interaction_model->load_templates();
            update_option('whatsapp_templates_cronjob_run_at', $currentTime);
        }

        echo json_encode([
            'success' => (bool)$result,
            'message' => $result ? 'Campaign sent successfully.' : 'Failed to send campaign.'
        ]);
    }

    /**
     * Main method: The webhook endpoint that receives WhatsApp callbacks.
     */
    public function getdata()
    {
        if ($this->isVerificationRequest()) {
            $this->handleVerificationRequest();
        } else {
            $this->processWebhookData();
        }
    }

    private function isVerificationRequest(): bool
    {
        return isset($_GET['hub_mode'], $_GET['hub_challenge'], $_GET['hub_verify_token']);
    }

    /**
     * Handle the GET-based Facebook/Meta verification request.
     */
    private function handleVerificationRequest()
    {
        if ($_GET['hub_verify_token'] === get_option('whatsapp_webhook_token')) {
            echo $_GET['hub_challenge'];
            http_response_code(200);
        } else {
            http_response_code(403);
        }
        exit();
    }

    /* ================= Webhook Processing Methods ================= */

    /**
     * The main body for handling POSTed JSON from the WhatsApp webhook.
     */
    private function processWebhookData()
    {
        $feedData = file_get_contents('php://input');
        if (!$feedData) {
            http_response_code(400);
            exit();
        }
        $payload = json_decode($feedData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            exit();
        }
        log_message('error', 'Payload received: ' . print_r($feedData, true));

        // Optionally forward to another webhook
        $this->forwardWebhookData($payload);

        // Handle the main data
        $this->handleData($payload);

        // If there are additional changes in the payload
        collect($payload['entry'] ?? [])
            ->pluck('changes')
            ->flatten(1)
            ->each(function ($change) {
                if (!empty($change['field']) && method_exists($this, $change['field'])) {
                    $this->{$change['field']}($change['value']);
                }
            });

        http_response_code(200);
        exit();
    }

    /**
     * Optionally forward the received webhook payload to another URL (if enabled).
     */
    private function forwardWebhookData(array $payload)
    {
        if (get_option('enable_webhooks') !== '1') {
            return;
        }
        $webhookUrl = get_option('webhooks_url');
        if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            log_message('error', 'Invalid webhook URL');
            return;
        }
        $maxRetries = 3;
        $timeout = 10;
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            try {
                $response = WhatsappRequests::request(
                    $webhookUrl,
                    [],
                    json_encode($payload),
                    'POST',
                    ['timeout' => $timeout]
                );
                update_option('whatsapp_webhook_code', $response->status_code);
                update_option('whatsapp_webhook_data', htmlentities($response->body));

                if ($response->status_code >= 200 && $response->status_code < 300) {
                    break;
                }
            } catch (Exception $e) {
                update_option('whatsapp_webhook_code', 'EXCEPTION');
                update_option('whatsapp_webhook_data', $e->getMessage());
                log_message('error', 'Webhook forwarding failed: ' . $e->getMessage());
            }
            sleep(2);
        }
    }

    /**
     * The main logic for parsing the relevant changes from the payload.
     */
    private function handleData($payload)
    {
        if (empty($payload['entry']) || !is_array($payload['entry'])) {
            $this->sendErrorResponse('Invalid payload structure', $payload);
            return;
        }
        $entry = array_shift($payload['entry']);
        if (empty($entry['changes']) || !is_array($entry['changes'])) {
            $this->sendErrorResponse('Invalid changes structure', $payload);
            return;
        }
        $changes = array_shift($entry['changes']);
        $value   = $changes['value'] ?? null;

        if (empty($value)) {
            $this->sendErrorResponse('Invalid value structure', $payload);
            return;
        }

        if ($changes['field'] === 'business_capability_update') {
            update_option('max_daily_conversation_per_phone', $value['max_daily_conversation_per_phone']);
            update_option('max_phone_numbers_per_business', $value['max_phone_numbers_per_business']);
        } elseif (isset($value['messages']) && is_array($value['messages'])) {
            $this->processMessages($value);
        } elseif (isset($value['statuses']) && is_array($value['statuses'])) {
            $this->processStatuses($value);
        } else {
            $this->sendErrorResponse('Invalid payload structure', $payload);
        }
    }

    /**
     * Process "statuses" changes from the webhook.
     */
    private function processStatuses($value)
    {
        if (!empty($value['statuses'])) {
            $statusEntry = array_shift($value['statuses']);
            $id          = $statusEntry['id'];
            $status      = $statusEntry['status'];
            $status_message = '';

            if (isset($statusEntry['errors']) && !empty($statusEntry['errors'])) {
                foreach ($statusEntry['errors'] as $error) {
                    $status_message .= "Error Code: {$error['code']}<br>"
                                     . "Title: {$error['title']}<br>"
                                     . "Message: {$error['message']}<br>"
                                     . "Details: {$error['error_data']['details']}<br>";
                }
            }
            // Update the DB record
            $this->whatsapp_interaction_model->update_message_status($id, $status, $status_message);

            // Trigger a Pusher event for status update
            $this->triggerPusherEvent('status_update', [
                'id' => $id,
                'status' => $status,
                'status_message' => $status_message
            ]);
        } else {
            log_message('error', "No statuses found in the payload.");
        }
    }

    /**
     * Process "messages" changes from the webhook.
     */
    private function processMessages($value)
    {
        $messageEntry = array_shift($value['messages']);
        $contact      = array_shift($value['contacts']);
        $metadata     = $value['metadata'];

        if (empty($messageEntry) || empty($contact) || empty($metadata)) {
            $this->sendErrorResponse('Missing required message or contact data', $value);
            return;
        }

        // Insert into interactions + messages
        $interaction_id = $this->saveInteractionAndMessage($messageEntry, $contact, $metadata, $value);

        // Extract user’s text for triggers
        $trigger_msg = $messageEntry['text']['body']
            ?? $messageEntry['interactive']['button_reply']['id']
            ?? "";

        // Process your bots
        $this->processBots($interaction_id, $trigger_msg, $messageEntry, $contact, $metadata);

        // AI auto-reply (if enabled)
        if ($this->isAIReplyEnabled() && $messageEntry['type'] === "text") {
            $this->aiMessageReply($interaction_id, $messageEntry['text']['body']);
        }
        
        // Trigger a Pusher event for message update
        $this->triggerPusherEvent('message_update', [
            'interaction_id' => $interaction_id,
            'message' => $messageEntry,
        ]);
        
        http_response_code(200);
    }

    /* ================= Interaction & Message Helpers ================= */

    /**
     * If AI is enabled, generate an AI-based reply to user’s message.
     */
    private function aiMessageReply(int $interaction_id, $usermessage)
    {
        // E.g. call your AI logic
        $ai_reply_content = getAIReply($interaction_id, $usermessage, null);

        $interaction = $this->db
            ->where('id', $interaction_id)
            ->get(db_prefix() . 'whatsapp_interactions')
            ->row();

        $response_data = $this->WhatsappLibrary->send_message($interaction->wa_no, [
            [
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body'        => $ai_reply_content,
                ],
            ],
        ]);

        if (!isset($response_data['response_data'])) {
            $response_data['response_data'] = [];
        }
        $message_id = $response_data['messages'][0]['id'] ?? null;
        if (is_null($message_id)) {
            log_message('error', "Failed to send message via WhatsApp for interaction ID: $interaction_id");
        }

        // Insert record
        $this->whatsapp_interaction_model->insert_interaction_message([
            'interaction_id' => $interaction_id,
            'sender_id'      => $interaction->wa_no_id,
            'message_id'     => $message_id,
            'message'        => $ai_reply_content,
            'type'           => 'text',
            'staff_id'       => get_staff_user_id() ?? null,
            'status'         => 'sent',
            'time_sent'      => date("Y-m-d H:i:s"),
        ]);
    }

    /**
     * Saves the interaction and the incoming message.
     */
    private function saveInteractionAndMessage(array $messageEntry, array $contact, array $metadata, array $fullPayload): int
    {
        $receiverId  = $messageEntry['from'];
        $timestamp   = $this->formatTimestamp($messageEntry['timestamp']);
        $interaction = $this->whatsapp_interaction_model->get_interaction(null, [
            'receiver_id' => $receiverId,
            'wa_no'       => $metadata['display_phone_number'],
            'wa_no_id'    => $metadata['phone_number_id'],
        ]);
        $this->is_first_time = !$interaction;

        // Extract the message data
        $messageData = $this->extractMessage($messageEntry['type'], $messageEntry);

        // Insert or update the interaction record
        $interactionData = [
            'receiver_id'   => $receiverId,
            'name'          => $contact['profile']['name'],
            'wa_no'         => $metadata['display_phone_number'],
            'wa_no_id'      => $metadata['phone_number_id'],
            'last_message'  => $messageData['message'] ?? $messageEntry['type'],
            'time_sent'     => $timestamp,
            'last_msg_time' => $timestamp,
        ];
        $interactionId = $this->whatsapp_interaction_model->insert_interaction($interactionData);
        
         $messageInsertData = [
            'interaction_id' => $interactionId,
            'sender_id' => $receiverId,
            'message_id' => $messageEntry['id'],
            'ref_message_id' => $messageEntry['context']['id'] ?? $messageEntry['reaction']['message_id'] ?? null,
            'message' => $messageData['message'] ?? $messageEntry['type'],
            'type' => $messageEntry['type'],
            'staff_id' => get_staff_user_id() ?? null,
            'url' => $messageData['url'] ?? null,
            'status' => 'delivered',
            'nature' => 'received',
            'payload' => json_encode($messageEntry, true),
            'time_sent' => $timestamp,
        ];
        
        $this->whatsapp_interaction_model->insert_interaction_message($messageInsertData);
        $this->whatsapp_interaction_model->update_unread_count($interactionId);

        return $interactionId;
    }

    private function formatTimestamp($timestamp): string
    {
        return date("Y-m-d H:i:s", $timestamp);
    }

    /**
     * Extracts a user-friendly message from different WhatsApp message types.
     */
    private function extractMessage($type, $messageEntry)
    {
        $data = [
            'message' => '',
            'url'     => null,
        ];
        switch ($type) {
            case 'text':
                $data['message'] = $messageEntry['text']['body'] ?? 'No text found';
                break;
            case 'image':
            case 'video':
            case 'document':
            case 'audio':
                $data = $this->extractMediaMessage($type, $messageEntry);
                break;
            case 'location':
                $lat  = $messageEntry['location']['latitude'];
                $long = $messageEntry['location']['longitude'];
                $name = $messageEntry['location']['name'] ?? '';
                $data['message'] = "Location received: Lat: {$lat}, Long: {$long} ({$name})";
                break;
            case 'contacts':
                $formattedName = $messageEntry['contacts'][0]['name']['formatted_name'] ?? 'Unknown';
                $data['message'] = "Contact received: " . $formattedName;
                break;
            case 'interactive':
                $data['message'] = $this->processInteractiveMessage($messageEntry);
                break;
            case 'reaction':
                $data['message'] = $messageEntry['reaction']['emoji'] ?? '';
                break;
            default:
                $data['message'] = $type; // fallback
                break;
        }
        return $data;
    }

    /**
     * Extracts media details from image/video/document/audio message.
     */
    private function extractMediaMessage($type, $messageEntry)
    {
        $data = [
            'message' => ucfirst($type) . ' received',
            'url'     => $messageEntry[$type]['link'] ?? null,
        ];
        if (isset($messageEntry[$type]['id'])) {
            $media_id     = $messageEntry[$type]['id'];
            $caption      = $messageEntry[$type]['caption'] ?? null;
            $access_token = get_option('whatsapp_access_token');
            $attachment   = $this->WhatsappLibrary->retrieveUrl($media_id, $access_token);

            $data['message'] .= $caption ? " - $caption" : '';
            $data['url']      = $attachment ?: $data['url'];
        }
        return $data;
    }

    /**
     * Processes interactive (button_reply / list_reply) messages.
     */
    private function processInteractiveMessage($messageEntry)
    {
        $interactive = $messageEntry['interactive'] ?? [];
        if (isset($interactive['button_reply'])) {
            return 'Button clicked: ' . ($interactive['button_reply']['title'] ?? 'Unknown button');
        } elseif (isset($interactive['list_reply'])) {
            return 'List option selected: ' . ($interactive['list_reply']['title'] ?? 'Unknown option');
        }
        return 'Unknown interactive response';
    }

    /**
     * Check if AI auto-reply is enabled in settings.
     */
    private function isAIReplyEnabled(): bool
    {
        return get_option('whatsapp_openai_token')
            && get_option('whatsapp_openai_status') === "auto";
    }

    /* ================= Bot Processing ================= */

    /**
     * Finds all matching bots for this interaction, then processes them.
     */
    private function processBots($interaction_id, $trigger_msg, $messageEntry, $contact, $metadata)
    {
        $bots = whatsapp_bots($interaction_id);
        log_message('error', 'Bots: ' . print_r($bots, true));

        if (empty($bots)) {
            return;
        }
        $logBatch = [];

        foreach ($bots as $bot) {
            $this->processBot($bot, $interaction_id, $trigger_msg, $messageEntry, $contact, $metadata, $logBatch);
        }

        if (!empty($logBatch)) {
            $this->addWhatsbotLog($logBatch);
        }
    }

    /**
     * For each Bot, check if it triggers, then handle logic based on bot type.
     */
    private function processBot($bot, $interaction_id, $trigger_msg, $messageEntry, $contact, $metadata, &$logBatch)
    {
        $session_timeout = 86400;
        if ($bot['reply_type'] == 7 && is_numeric($trigger_msg)) {
            $session_timeout = (int)$trigger_msg * 3600;
        }

        $interaction = $this->whatsapp_interaction_model->get_interaction($interaction_id);
        if (!$interaction) {
            return;
        }

        $is_first_message_in_session = $this->isSessionExpired($interaction['time_sent'], $session_timeout);
        $is_first_time = isset($messageEntry['type']) && $messageEntry['type'] === 'request_welcome';

        if (!$this->shouldTriggerBot($bot, $trigger_msg, $is_first_message_in_session, $is_first_time)) {
            return;
        }

        $log_data = $this->prepareBotLogData($bot, $metadata);
        $receiver = $messageEntry['from'];

        $message_data = null;
        switch ($bot['bot_type']) {
            case 4:   // Flow Bot.
                $message_data = $this->processFlowBot($bot, $metadata, $receiver);
                break;

            case 12:  // AI Bot.
                $ai_reply_content = getAIReply($interaction_id, $trigger_msg, null);
                $message_data = [
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body'        => $ai_reply_content,
                    ],
                ];
                break;

            case 13:  // Review Bot.
                $message_data = $this->prepareReviewMessage($bot, $receiver);
                break;

            default:
                $message_data = $this->prepareMessageData($bot, $receiver);
                if (!$message_data) {
                    log_message('error', 'Bot type not supported or no message data. Bot type: ' . $bot['bot_type']);
                    return;
                }
                break;
        }

        $response = $this->WhatsappLibrary->send_message($metadata['phone_number_id'], $receiver, $message_data, $log_data);

        $this->whatsapp_interaction_model->prepare_chat_message($response, $bot, "bots");

        $this->bots_model->update_sending_count($bot);

        if (!isset($response['log_data'])) {
            $response['log_data'] = [];
        }
        $logBatch[] = $response['log_data'];
    }

    /**
     * Prepare a message for standard bot types.
     */
    private function prepareMessageData($bot, $contact_number)
    {
        switch ($bot['bot_type']) {
            case 1: // Basic "Message Bot"
                return $this->prepareTextMessage($bot, $contact_number);

            case 2: // Template Bot
                return $this->WhatsappLibrary->prepare_template_message_data($contact_number, $bot);

            case 3: // Menu Bot
                return $this->prepareMenuMessage($bot, $contact_number);

            case 5: // Media Bot
                return $this->prepareMediaMessage($bot, $contact_number);

            case 6: // Location Bot
                return $this->prepareLocationMessage($bot, $contact_number);

            case 7: // Interactive Buttons Bot
                return $this->prepareInteractiveButtonsMessage($bot, $contact_number);

            case 8: // List Bot
                return $this->prepareListMessage($bot, $contact_number);

            case 9: // Quick Reply Bot
                return $this->prepareQuickReplyMessage($bot, $contact_number);

            case 10: // Sticker Bot
                return $this->prepareStickerMessage($bot, $contact_number);

            case 11: // Contact Bot
                return $this->prepareContactMessage($bot, $contact_number);

            default:
                log_message('error', 'Unsupported bot type in prepareMessageData: ' . $bot['bot_type']);
                return null;
        }
    }

    /* ========== Example placeholders for each specialized message ========== */

    private function prepareTextMessage($bot, $contact_number)
    {
        $text = $bot['reply_text'] ?? 'Hello from Message Bot!';
        return [
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $text,
            ],
        ];
    }

    private function prepareMenuMessage($bot, $contact_number)
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => 'Menu Bot: [List of items here...]',
                'preview_url' => false,
            ],
        ];
    }

    private function prepareMediaMessage($bot, $contact_number)
    {
        $mediaType = $bot['media_type'] ?? 'image';
        $mediaUrl  = $bot['media_url']  ?? 'https://example.com/sample.jpg';
        return [
            'type' => $mediaType,
            $mediaType => [
                'link' => $mediaUrl,
                'caption' => $bot['caption'] ?? '',
            ],
        ];
    }

    private function prepareLocationMessage($bot, $contact_number)
    {
        return [
            'type' => 'location',
            'location' => [
                'latitude' => $bot['latitude']  ?? '0',
                'longitude' => $bot['longitude'] ?? '0',
                'name' => $bot['location_name'] ?? 'Unknown',
                'address' => $bot['location_address'] ?? 'Unknown Address',
            ],
        ];
    }

    private function prepareInteractiveButtonsMessage(array $bot, string $contact_number): array
    {
        $buttons = [];
        for ($i = 1; $i <= 3; $i++) {
            $nameKey  = "btn{$i}_name";
            $typeKey  = "btn{$i}_type";
            $linkKey  = "btn{$i}_link";
            $phoneKey = "btn{$i}_number";

            if (empty($bot[$nameKey])) {
                continue;
            }

            $buttonName = $bot[$nameKey];
            $buttonType = strtolower($bot[$typeKey] ?? 'reply');

            switch ($buttonType) {
                case 'reply':
                    $buttons[] = [
                        'type' => 'reply',
                        'reply' => [
                            'id'    => 'btn_' . $i . '_' . uniqid(),
                            'title' => $buttonName,
                        ],
                    ];
                    break;

                case 'cta_url':
                    if (!empty($bot[$linkKey])) {
                        $buttons[] = [
                            'type' => 'cta_url',
                            'cta_url'  => [
                                'link'         => $bot[$linkKey],
                                'display_text' => $buttonName,
                            ],
                        ];
                    }
                    break;

                case 'phone':
                    if (!empty($bot[$phoneKey])) {
                        $buttons[] = [
                            'type' => 'phone_number',
                            'phone_number' => [
                                'phone_number' => $bot[$phoneKey],
                                'display_text' => $buttonName,
                            ],
                        ];
                    }
                    break;

                default:
                    break;
            }
        }

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'header' => [
                    'type' => 'text',
                    'text' => $bot['bot_header'] ?? 'Header Header',
                ],
                'body' => [
                    'text' => $bot['reply_text'] ?? 'Choose an option:',
                ],
                'footer' => [
                    'text' => $bot['bot_footer'] ?? '',
                ],
                'action' => [
                    'buttons' => $buttons,
                ],
            ],
        ];
    }

    private function prepareListMessage($bot, $contact_number)
    {
        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'header' => [
                    'type' => 'text',
                    'text' => $bot['bot_header'] ?? 'List Header',
                ],
                'body' => [
                    'text' => $bot['reply_text'] ?? 'Choose an option:',
                ],
                'footer' => [
                    'text' => $bot['bot_footer'] ?? '',
                ],
                'action' => [
                    'button' => $bot['list_button'] ?? 'Show Items',
                    'sections' => [
                        [
                            'title' => 'Section 1',
                            'rows' => [
                                [ 'id' => 'item1', 'title' => 'Item 1', 'description' => 'Description 1' ],
                                [ 'id' => 'item2', 'title' => 'Item 2', 'description' => 'Description 2' ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function prepareQuickReplyMessage($bot, $contact_number)
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => 'Quick Reply Bot: type your choice...',
                'preview_url' => false,
            ],
        ];
    }

    private function prepareStickerMessage($bot, $contact_number)
    {
        return [
            'type' => 'sticker',
            'sticker' => [
                'link' => $bot['sticker_url'] ?? 'https://example.com/sticker.webp',
            ],
        ];
    }

    private function prepareContactMessage($bot, $contact_number)
    {
        return [
            'type' => 'contacts',
            'contacts' => [
                [
                    'name' => [
                        'formatted_name' => $bot['contact_name'] ?? 'John Doe',
                        'first_name' => $bot['contact_first_name'] ?? 'John',
                        'last_name' => $bot['contact_last_name'] ?? 'Doe',
                    ],
                    'phones' => [
                        [ 'phone' => $bot['contact_number'] ?? '+1234567890' ],
                    ],
                    'emails' => [
                        [ 'email' => $bot['contact_email'] ?? 'john@example.com' ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Check if the last interaction was older than $session_timeout => session expired.
     */
    private function isSessionExpired($last_interaction_time, $session_timeout): bool
    {
        if (!$last_interaction_time) {
            return true;
        }
        $current_time = time();
        $time_since_last_interaction = $current_time - strtotime($last_interaction_time);
        return $time_since_last_interaction > $session_timeout;
    }

    /**
     * Flow Bot: runs the entire flow ignoring condition nodes, from Start => End.
     */
    private function processFlowBot($bot, $metadata, $receiver)
    {
        if (empty($bot['flow_data'])) {
            log_message('error', 'Flow data is empty for Flow Bot.');
            return ['response_data' => ['error' => ['message' => 'Flow data is missing']]];
        }
        $flow = json_decode($bot['flow_data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message('error', 'Invalid flow data JSON for Flow Bot.');
            return ['response_data' => ['error' => ['message' => 'Invalid flow data JSON']]];
        }

        // Build node + adjacency
        $nodes = [];
        foreach ($flow['nodes'] as $node) {
            $nodes[$node['id']] = $node;
        }
        $adjacency = [];
        foreach ($flow['connections'] as $conn) {
            $from = $conn['from'];
            $to   = $conn['to'];
            if (!isset($adjacency[$from])) {
                $adjacency[$from] = [];
            }
            $adjacency[$from][] = $to;
        }

        // Find the Start node
        $startNodeId = null;
        foreach ($nodes as $nodeId => $n) {
            if ($n['type'] === 'start') {
                $startNodeId = $nodeId;
                break;
            }
        }
        if (!$startNodeId) {
            log_message('error', 'No start node found in flow data.');
            return ['response_data' => ['error' => ['message' => 'No start node in flow data']]];
        }
        if (empty($adjacency[$startNodeId])) {
            log_message('error', 'No next node from the start node.');
            return ['response_data' => ['error' => ['message' => 'No adjacency from start node']]];
        }

        // The first child of Start is our entry point
        $currentNodeId = $adjacency[$startNodeId][0];
        $response = null;

        // Traverse
        while ($currentNodeId) {
            if (!isset($nodes[$currentNodeId])) {
                log_message('error', "Flow node $currentNodeId not found; possibly removed.");
                break;
            }
            $currentNode = $nodes[$currentNodeId];
            $nodeType    = $currentNode['type'];

            // skip condition
            if ($nodeType === 'condition') {
                log_message('error', "Skipping condition node: $currentNodeId");
                if (!empty($adjacency[$currentNodeId])) {
                    $currentNodeId = $adjacency[$currentNodeId][0];
                    continue;
                } else {
                    break;
                }
            }

            // If end => final message
            if ($nodeType === 'end') {
                $endText = $currentNode['data']['message'] ?? 'Flow ended.';
                $msg_data = [
                    'type' => 'text',
                    'text' => [
                        'body' => "End: " . $endText,
                        'preview_url' => false,
                    ],
                ];
                $response = $this->WhatsappLibrary->send_message($metadata['phone_number_id'], $receiver, $msg_data);
                $this->whatsapp_interaction_model->prepare_chat_message($response, $bot, "bots");
                break;
            }

            // otherwise => build + send
            $msg_data = $this->buildFlowMessageData($nodeType, $currentNode['data']);
            if ($msg_data) {
                $response = $this->WhatsappLibrary->send_message($metadata['phone_number_id'], $receiver, $msg_data);
                $this->whatsapp_interaction_model->prepare_chat_message($response, $bot, "bots");
            }

            // optional delay between nodes
            sleep(2);

            // next adjacency
            if (!empty($adjacency[$currentNodeId])) {
                $currentNodeId = $adjacency[$currentNodeId][0];
            } else {
                break;
            }
        }

        return $response ?: [];
    }

    /**
     * Build the message data for each node type in the flow (text, image, etc.).
     */
    private function buildFlowMessageData(string $nodeType, array $nodeData): ?array
    {
        switch ($nodeType) {
            case 'text':
                return [
                    'type' => 'text',
                    'text' => [
                        'body' => $nodeData['text'] ?? '(no text)',
                        'preview_url' => false,
                    ],
                ];
            case 'image':
                return [
                    'type' => 'image',
                    'image' => [
                        'link' => $nodeData['url'] ?? '',
                        'caption' => $nodeData['caption'] ?? '',
                    ],
                ];
            case 'audio':
                return [
                    'type' => 'audio',
                    'audio' => [
                        'link' => $nodeData['url'] ?? '',
                    ],
                ];
            case 'video':
                return [
                    'type' => 'video',
                    'video' => [
                        'link' => $nodeData['url'] ?? '',
                        'caption' => $nodeData['caption'] ?? '',
                    ],
                ];
            case 'document':
                return [
                    'type' => 'document',
                    'document' => [
                        'link' => $nodeData['url'] ?? '',
                        'filename' => $nodeData['filename'] ?? '',
                    ],
                ];
            case 'location':
                return [
                    'type' => 'location',
                    'location' => [
                        'latitude'  => $nodeData['latitude']  ?? '0',
                        'longitude' => $nodeData['longitude'] ?? '0',
                        'name'      => $nodeData['name']      ?? '',
                        'address'   => $nodeData['address']   ?? '',
                    ],
                ];
            case 'interactive_button':
                return [
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'button',
                        'body' => [
                            'text' => $nodeData['body'] ?? '(no text)',
                        ],
                        'action' => [
                            'buttons' => $this->buildFlowButtons($nodeData),
                        ],
                    ],
                ];
            case 'interactive_list':
                return [
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'list',
                        'header' => [
                            'type' => 'text',
                            'text' => $nodeData['header'] ?? 'List Header',
                        ],
                        'body' => [
                            'text' => $nodeData['body'] ?? '(no text)',
                        ],
                        'footer' => [
                            'text' => $nodeData['footer'] ?? '',
                        ],
                        'action' => [
                            'button' => $nodeData['actionButton'] ?? 'Choose',
                            'sections' => $this->buildFlowListSections($nodeData),
                        ],
                    ],
                ];
            case 'template':
                $templateName = $nodeData['template_name'] ?? 'My_Approved_Template';
                $templateLang = $nodeData['language'] ?? 'en_US';
                $templateComponents = $nodeData['components'] ?? [];
                return [
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => [ 'code' => $templateLang ],
                        'components' => $templateComponents,
                    ],
                ];
            case 'cta':
                return [
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'button',
                        'body' => [
                            'text' => 'Choose an option:',
                        ],
                        'action' => [
                            'buttons' => $this->buildCtaButtons($nodeData),
                        ],
                    ],
                ];
            default:
                return [
                    'type' => 'text',
                    'text' => [
                        'body' => 'Flow Node: ' . ucfirst($nodeType),
                        'preview_url' => false,
                    ],
                ];
        }
    }

    private function buildFlowButtons(array $nodeData): array
    {
        $buttons = [];
        if (!empty($nodeData['buttons']) && is_array($nodeData['buttons'])) {
            foreach ($nodeData['buttons'] as $b) {
                $buttons[] = [
                    'type' => 'reply',
                    'reply' => [
                        'id' => $b['id'] ?? ('btn_' . uniqid()),
                        'title' => $b['title'] ?? 'Button',
                    ],
                ];
            }
        }
        return $buttons;
    }

    private function buildFlowListSections(array $nodeData): array
    {
        if (!empty($nodeData['sections']) && is_array($nodeData['sections'])) {
            return $nodeData['sections'];
        }
        return [
            [
                'title' => 'Default Section',
                'rows' => [
                    [
                        'id' => 'row1',
                        'title' => 'Row 1',
                        'description' => 'Description 1',
                    ],
                ],
            ],
        ];
    }

    private function buildCtaButtons(array $nodeData): array
    {
        $buttons = [];
        if (!empty($nodeData['buttons']) && is_array($nodeData['buttons'])) {
            foreach ($nodeData['buttons'] as $b) {
                $buttons[] = [
                    'type' => 'reply',
                    'reply' => [
                        'id' => $b['id'] ?? ('cta_' . uniqid()),
                        'title' => $b['title'] ?? 'CTA',
                    ],
                ];
            }
        }
        return $buttons;
    }

    /* ================= Additional Bot Logic / Logging ================= */

    private function prepareBotLogData($bot, $metadata)
    {
        $botTypeInfo = get_whatsapp_bot_types($bot['bot_type']);
        return [
            'phone_number_id' => $metadata['phone_number_id'],
            'category'        => $botTypeInfo['label'] ?? 'Unknown Bot',
            'category_id'     => $bot['bot_type'] ?? "-",
            'rel_type'        => 'Bots',
            'rel_id'          => $bot['id'],
            'recorded_at'     => date('Y-m-d H:i:s'),
        ];
    }

    private function addWhatsbotLog($logBatch)
    {
        if (!empty($logBatch)) {
            $this->db->insert_batch(db_prefix() . 'whatsapp_activity_log', $logBatch);
        }
    }

    private function sendErrorResponse($message, $data = [])
    {
        log_message('error', $message . ': ' . print_r($data, true));
        http_response_code(400);
        echo json_encode(['error' => $message]);
        exit();
    }

    private function updateFlowSessionOptions($keyNode, $keyExpire, $nodeId, $timeoutSeconds)
    {
        $expiresAt = date('Y-m-d H:i:s', time() + $timeoutSeconds);
        update_option($keyNode, $nodeId);
        update_option($keyExpire, $expiresAt);
    }

    private function resetFlowSessionOptions($keyNode, $keyExpire)
    {
        update_option($keyNode, null);
        update_option($keyExpire, null);
    }

    public function get_user_last_incoming_time($interaction_id)
    {
        $row = $this->db
            ->select('time_sent')
            ->from(db_prefix().'whatsapp_interaction_messages')
            ->where('interaction_id', $interaction_id)
            ->where('nature','received')
            ->order_by('id','DESC')
            ->limit(1)
            ->get()
            ->row();
        return $row ? $row->time_sent : null;
    }

    public function get_last_activity_time($interaction_id)
    {
        $row = $this->db
            ->select_max('time_sent')
            ->from(db_prefix().'whatsapp_interaction_messages')
            ->where('interaction_id', $interaction_id)
            ->get()
            ->row();
        return $row ? $row->time_sent : null;
    }

    public function get_reminder_status($interaction_id)
    {
        return false;
    }

    /* ================= Bot Trigger Conditions ================= */

    private function shouldTriggerBot(
        array $bot,
        string $trigger_msg,
        bool $is_first_message_in_session,
        bool $is_first_time
    ): bool {
        $trigger_type = $bot['reply_type'] ?? null;
        $trigger_msg = strtolower(trim($trigger_msg));
        log_message('error', 'Evaluating bot trigger with message: ' . $trigger_msg);
        $conditions = $this->extractConditions($bot['trigger'] ?? '');

        foreach ($conditions as $condition) {
            switch ($trigger_type) {
                case 1:  // on_exact_match
                    if ($this->isExactMatch($trigger_msg, $condition)) {
                        return true;
                    }
                    break;
                case 2:  // when_message_contains
                    if ($this->containsWord($trigger_msg, $condition)) {
                        return true;
                    }
                    break;
                case 3:  // when_client_send_the_first_message
                    if ($is_first_time) {
                        return true;
                    }
                    break;
                case 4:  // on_keyword_match
                    if ($this->isKeywordMatch($trigger_msg, $condition)) {
                        return true;
                    }
                    break;
                case 5:  // within_office_time_range
                    if ($this->isWithinOfficeTimings($condition)) {
                        return true;
                    }
                    break;
                case 6:  // outof_office_time_range
                    if ($this->isOutsideOfficeTimings($condition)) {
                        return true;
                    }
                    break;
                case 7:  // first_message_in_session
                    if ($is_first_message_in_session) {
                        return true;
                    }
                    break;
                case 8:  // on_user_idle_timeout
                    $idleMinutes = (int)$condition;
                    if ($this->userHasBeenIdleLongerThan($idleMinutes, $bot)) {
                        return true;
                    }
                    break;
                case 9:  // on_user_requests_human
                    if (strpos($trigger_msg, $condition) !== false) {
                        return true;
                    }
                    break;
                case 10: // on_synonym_match
                    if ($this->synonymCheck($trigger_msg, $condition)) {
                        return true;
                    }
                    break;
                case 11: // on_chat_timeout_reminder
                    if ($this->chatNeedsReminder($condition, $bot)) {
                        return true;
                    }
                    break;
                case 12: // on_no_response_inform_staff
                    if ($this->isNoResponseScenario($bot, $condition)) {
                        return true;
                    }
                    break;
                case 13: // on_chat_close_request_star_rating
                    if (strpos($trigger_msg, 'bye') !== false) {
                        return true;
                    }
                    break;
                default:
                    log_message('error', 'Unsupported or unknown trigger type: ' . $trigger_type);
                    break;
            }
        }

        log_message('error', 'No trigger conditions met for message: ' . $trigger_msg);
        return false;
    }

    private function extractConditions(string $trigger): array
    {
        if (empty($trigger)) {
            return [];
        }
        $conditions = preg_split("/[,\|]+/", $trigger);
        $conditions = array_map('trim', $conditions);
        $conditions = array_filter($conditions, function ($cond) {
            return $cond !== '';
        });
        return array_map('strtolower', $conditions);
    }

    private function isExactMatch(string $userMsg, string $condition): bool
    {
        return $userMsg === $condition;
    }

    private function containsWord(string $userMsg, string $word): bool
    {
        return (strpos($userMsg, $word) !== false);
    }

    private function isKeywordMatch(string $message, string $keyword): bool
    {
        $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
        return (bool) preg_match($pattern, $message);
    }

    private function isWithinOfficeTimings(string $time_range): bool
    {
        list($start_time, $end_time) = explode('-', $time_range);
        $current_time = date('H:i');
        return ($current_time >= trim($start_time) && $current_time <= trim($end_time));
    }

    private function isOutsideOfficeTimings(string $time_range): bool
    {
        list($start_time, $end_time) = explode('-', $time_range);
        $current_time = date('H:i');
        return ($current_time < trim($start_time) || $current_time > trim($end_time));
    }

    private function userHasBeenIdleLongerThan(int $minutes, array $bot): bool
    {
        $last_activity = strtotime($this->get_last_activity_time($bot['interaction_id'] ?? 0));
        if (!$last_activity) {
            return false;
        }
        return (time() - $last_activity) > ($minutes * 60);
    }

    private function synonymCheck(string $msg, string $condition): bool
    {
        $synonyms = [
            'help' => ['assist', 'support', 'aid'],
        ];
        if (isset($synonyms[$condition])) {
            foreach ($synonyms[$condition] as $syn) {
                if (strpos($msg, $syn) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    private function chatNeedsReminder(string $condition, array $bot): bool
    {
        $minutes = (int)$condition;
        $last_incoming = strtotime($this->get_user_last_incoming_time($bot['interaction_id'] ?? 0));
        if (!$last_incoming) {
            return false;
        }
        return (time() - $last_incoming) > ($minutes * 60);
    }

    private function isNoResponseScenario(array $bot, string $condition): bool
    {
        $minutes = (int)$condition;
        $last_user_message = strtotime($this->get_user_last_incoming_time($bot['interaction_id'] ?? 0));
        if (!$last_user_message) {
            return false;
        }
        return (time() - $last_user_message) > ($minutes * 60);
    }

    /**
     * Prepare message data for a Review Bot with star rating selection.
     */
    private function prepareReviewMessage($bot, $contact_number)
    {
        $review_prompt = $bot['review_prompt'] ?? "Please rate our service by selecting a star rating:";
        $rows = [];
        for ($i = 1; $i <= 5; $i++) {
            $stars = str_repeat('⭐', $i);
            $rows[] = [
                'id'          => "review_{$i}",
                'title'       => $stars,
                'description' => "Rate us with {$i} star" . ($i > 1 ? "s" : ""),
            ];
        }
        $sections = [
            [
                'title' => 'Star Ratings',
                'rows'  => $rows,
            ],
        ];
        return [
            'type' => 'interactive',
            'interactive' => [
                'type'   => 'list',
                'header' => [
                    'type' => 'text',
                    'text' => $review_prompt,
                ],
                'body' => [
                    'text' => "Select your rating below:",
                ],
                'footer' => [
                    'text' => "",
                ],
                'action' => [
                    'button'   => "Select Rating",
                    'sections' => $sections,
                ],
            ],
        ];
    }

    /* ================= Outgoing Message Handler ================= */

    public function send_message()
    {
        $id = $this->input->post('id', true) ?? '';
        $existing_interaction = $this->db->where('id', $id)->get(db_prefix() . 'whatsapp_interactions')->row_array();
        if (!$existing_interaction) {
            echo json_encode(['success' => false, 'error' => 'Interaction not found']);
            return;
        }
        $to = $this->input->post('to', true) ?? '';
        $template_id = $this->input->post('template_id', true) ?? null;
        $parameters = $this->input->post('parameters', true) ?? [];
        $message = strip_tags($this->input->post('message', true) ?? '');
        $attachments = [
            'image' => $_FILES['image'] ?? null,
            'video' => $_FILES['video'] ?? null,
            'document' => $_FILES['document'] ?? null,
            'audio' => $_FILES['audio'] ?? null,
        ];
        $reaction_emoji = $this->input->post('reaction_emoji', true) ?? '';
        $ref_message_id = $this->input->post('ref_message_id', true) ?? '';
        $carousel_data = $this->input->post('carousel_data', true) ?? null;
        $list_data = $this->input->post('list_data', true) ?? null;
        $message_data = [];

        if ($template_id) {
            $template_data = get_whatsapp_template($template_id);
            if ($template_data) {
                $existing_interaction = array_merge($existing_interaction, $template_data);
                $message_data = $this->WhatsappLibrary->prepare_template_message_data($existing_interaction['wa_no_id'], $existing_interaction);
                log_message('error', 'prepare_template_message_data received: ' . print_r($message_data, true));
            }
        } elseif ($reaction_emoji && $ref_message_id) {
            $message_data = [
                'type' => 'reaction',
                'reaction' => [
                    'emoji' => $reaction_emoji,
                    'message_id' => $ref_message_id,
                ],
            ];
        } elseif ($message) {
            $message_data = [
                'type' => 'text',
                'text' => [
                    'preview_url' => true,
                    'body' => $message,
                ],
            ];
        } else {
            foreach ($attachments as $type => $file) {
                if ($file) {
                    $file_url = $this->WhatsappLibrary->handle_attachment_upload($file);
                    $message_data = [
                        'type' => $type,
                        $type => [
                            'link' => WHATSAPP_MODULE_UPLOAD_URL . $file_url,
                        ],
                    ];
                    break;
                }
            }
            if (!$message_data && $carousel_data) {
                $message_data = [
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'carousel',
                        'header' => $carousel_data['header'],
                        'body' => $carousel_data['body'],
                        'footer' => $carousel_data['footer'],
                        'action' => [
                            'button' => $carousel_data['button'],
                            'sections' => $carousel_data['sections'],
                        ],
                    ],
                ];
            }
            if (!$message_data && $list_data) {
                $message_data = [
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'list',
                        'header' => $list_data['header'],
                        'body' => $list_data['body'],
                        'footer' => $list_data['footer'],
                        'action' => [
                            'button' => $list_data['button'],
                            'sections' => $list_data['sections'],
                        ],
                    ],
                ];
            }
        }

        if ($ref_message_id) {
            $message_data['context'] = ['message_id' => $ref_message_id];
        }
        if (empty($message_data)) {
            echo json_encode(['success' => false, 'error' => 'No valid message data found']);
            return;
        }

        $response = $this->WhatsappLibrary->send_message($existing_interaction['wa_no_id'], $to, $message_data);
        if (!isset($response['response_data'])) {
            $response['response_data'] = [];
        }

        $status_message = '';
        if (isset($response['response_data']['error'])) {
            $main_message = $response['response_data']['error']['message'] ?? 'Failed to send message';
            $error_details = isset($response['response_data']['error']['error_data']['details'])
                ? 'Details: ' . $response['response_data']['error']['error_data']['details']
                : '';
            $status_message = $main_message . ($error_details ? ' - ' . $error_details : '');
        }
        $this->whatsapp_interaction_model->prepare_chat_message($response, $existing_interaction, "message");
        echo json_encode(['success' => empty($response['response_data']['error']), 'status_message' => $status_message]);
    }
}
