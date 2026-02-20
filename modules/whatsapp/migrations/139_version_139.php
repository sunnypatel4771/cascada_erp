<?php

defined('BASEPATH') || exit('No direct script access allowed');

class Migration_Version_139 extends App_module_migration
{
    protected $CI;

    public function __construct()
    {
        parent::__construct();
    }

    public function up()
    {
        $CI = &get_instance();
        $interaction_menu_state_table = db_prefix() . 'interaction_menu_state';
$whatsapp_bot_table = db_prefix() . 'whatsapp_bot';
// Check if the 'verified_name' column does not exist in the 'whatsapp_numbers' table
if (!$CI->db->field_exists('menu_items', $whatsapp_bot_table)) {
    $CI->db->query("ALTER TABLE `tblwhatsapp_bot` ADD COLUMN `menu_items` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;");
    }
    // Create or update interaction_menu_state table
    $CI->db->query("
    CREATE TABLE IF NOT EXISTS `$interaction_menu_state_table` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `menu_path` TEXT NULL,
        `interaction_id` INT(11) UNSIGNED NULL,
        `bot_id` INT(11) UNSIGNED NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
       
    }

    public function down()
    {
        
    }
}
