<?php
defined('BASEPATH') || exit('No direct script access allowed');

class Migration_Version_135 extends App_module_migration
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
        $whatsapp_bot_table = db_prefix() . 'whatsapp_bot';
            
            // Check if the 'flow_data' column does not exist
            if (!$CI->db->field_exists('body_params', $whatsapp_bot_table)) {
                $CI->db->query("
                            ALTER TABLE `$whatsapp_bot_table`
                            ADD COLUMN `body_params` TEXT NULL;
                        ");
            }
            
            // Check if the 'flow_data' column does not exist
            if (!$CI->db->field_exists('header_params', $whatsapp_bot_table)) {
                $CI->db->query("
                            ALTER TABLE `$whatsapp_bot_table`
                            ADD COLUMN `header_params` TEXT NULL;
                        ");
            }
            
            // Check if the 'flow_data' column does not exist
            if (!$CI->db->field_exists('footer_params', $whatsapp_bot_table)) {
                $CI->db->query("
                            ALTER TABLE `$whatsapp_bot_table`
                            ADD COLUMN `footer_params` TEXT NULL;
                        ");
            }
            
            $CI->db->query("DELETE FROM `" . db_prefix() . "whatsapp_interactions` WHERE `wa_no_id` IS NULL");
            
            // Define the table name
            $whatsappNumbersTable = db_prefix() . 'whatsapp_numbers';
            
            // Columns to be added with their respective SQL definitions
            $columnsToAdd = [
                'profile_picture' => 'TEXT DEFAULT NULL',
                'verified_name' => 'VARCHAR(255) DEFAULT NULL',
                'code_verification_status' => 'VARCHAR(50) DEFAULT NULL',
                'display_phone_number' => 'VARCHAR(50) DEFAULT NULL',
                'quality_rating' => 'VARCHAR(50) DEFAULT NULL',
                'platform_type' => 'VARCHAR(50) DEFAULT NULL',
                'throughput_level' => 'VARCHAR(50) DEFAULT NULL',
                'external_id' => 'VARCHAR(50) DEFAULT NULL' // 'ID' column renamed to 'external_id' to avoid conflict with primary key
            ];
            
            // Loop through each column and check if it exists, if not, add it
            foreach ($columnsToAdd as $columnName => $columnDefinition) {
                if (!$CI->db->field_exists($columnName, $whatsappNumbersTable)) {
                    $CI->db->query("ALTER TABLE `$whatsappNumbersTable` ADD COLUMN `$columnName` $columnDefinition;");
                }
            }


    }

    public function down()
    {
       
    }
}
