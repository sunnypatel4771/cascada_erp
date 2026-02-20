<?php
defined('BASEPATH') || exit('No direct script access allowed');

class Migration_Version_136 extends App_module_migration
{
    protected $CI;

    public function __construct()
    {
        parent::__construct();
        $this->CI = &get_instance(); // Initialize the CI instance
    }

    public function up()
    {
$CI =& get_instance();
$CI->load->dbforge();

$table = 'whatsapp_bot';

// Define the columns to be added, if they don't exist
$columns_to_add = [
    'bot_header' => [
        'type' => 'VARCHAR',
        'constraint' => '65',
        'null' => TRUE,
    ],
    'bot_footer' => [
        'type' => 'VARCHAR',
        'constraint' => '65',
        'null' => TRUE,
    ],
    'button1' => [
        'type' => 'VARCHAR',
        'constraint' => '25',
        'null' => TRUE,
    ],
    'button1_id' => [
        'type' => 'VARCHAR',
        'constraint' => '258',
        'null' => TRUE,
    ],
    'button2' => [
        'type' => 'VARCHAR',
        'constraint' => '25',
        'null' => TRUE,
    ],
    'button2_id' => [
        'type' => 'VARCHAR',
        'constraint' => '258',
        'null' => TRUE,
    ],
    'button3' => [
        'type' => 'VARCHAR',
        'constraint' => '25',
        'null' => TRUE,
    ],
    'button3_id' => [
        'type' => 'VARCHAR',
        'constraint' => '258',
        'null' => TRUE,
    ],
    'button_name' => [
        'type' => 'VARCHAR',
        'constraint' => '25',
        'null' => TRUE,
    ],
    'button_url' => [
        'type' => 'VARCHAR',
        'constraint' => '255',
        'null' => TRUE,
    ],
    'filename' => [
        'type' => 'TEXT',
        'null' => TRUE,
    ],
    'latitude' => [
        'type' => 'VARCHAR',
        'constraint' => '50',
        'null' => TRUE,
    ],
    'longitude' => [
        'type' => 'VARCHAR',
        'constraint' => '50',
        'null' => TRUE,
    ],
    'location_name' => [
        'type' => 'VARCHAR',
        'constraint' => '255',
        'null' => TRUE,
    ],
    'location_address' => [
        'type' => 'VARCHAR',
        'constraint' => '255',
        'null' => TRUE,
    ],
    'bot_list' => [
        'type' => 'TEXT',
        'null' => TRUE,
    ],
    'poll_question' => [
        'type' => 'VARCHAR',
        'constraint' => '255',
        'null' => TRUE,
    ],
    'poll_option1' => [
        'type' => 'VARCHAR',
        'constraint' => '255',
        'null' => TRUE,
    ],
    'poll_option2' => [
        'type' => 'VARCHAR',
        'constraint' => '255',
        'null' => TRUE,
    ],
    'poll_option3' => [
        'type' => 'VARCHAR',
        'constraint' => '255',
        'null' => TRUE,
    ],
  
    'is_bot_active' => [
        'type' => 'TINYINT',
        'constraint' => '1',
        'default' => '1',
        'null' => FALSE,
    ],
    'sending_count' => [
        'type' => 'INT',
        'default' => '0',
        'null' => TRUE,
    ],
    'media_type' => [
        'type' => 'VARCHAR',
        'constraint' => '255',
        'null' => TRUE,
    ],
      'contact_name' => [
        'type' => 'VARCHAR',
        'constraint' => '255',
        'null' => TRUE,
    ],
      'contact_first_name' => [
        'type' => 'VARCHAR',
        'constraint' => '255',
        'null' => TRUE,
    ],
      'contact_last_name' => [
        'type' => 'VARCHAR',
        'constraint' => '255',
        'null' => TRUE,
    ],
    'contact_number' => [
        'type' => 'VARCHAR',
        'constraint' => '255',
        'null' => TRUE,
    ],
    'contact_email' => [
        'type' => 'VARCHAR',
        'constraint' => '255',
        'null' => TRUE,
    ],
];

// Loop through each column and add it if it doesn't exist
foreach ($columns_to_add as $column_name => $column_def) {
    if (!$CI->db->field_exists($column_name, $table)) {
        $CI->dbforge->add_column($table, [$column_name => $column_def]);
    }
}
$whatsapp_numbers_table = 'whatsapp_numbers'; // Assuming this is the correct table name
// Check if the 'verified_name' column does not exist in the 'whatsapp_numbers' table
if (!$CI->db->field_exists('verified_name', $whatsapp_numbers_table)) {
    $CI->dbforge->add_column($whatsapp_numbers_table, [
        'verified_name' => [
            'type' => 'TEXT',
            'null' => TRUE,
        ],
    ]);
}
    }

    public function down()
    {
       
    }
}
