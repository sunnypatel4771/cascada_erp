<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_151 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'pur_vendor_items') && !$CI->db->field_exists('priority', db_prefix() . 'pur_vendor_items')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . "pur_vendor_items`
                ADD COLUMN `priority` int(11) NOT NULL DEFAULT 0 AFTER `items`
            ;");
        }
    }
}
