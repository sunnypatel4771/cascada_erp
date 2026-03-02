<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_331 extends CI_Migration
{
    public function up(): void
    {
        // Add columns to tblinvoices for ERP order automation tracking
        $table = db_prefix() . 'invoices';

        // Check if columns already exist before adding
        if (!$this->db->field_exists('processed_for_purchase', $table)) {
            $this->db->query("ALTER TABLE `{$table}` ADD COLUMN `processed_for_purchase` TINYINT(1) DEFAULT 0 AFTER `status`");
        }

        if (!$this->db->field_exists('automation_run_id', $table)) {
            $this->db->query("ALTER TABLE `{$table}` ADD COLUMN `automation_run_id` INT NULL AFTER `processed_for_purchase`");
        }
    }

    public function down(): void
    {
        $table = db_prefix() . 'invoices';

        if ($this->db->field_exists('automation_run_id', $table)) {
            $this->db->query("ALTER TABLE `{$table}` DROP COLUMN `automation_run_id`");
        }

        if ($this->db->field_exists('processed_for_purchase', $table)) {
            $this->db->query("ALTER TABLE `{$table}` DROP COLUMN `processed_for_purchase`");
        }
    }
}
