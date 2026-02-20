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
    if (!$CI->db->field_exists($columnName, $whatsapp_numbers_table)) {
        $CI->db->query("ALTER TABLE `$whatsapp_numbers_table` ADD COLUMN `$columnName` $columnDefinition;");
    }
}
// If whatsapp_openai_status is currently 'enable', update it to 'auto'
if (get_option('whatsapp_openai_status') === 'enable') {
    update_option('whatsapp_openai_status', 'auto');
}

// Define the columns to be added, if they don't exist
$columns_to_add = [
    'bot_header' => 'VARCHAR(65) DEFAULT NULL',
    'bot_footer' => 'VARCHAR(65) DEFAULT NULL',
    'button1' => 'VARCHAR(25) DEFAULT NULL',
    'button1_id' => 'VARCHAR(258) DEFAULT NULL',
    'button2' => 'VARCHAR(25) DEFAULT NULL',
    'button2_id' => 'VARCHAR(258) DEFAULT NULL',
    'button3' => 'VARCHAR(25) DEFAULT NULL',
    'button3_id' => 'VARCHAR(258) DEFAULT NULL',
    'button_name' => 'VARCHAR(25) DEFAULT NULL',
    'button_url' => 'VARCHAR(255) DEFAULT NULL',
    'filename' => 'TEXT DEFAULT NULL',
    'latitude' => 'VARCHAR(50) DEFAULT NULL',
    'longitude' => 'VARCHAR(50) DEFAULT NULL',
    'location_name' => 'VARCHAR(255) DEFAULT NULL',
    'location_address' => 'VARCHAR(255) DEFAULT NULL',
    'bot_list' => 'TEXT DEFAULT NULL',
    'poll_question' => 'VARCHAR(255) DEFAULT NULL',
    'poll_option1' => 'VARCHAR(255) DEFAULT NULL',
    'poll_option2' => 'VARCHAR(255) DEFAULT NULL',
    'poll_option3' => 'VARCHAR(255) DEFAULT NULL',
    'is_bot_active' => 'TINYINT(1) DEFAULT 1 NOT NULL',
    'sending_count' => 'INT DEFAULT 0',
    'media_type' => 'VARCHAR(255) DEFAULT NULL',
    'contact_name' => 'VARCHAR(255) DEFAULT NULL',
    'contact_first_name' => 'VARCHAR(255) DEFAULT NULL',
    'contact_last_name' => 'VARCHAR(255) DEFAULT NULL',
    'contact_number' => 'VARCHAR(255) DEFAULT NULL',
    'contact_email' => 'VARCHAR(255) DEFAULT NULL'
];

// Loop through each column and add it if it doesn't exist
foreach ($columns_to_add as $column_name => $column_definition) {
    if (!$CI->db->field_exists($column_name, $whatsapp_bot_table)) {
        $CI->db->query("ALTER TABLE `$whatsapp_bot_table` ADD COLUMN `$column_name` $column_definition;");
    }
}

// Check if the 'verified_name' column does not exist in the 'whatsapp_numbers' table
if (!$CI->db->field_exists('verified_name', $whatsapp_numbers_table)) {
    $CI->db->query("ALTER TABLE `$whatsapp_numbers_table` ADD COLUMN `verified_name` TEXT DEFAULT NULL;");
}

