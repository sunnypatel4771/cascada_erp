<?php
defined('BASEPATH') || exit('No direct script access allowed');

class Migration_Version_131 extends App_module_migration
{
    protected $CI;

    public function __construct()
    {
        parent::__construct();
        $this->CI = &get_instance(); // Initialize the CI instance
    }

    public function up()
    {
        // Define table name with prefix
        $interaction_messages_table = db_prefix() . 'whatsapp_interaction_messages';
        $interaction_table = db_prefix() . 'whatsapp_interactions';

        // Check if the 'nature' column exists
        $columnExists = $this->CI->db->field_exists('nature', $interaction_messages_table);

        if (!$columnExists) {
            // Add the 'nature' column if it does not exist for message detection that is sent/received
            $sql = "ALTER TABLE `$interaction_messages_table` ADD COLUMN `nature` VARCHAR(50) NULL;";
            $this->CI->db->query($sql);

            // Update only the records where 'nature' is NULL
            $this->CI->db->where('nature IS NULL', null, false);
            $query = $this->CI->db->get($interaction_messages_table);
            $messages = $query->result_array();

            foreach ($messages as $message) {
                // Fetch the receiver_id from the related interaction record
                $interaction = $this->CI->db->select('receiver_id')->where('id', $message['interaction_id'])->get($interaction_table)->row_array();

                if ($interaction) {
                    // Determine if the message is 'sent' or 'received'
                    $nature = ($message['sender_id'] === $interaction['receiver_id']) ? 'received' : 'sent';

                    // Update the nature in the database
                    $this->CI->db->where('id', $message['id'])->update($interaction_messages_table, ['nature' => $nature]);
                }
            }
        }
    }

    public function down()
    {
        // Rollback logic if necessary
    }
}
