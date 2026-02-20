<?php
defined('BASEPATH') or exit('No direct script access allowed');

// Get CodeIgniter instance
$CI = &get_instance();

// Define table names with prefix
$interaction_table = db_prefix() . 'whatsapp_interactions';
$interaction_messages_table = db_prefix() . 'whatsapp_interaction_messages';
$whatsapp_bot_table = db_prefix() . 'whatsapp_bot';
$whatsapp_templates_table = db_prefix() . 'whatsapp_templates';
$whatsapp_campaigns_table = db_prefix() . 'whatsapp_campaigns';
$whatsapp_campaign_data_table = db_prefix() . 'whatsapp_campaign_data';
$whatsapp_activity_log_table = db_prefix() . 'whatsapp_activity_log';
$whatsapp_numbers_table = db_prefix() . 'whatsapp_numbers';
$quick_replies_table = db_prefix() . 'quick_replies';
$whatsapp_automations = db_prefix() . 'whatsapp_automations';
$interaction_menu_state_table = db_prefix() . 'interaction_menu_state';
$whatsapp_contacts_table = db_prefix() . 'whatsapp_contacts';
$groups_table = db_prefix() . 'whatsapp_groups';
$contact_group_table = db_prefix() . 'whatsapp_contact_group';

$options = [
    'whatsapp_webhook_token' => '123456',
    'whatsapp_blueticks_status' => 'enable',
    'whatsapp_openai_status' => 'disable',
    'whatsapp_auto_lead_settings' => 'disable',
    'enable_webhooks' => '0',
    'whatsapp_reload_interval' => '5',      // Default reload interval set to 5 seconds
    'whatsapp_load_more_limit' => '20'      // Default load more limit set to 20 chats
];

// Iterate through each default option
foreach ($options as $key => $value) {
    // Check if the option exists; if not, add it with the default value
    if (!get_option($key)) {
        add_option($key, $value);
    }
}
// If whatsapp_openai_status is currently 'enable', update it to 'auto'
if (get_option('whatsapp_openai_status') === 'enable') {
    update_option('whatsapp_openai_status', 'auto');
}

// Create or alter tables as needed

