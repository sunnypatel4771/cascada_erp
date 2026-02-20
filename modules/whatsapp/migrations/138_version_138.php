<?php

defined('BASEPATH') || exit('No direct script access allowed');

class Migration_Version_138 extends App_module_migration
{
    protected $CI;

    public function __construct()
    {
        parent::__construct();
        $this->CI = &get_instance(); // Initialize the CI instance
        $this->CI->load->dbforge();  // Load the database forge library
    }

    public function up()
    {
        $whatsapp_interactions_table =  'whatsapp_interactions'; // Table name

        // Add 'unread' column if it doesn't exist
        if (!$this->CI->db->field_exists('unread', $whatsapp_interactions_table)) {
            $fields = [
                'unread' => [
                    'type' => 'VARCHAR',
                    'constraint' => 11,
                    'default' => 0,
                    'null' => TRUE,
                ],
            ];
            $this->CI->dbforge->add_column($whatsapp_interactions_table, $fields);
        }

        // Alter the whatsapp_templates table to update character sets and collations
        $whatsapp_templates_table =  'whatsapp_templates';

        // Alter the table fields using dbforge
        $fields = [
            'template_name' => [
                'name' => 'template_name',
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => FALSE,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],
            'language' => [
                'name' => 'language',
                'type' => 'VARCHAR',
                'constraint' => '50',
                'null' => FALSE,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],
            'status' => [
                'name' => 'status',
                'type' => 'VARCHAR',
                'constraint' => '50',
                'null' => FALSE,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],
            'category' => [
                'name' => 'category',
                'type' => 'VARCHAR',
                'constraint' => '100',
                'null' => FALSE,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],
            'header_data_format' => [
                'name' => 'header_data_format',
                'type' => 'VARCHAR',
                'constraint' => '10',
                'null' => FALSE,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],
            'header_data_text' => [
                'name' => 'header_data_text',
                'type' => 'TEXT',
                'null' => TRUE,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],
            'body_data' => [
                'name' => 'body_data',
                'type' => 'TEXT',
                'null' => FALSE,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],
            'footer_data' => [
                'name' => 'footer_data',
                'type' => 'TEXT',
                'null' => TRUE,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],
        ];

        // Modify the fields in the table
        $this->CI->dbforge->modify_column($whatsapp_templates_table, $fields);
    }

    public function down()
    {
        // Implement the down method if necessary, e.g., to reverse schema changes
    }
}
