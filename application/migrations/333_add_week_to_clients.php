<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Add_week_to_clients extends CI_Migration
{
    public function up()
    {
        $table = db_prefix() . 'clients';

        if (!$this->db->field_exists('week', $table)) {
            $this->db->query(
                "ALTER TABLE `{$table}` ADD COLUMN `week` TINYINT(1) NOT NULL DEFAULT 0 AFTER `active`"
            );
        }
    }

    public function down()
    {
        $table = db_prefix() . 'clients';

        if ($this->db->field_exists('week', $table)) {
            $this->db->query("ALTER TABLE `{$table}` DROP COLUMN `week`");
        }
    }
}