// Create or update whatsapp_interactions table
$CI->db->query("
    CREATE TABLE IF NOT EXISTS `$interaction_table` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `wa_no` VARCHAR(20) NULL,
        `wa_no_id` VARCHAR(20) NULL,
        `receiver_id` VARCHAR(20) NOT NULL,
        `last_message` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `last_msg_time` DATETIME NULL,
        `time_sent` DATETIME NOT NULL,
        `type` VARCHAR(500) NULL,
        `type_id` VARCHAR(500) NULL,
        `unread` INT DEFAULT 0 NULL,
        `ai_prompt` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Create or update whatsapp_interaction_messages table
$CI->db->query("
    CREATE TABLE IF NOT EXISTS `$interaction_messages_table` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `interaction_id` INT(11) UNSIGNED NOT NULL,
        `sender_id` VARCHAR(20) NOT NULL,
        `url` VARCHAR(255) NULL,
        `message` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `status` VARCHAR(20) NULL,
        `status_message` TEXT NULL,
        `time_sent` DATETIME NOT NULL,
        `message_id` VARCHAR(500) NULL,
        `staff_id` VARCHAR(500) NULL,
        `type` VARCHAR(20) NULL,
        `ref_message_id` VARCHAR(500) NULL,
        `nature` VARCHAR(50) NULL,
        `payload` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`interaction_id`) REFERENCES `$interaction_table`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");



// Create or update whatsapp_templates table
$CI->db->query("
    CREATE TABLE IF NOT EXISTS `$whatsapp_templates_table` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `template_id` BIGINT UNSIGNED NOT NULL,
        `template_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `language` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `status` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `category` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `sub_category` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL, -- New field for sub-category
        `parameter_format` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL, -- New field for parameter format (e.g., NAMED, POSITIONAL)
        `header_data_format` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `header_data_text` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `header_params_count` INT NOT NULL,
        `header_example` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL, -- New field for header example
        `body_data` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `body_params_count` INT NOT NULL,
        `body_example` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL, -- New field for body example
        `footer_data` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `footer_params_count` INT NOT NULL,
        `footer_example` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL, -- New field for footer example
        `buttons_data` VARCHAR(255) NOT NULL,
        `buttons_example` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL, -- New field for buttons example
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- New field for record creation timestamp
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- New field for last updated timestamp
        `template_description` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL, -- New field for template description
        `is_custom` TINYINT(1) DEFAULT 0, -- New field to identify if the template is custom
        PRIMARY KEY (`id`),
        UNIQUE KEY `template_id` (`template_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
");
// Create or update whatsapp_bot table
$CI->db->query("
    CREATE TABLE IF NOT EXISTS `$whatsapp_bot_table` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `rel_type` VARCHAR(50) NOT NULL,
        `reply_text` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `reply_type` INT NOT NULL,
        `trigger` VARCHAR(255) NOT NULL,
        `bot_header` VARCHAR(65) DEFAULT NULL,
        `bot_footer` VARCHAR(65) DEFAULT NULL,
        `button1` VARCHAR(25) DEFAULT NULL,
        `button1_id` VARCHAR(258) DEFAULT NULL,
        `button2` VARCHAR(25) DEFAULT NULL,
        `button2_id` VARCHAR(258) DEFAULT NULL,
        `button3` VARCHAR(25) DEFAULT NULL,
        `button3_id` VARCHAR(258) DEFAULT NULL,
        `button_name` VARCHAR(25) DEFAULT NULL,
        `button_url` VARCHAR(255) DEFAULT NULL,
        `filename` TEXT DEFAULT NULL,
        `addedfrom` INT NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `is_bot_active` TINYINT(1) NOT NULL DEFAULT 1,
        `sending_count` INT DEFAULT 0,
        `flow_data` TEXT NULL,
        `bot_type` VARCHAR(50) NULL,
        `bot_list` TEXT NULL,
        `template_id` VARCHAR(50) NULL,
        `body_params` TEXT NULL,
        `header_params` TEXT NULL,
        `footer_params` TEXT NULL,
        `latitude` VARCHAR(50) NULL,
        `longitude` VARCHAR(50) NULL,
        `location_name` VARCHAR(255) NULL,
        `location_address` VARCHAR(255) NULL,
        `poll_question` VARCHAR(255) NULL,
        `poll_option1` VARCHAR(255) NULL,
        `poll_option2` VARCHAR(255) NULL,
        `poll_option3` VARCHAR(255) NULL,
        `media_type` VARCHAR(255) NULL,
        `contact_name` VARCHAR(255) NULL,
        `contact_first_name` VARCHAR(255) NULL,
        `contact_last_name` VARCHAR(255) NULL,
        `contact_number` VARCHAR(255) NULL,
        `contact_email` VARCHAR(255) NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$CI->db->query("
    CREATE TABLE IF NOT EXISTS `$whatsapp_campaigns_table` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `rel_type` VARCHAR(50) NOT NULL,
        `template_id` INT DEFAULT NULL,
        `scheduled_send_time` TIMESTAMP NULL DEFAULT NULL,
        `send_now` TINYINT NOT NULL DEFAULT 0,
        `header_params` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `body_params` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `footer_params` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `filename` TEXT DEFAULT NULL,
        `pause_campaign` TINYINT(1) NOT NULL DEFAULT 0,
        `select_all` TINYINT(1) NOT NULL DEFAULT 0,
        `trigger` VARCHAR(191) DEFAULT NULL,
        `is_sent` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `sending_count` INT DEFAULT 0,
        `auto_reminder_days` INT DEFAULT NULL COMMENT 'Days after which reminders will be sent',
        `reminder_schedule_hour` TIME DEFAULT NULL COMMENT 'Specific hour to send reminders',
        `campaign_type` VARCHAR(50) NOT NULL DEFAULT 'onetime' COMMENT 'Type of campaign (e.g., onetime, recurring, custom)',
        `last_run_time` TIMESTAMP NULL DEFAULT NULL COMMENT 'Tracks the last time the campaign was executed',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
");

// Create or update whatsapp_campaign_data table
$CI->db->query("
    CREATE TABLE IF NOT EXISTS `$whatsapp_campaign_data_table` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `campaign_id` INT NOT NULL,
        `rel_id` INT DEFAULT NULL,
        `rel_type` VARCHAR(50) NOT NULL,
        `header_data` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `body_data` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `footer_data` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `status` INT DEFAULT NULL,
        `response_message` TEXT DEFAULT NULL,
        `whatsapp_id` TEXT DEFAULT NULL,
        `message_status` VARCHAR(25) DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
");

// Create or update whatsapp_activity_log table
$CI->db->query("
    CREATE TABLE IF NOT EXISTS `$whatsapp_activity_log_table` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `phone_number_id` VARCHAR(255) DEFAULT NULL,
        `access_token` TEXT DEFAULT NULL,
        `business_account_id` VARCHAR(255) DEFAULT NULL,
        `response_code` VARCHAR(4) NOT NULL,
        `response_data` TEXT NOT NULL,
        `category` VARCHAR(50) NOT NULL,
        `category_id` INT(11) NOT NULL,
        `rel_type` VARCHAR(50) NOT NULL,
        `rel_id` INT(11) NOT NULL,
        `category_params` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `raw_data` TEXT NOT NULL,
        `recorded_at` DATETIME NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
");

// Create or update whatsapp_numbers table
$CI->db->query("
    CREATE TABLE IF NOT EXISTS `$whatsapp_numbers_table` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `phone_number_id` VARCHAR(50) NOT NULL,
        `phone_number` VARCHAR(50) NOT NULL,
        `profile_picture_url` TEXT DEFAULT NULL,
        `about` TEXT DEFAULT NULL,
        `address` TEXT DEFAULT NULL,
        `vertical` VARCHAR(255) DEFAULT NULL,
        `email` VARCHAR(255) DEFAULT NULL,
        `websites` TEXT DEFAULT NULL,
        `is_default` TINYINT(1) NOT NULL DEFAULT 0,
        `profile_picture` TEXT DEFAULT NULL,
        `verified_name` TEXT DEFAULT NULL,
        `code_verification_status` VARCHAR(50) DEFAULT NULL,
        `display_phone_number` VARCHAR(50) DEFAULT NULL,
        `quality_rating` VARCHAR(50) DEFAULT NULL,
        `platform_type` VARCHAR(50) DEFAULT NULL,
        `throughput_level` VARCHAR(50) DEFAULT NULL,
        `external_id` VARCHAR(50) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `phone_number_id` (`phone_number_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
");

// Create or update quick_replies table
$CI->db->query("
    CREATE TABLE IF NOT EXISTS `$quick_replies_table` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `message` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
");
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
// Create the whatsapp_contacts table if it doesn’t exist
$CI->db->query("
    CREATE TABLE IF NOT EXISTS `$whatsapp_contacts_table` (
        `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `company_name` VARCHAR(255) NULL,
        `phonenumber` VARCHAR(50) NOT NULL,
        `address` TEXT NULL,
        `city` VARCHAR(100) NULL,
        `state` VARCHAR(100) NULL,
        `country` VARCHAR(100) NULL,
        `description` TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");


// Create the whatsapp_groups table if it doesn’t exist
$CI->db->query("
    CREATE TABLE IF NOT EXISTS `$groups_table` (
        `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$CI->db->query("
    CREATE TABLE IF NOT EXISTS `$contact_group_table` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `contact_id` INT(11) UNSIGNED NOT NULL,
        `group_id` INT(11) UNSIGNED NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_contact_group` (`contact_id`, `group_id`), -- Ensures unique contact-group pairs
        FOREIGN KEY (`contact_id`) REFERENCES `$whatsapp_contacts_table`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`group_id`) REFERENCES `$groups_table`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");



?>
