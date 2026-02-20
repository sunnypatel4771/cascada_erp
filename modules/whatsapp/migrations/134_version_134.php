<?php
defined('BASEPATH') || exit('No direct script access allowed');

class Migration_Version_134 extends App_module_migration
{
    protected $CI;

    public function __construct()
    {
        parent::__construct();
        $this->CI = &get_instance(); // Initialize the CI instance
    }

    public function up()
    {

               $CI = &get_instance(); // Initialize the CI instance

 // Define table name with prefix
        $whatsapp_bot_table = db_prefix() . 'whatsapp_bot';

        // Check if the 'flow_data' column does not exist
        if (!$CI->db->field_exists('flow_data', $whatsapp_bot_table)) {
            $CI->db->query("
                ALTER TABLE `$whatsapp_bot_table`
                ADD COLUMN `flow_data` TEXT NULL;
            ");
        }

        // Check if the 'bot_type' column does not exist
        if (!$CI->db->field_exists('bot_type', $whatsapp_bot_table)) {
            $CI->db->query("
                ALTER TABLE `$whatsapp_bot_table`
                ADD COLUMN `bot_type` VARCHAR(50) NULL;
            ");
        }
    
    $whatsapp_bot_table = db_prefix() . 'whatsapp_bot';

        // Check if the 'flow_data' column does not exist
        if (!$CI->db->field_exists('bot_list', $whatsapp_bot_table)) {
            $CI->db->query("
                ALTER TABLE `$whatsapp_bot_table`
                ADD COLUMN `bot_list` TEXT NULL;
            ");
        }

        $whatsapp_bot_table = db_prefix() . 'whatsapp_bot';

        // Check if the 'flow_data' column does not exist
        if (!$CI->db->field_exists('template_id', $whatsapp_bot_table)) {
            $CI->db->query("
                ALTER TABLE `$whatsapp_bot_table`
                ADD COLUMN `template_id` VARCHAR(50) NULL;
            ");
        }


    }

    public function down()
    {
       
    }
}
