<?php

defined('BASEPATH') || exit('No direct script access allowed');

class Migration_Version_150 extends App_module_migration
{
    protected $CI;

    public function __construct()
    {
        parent::__construct();
    }

    public function up()
    {
      $CI = &get_instance();
        $whatsapp_campaign_data_table = db_prefix() . 'whatsapp_campaign_data';
        $interaction_messages_table = db_prefix() . 'whatsapp_interaction_messages';
        if ($CI->db->field_exists('header_message', $whatsapp_campaign_data_table)) {
        $CI->db->query("
            ALTER TABLE `$whatsapp_campaign_data_table`
            CHANGE `header_message` `header_data` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
            CHANGE `body_message` `body_data` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
            CHANGE `footer_message` `footer_data` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
        ");
        }
        if (!$CI->db->field_exists('status_message', $interaction_messages_table)) {
            $CI->db->query("ALTER TABLE `$interaction_messages_table` ADD COLUMN `status_message` TEXT NULL;");
        }
       
    }

    public function down()
    {
        
    }
}