// Add 'unread' column to `whatsapp_interactions` if it doesn't exist
if (!$CI->db->field_exists('unread', $interaction_table)) {
    $CI->db->query("ALTER TABLE `$interaction_table` ADD COLUMN `unread` VARCHAR(11) DEFAULT '0' NULL;");
}
$CI->db->query("ALTER TABLE `$whatsapp_templates_table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
$fields = [
    'template_name' => 'VARCHAR(255) NOT NULL',
    'language' => 'VARCHAR(50) NOT NULL',
    'status' => 'VARCHAR(50) NOT NULL',
    'category' => 'VARCHAR(100) NOT NULL',
    'header_data_format' => 'VARCHAR(10) NOT NULL',
    'header_data_text' => 'TEXT DEFAULT NULL',
    'body_data' => 'TEXT NOT NULL',
    'footer_data' => 'TEXT DEFAULT NULL'
];

foreach ($fields as $field_name => $field_definition) {
    $CI->db->query("ALTER TABLE `$whatsapp_templates_table` MODIFY COLUMN `$field_name` $field_definition;");
}
// Check if the 'verified_name' column does not exist in the 'whatsapp_numbers' table
if (!$CI->db->field_exists('nature', $interaction_messages_table)) {
    $CI->db->query("ALTER TABLE `$interaction_messages_table` ADD COLUMN `nature` VARCHAR(50) NULL;");
}
// Check if the 'verified_name' column does not exist in the 'whatsapp_numbers' table
if (!$CI->db->field_exists('menu_items', $whatsapp_bot_table)) {
$CI->db->query("ALTER TABLE `$whatsapp_bot_table` ADD COLUMN `menu_items` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;");
}



// Add 'tags' column to `whatsapp_interactions` if it doesn't exist
if (!$CI->db->field_exists('tags', $interaction_table)) {
    $CI->db->query("ALTER TABLE `$interaction_table` ADD COLUMN `tags` LONGTEXT NULL;");
}
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
// Add 'is_pinned' column to `whatsapp_interactions` if it doesn't exist
if (!$CI->db->field_exists('is_pinned', $interaction_table)) {
$CI->db->query("ALTER TABLE `$interaction_table` ADD COLUMN `is_pinned` VARCHAR(1) DEFAULT '0' NOT NULL;");
}
// Check if columns 'type' and 'type_id' exist in the table
$columns = $CI->db->list_fields($interaction_table);

if (in_array('type', $columns) && in_array('type_id', $columns)) {
    // Rename the columns only if they exist
    $CI->db->query("ALTER TABLE `$interaction_table` CHANGE `type` `rel_type` VARCHAR(500) NULL, CHANGE `type_id` `rel_id` VARCHAR(500) NULL; ");
}

if (!$CI->db->field_exists('group_id', $whatsapp_campaigns_table)) {
    // Add the 'group_id' column as INT and set up the foreign key constraint with ON DELETE CASCADE
    $CI->db->query("
        ALTER TABLE `$whatsapp_campaigns_table`
        ADD COLUMN `group_id` INT NULL DEFAULT NULL;
    ");
}
if ($CI->db->field_exists('number', $whatsapp_contacts_table) && !$CI->db->field_exists('phonenumber', $whatsapp_contacts_table)) {
    $CI->db->query("ALTER TABLE `$whatsapp_contacts_table`
        CHANGE COLUMN `number` `phonenumber` VARCHAR(50) NOT NULL;
    ");
}

if (!$CI->db->field_exists('payload', $interaction_messages_table)) {
    $CI->db->query("ALTER TABLE $interaction_messages_table ADD COLUMN payload LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;");
}
// Check if the 'ai_prompt' column does not exist in the 'whatsapp_numbers' table
if (!$CI->db->field_exists('ai_prompt', $whatsapp_numbers_table)) {
    $CI->db->query("ALTER TABLE $whatsapp_numbers_table ADD COLUMN ai_prompt LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;");
}
if ($CI->db->field_exists('bot_type', $whatsapp_campaigns_table)) {
    $CI->db->query("ALTER TABLE $whatsapp_campaigns_table DROP COLUMN bot_type");
}

if ($CI->db->field_exists('is_bot_active', $whatsapp_campaigns_table)) {
    $CI->db->query("ALTER TABLE $whatsapp_campaigns_table DROP COLUMN is_bot_active");
}

if ($CI->db->field_exists('is_bot', $whatsapp_campaigns_table)) {
    $CI->db->query("ALTER TABLE $whatsapp_campaigns_table DROP COLUMN is_bot");
}

if ($CI->db->field_exists('trigger', $whatsapp_campaigns_table)) {
    $CI->db->query("ALTER TABLE `$whatsapp_campaigns_table` DROP COLUMN `trigger`");
}


if (!$CI->db->field_exists('campaign_type', $whatsapp_campaigns_table)) {
    $CI->db->query("
        ALTER TABLE `$whatsapp_campaigns_table`
        ADD COLUMN `campaign_type` VARCHAR(50) NOT NULL DEFAULT 'onetime' COMMENT 'Type of campaign (e.g., onetime, recurring, custom)'
    ");
}

if (!$CI->db->field_exists('auto_reminder_days', $whatsapp_campaigns_table)) {
    $CI->db->query("
        ALTER TABLE `$whatsapp_campaigns_table`
        ADD COLUMN `auto_reminder_days` INT DEFAULT NULL COMMENT 'Days after which reminders will be sent'
    ");
}

if (!$CI->db->field_exists('reminder_schedule_hour', $whatsapp_campaigns_table)) {
    $CI->db->query("
        ALTER TABLE `$whatsapp_campaigns_table`
        ADD COLUMN `reminder_schedule_hour` TIME DEFAULT NULL COMMENT 'Specific hour to send reminders'
    ");
}

if (!$CI->db->field_exists('last_run_time', $whatsapp_campaigns_table)) {
    $CI->db->query("
        ALTER TABLE `$whatsapp_campaigns_table`
        ADD COLUMN `last_run_time` TIMESTAMP NULL DEFAULT NULL COMMENT 'Tracks the last time the campaign was executed'
    ");
}
// Button #1
if (!$CI->db->field_exists('btn1_name', $whatsapp_bot_table)) {
    $CI->db->query("ALTER TABLE `{$whatsapp_bot_table}` ADD COLUMN `btn1_name` VARCHAR(50) DEFAULT NULL;");
}
if (!$CI->db->field_exists('btn1_type', $whatsapp_bot_table)) {
    $CI->db->query("ALTER TABLE `{$whatsapp_bot_table}` ADD COLUMN `btn1_type` VARCHAR(20) DEFAULT NULL;"); // e.g., "reply", "url", "phone"
}
if (!$CI->db->field_exists('btn1_link', $whatsapp_bot_table)) {
    $CI->db->query("ALTER TABLE `{$whatsapp_bot_table}` ADD COLUMN `btn1_link` VARCHAR(255) DEFAULT NULL;"); // for "url" type
}
if (!$CI->db->field_exists('btn1_number', $whatsapp_bot_table)) {
    $CI->db->query("ALTER TABLE `{$whatsapp_bot_table}` ADD COLUMN `btn1_number` VARCHAR(25) DEFAULT NULL;"); // for "phone" type
}

// Button #2
if (!$CI->db->field_exists('btn2_name', $whatsapp_bot_table)) {
    $CI->db->query("ALTER TABLE `{$whatsapp_bot_table}` ADD COLUMN `btn2_name` VARCHAR(50) DEFAULT NULL;");
}
if (!$CI->db->field_exists('btn2_type', $whatsapp_bot_table)) {
    $CI->db->query("ALTER TABLE `{$whatsapp_bot_table}` ADD COLUMN `btn2_type` VARCHAR(20) DEFAULT NULL;");
}
if (!$CI->db->field_exists('btn2_link', $whatsapp_bot_table)) {
    $CI->db->query("ALTER TABLE `{$whatsapp_bot_table}` ADD COLUMN `btn2_link` VARCHAR(255) DEFAULT NULL;");
}
if (!$CI->db->field_exists('btn2_number', $whatsapp_bot_table)) {
    $CI->db->query("ALTER TABLE `{$whatsapp_bot_table}` ADD COLUMN `btn2_number` VARCHAR(25) DEFAULT NULL;");
}

// Button #3
if (!$CI->db->field_exists('btn3_name', $whatsapp_bot_table)) {
    $CI->db->query("ALTER TABLE `{$whatsapp_bot_table}` ADD COLUMN `btn3_name` VARCHAR(50) DEFAULT NULL;");
}
if (!$CI->db->field_exists('btn3_type', $whatsapp_bot_table)) {
    $CI->db->query("ALTER TABLE `{$whatsapp_bot_table}` ADD COLUMN `btn3_type` VARCHAR(20) DEFAULT NULL;");
}
if (!$CI->db->field_exists('btn3_link', $whatsapp_bot_table)) {
    $CI->db->query("ALTER TABLE `{$whatsapp_bot_table}` ADD COLUMN `btn3_link` VARCHAR(255) DEFAULT NULL;");
}
if (!$CI->db->field_exists('btn3_number', $whatsapp_bot_table)) {
    $CI->db->query("ALTER TABLE `{$whatsapp_bot_table}` ADD COLUMN `btn3_number` VARCHAR(25) DEFAULT NULL;");
}
// Define the new columns to add to the table with their definitions
$columns_to_add = [
    'parameter_format'      => "VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL",
    'sub_category'          => "VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL",
    'header_example'        => "TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL",
    'body_example'          => "TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL",
    'footer_example'        => "TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL",
    'buttons_example'       => "TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL",
    'created_at'            => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    'updated_at'            => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    'template_description'  => "TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL",
    'is_custom'             => "TINYINT(1) DEFAULT 0"
];

// Iterate through the array to check and add missing columns
foreach ($columns_to_add as $column => $definition) {
    // Check if the column already exists
    $result = $CI->db->query("SHOW COLUMNS FROM `$whatsapp_templates_table` LIKE '$column'");

    if ($result->num_rows() == 0) {
        // Column doesn't exist, so add it
        $CI->db->query("ALTER TABLE `$whatsapp_templates_table` ADD COLUMN `$column` $definition;");
    }
}

// Finally, ensure the character set and collation are set to utf8mb4
$CI->db->query("ALTER TABLE `$whatsapp_templates_table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

