<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Add_priority_zone_to_invoices extends CI_Migration
{
    public function up()
    {
        // Add priority field to tblinvoices (1-9 scale, default 5 = normal)
        if (!$this->db->field_exists('priority', db_prefix() . 'invoices')) {
            $this->db->query(
                'ALTER TABLE ' . db_prefix() . 'invoices ADD COLUMN priority INT DEFAULT 5 AFTER duedate'
            );
        }

        // Add zone field to tblinvoices (matches Zona custom field options)
        if (!$this->db->field_exists('zone', db_prefix() . 'invoices')) {
            $this->db->query(
                'ALTER TABLE ' . db_prefix() . 'invoices ADD COLUMN zone VARCHAR(100) DEFAULT NULL AFTER priority'
            );
        }

        // Add index for performance on queries filtering by zone and priority (if it doesn't exist)
        $indexes = $this->db->query(
            "SELECT DISTINCT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_NAME = '" . 
            db_prefix() . "invoices' AND INDEX_NAME = 'idx_zone_priority'"
        )->result_array();

        if (empty($indexes)) {
            $this->db->query(
                'ALTER TABLE ' . db_prefix() . 'invoices ADD INDEX idx_zone_priority (zone, priority)'
            );
        }
    }

    public function down()
    {
        // Remove the index first
        $this->db->query(
            'ALTER TABLE ' . db_prefix() . 'invoices DROP INDEX IF EXISTS idx_zone_priority'
        );

        // Remove columns
        if ($this->db->field_exists('zone', db_prefix() . 'invoices')) {
            $this->db->query(
                'ALTER TABLE ' . db_prefix() . 'invoices DROP COLUMN zone'
            );
        }

        if ($this->db->field_exists('priority', db_prefix() . 'invoices')) {
            $this->db->query(
                'ALTER TABLE ' . db_prefix() . 'invoices DROP COLUMN priority'
            );
        }
    }
}
