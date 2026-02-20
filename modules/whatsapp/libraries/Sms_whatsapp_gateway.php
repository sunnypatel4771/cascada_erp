<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

defined('BASEPATH') or exit('No direct script access allowed');

class Sms_whatsapp_gateway extends App_sms
{

    public function __construct()
    {
        parent::__construct();
        $this->add_gateway('whatsapp_gateway', [
            'name' => 'Whatsapp Cloud API Sms Gateway',
            'info' => 'This is Whatsapp Cloud API Sms Gateway.',
            'options' => [
            ],
        ]);
    }

    /**
 * Sends a WhatsApp message and stores it in the database.
 *
 * @param string $number The recipient's phone number.
 * @param string $message The message to send.
 * @return bool Returns true if the message was sent successfully, false otherwise.
 */
public function send($number, $message): bool
{
    try {
        // Prepare the message data
        $message_data = [
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => $message
            ]
        ];

        $CI =& get_instance();
        $CI->load->model('whatsapp/whatsapp_interaction_model');
        $CI->load->library('whatsapp/WhatsappLibrary');

        // Send message using the WhatsApp library
        $whatsappLibrary = new WhatsappLibrary();
        $response = $whatsappLibrary->send_whatsapp_message($number, $message_data);
         $response_data = $response->decodedBody();

    // Check if the response data contains the message ID
        if (isset($response_data['messages'][0]['id'])) {
            // Message sent successfully, store the message ID
            $messageId = $response_data['messages'][0]['id'];
        }
        // Insert message into the database
        $whatsappinteractionModel = $CI->whatsapp_interaction_model;
        $interaction_id = $whatsappinteractionModel->insert_interaction([
            'receiver_id' => $number,
            'last_message' => $message,
            'time_sent' => date("Y-m-d H:i:s")
        ]);

        // Insert interaction message into the database
        $whatsappinteractionModel->insert_interaction_message([
            'interaction_id' => $interaction_id,
            'sender_id' => get_option('phone_number'),
            'message' => $message,
            'message_id' => $messageId,
            'type' => 'text',
            'status' => 'sent',
            'time_sent' => date("Y-m-d H:i:s")
        ]);

            log_activity('<strong>SMS sent via Whatsapp to </strong><hr> Phone: ' . $number . '<br> Message: ' . $message);
            return true;
    } catch (Exception $e) {
        // Handle exceptions
        // Log the error, notify administrators, or perform any necessary actions
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        return false;
    }
}


}
