<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Entrada_inventario_model extends App_Model
{
    private function t($n){ return db_prefix().$n; }

    public function ensure_tables()
    {
        $rec = $this->t('ei_receptions');
        $it  = $this->t('ei_reception_items');

        if (!$this->db->table_exists($rec)) {
            $this->db->query("CREATE TABLE `$rec` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `pur_order_id` INT(11) NOT NULL,
                `warehouse_id` INT(11) NOT NULL DEFAULT 1,
                `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
                `created_at` DATETIME NOT NULL,
                `posted_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `pur_order_id` (`pur_order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        } else {
            if (!$this->db->field_exists('posted_at', $rec)) {
                $this->db->query("ALTER TABLE `$rec` ADD COLUMN `posted_at` DATETIME NULL AFTER `created_at`;");
            }
        }

        if (!$this->db->table_exists($it)) {
            $this->db->query("CREATE TABLE `$it` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `reception_id` INT(11) NOT NULL,
                `item_code` VARCHAR(100) NOT NULL,
                `description` TEXT NULL,
                `qty_ordered` DECIMAL(15,4) NOT NULL DEFAULT 0,
                `qty_delivered` DECIMAL(15,4) NOT NULL DEFAULT 0,
                `qty_accepted` DECIMAL(15,4) NOT NULL DEFAULT 0,
                `applied` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `reception_id` (`reception_id`),
                KEY `item_code` (`item_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        } else {
            if (!$this->db->field_exists('qty_delivered', $it)) {
                $this->db->query("ALTER TABLE `$it` ADD COLUMN `qty_delivered` DECIMAL(15,4) NOT NULL DEFAULT 0 AFTER `qty_ordered`;");
            }
        }
    }

    public function ensure_moves_table()
    {
        $mv = $this->t('entrada_inventory_moves');
        if ($this->db->table_exists($mv)) return;

        $this->db->query("CREATE TABLE `$mv` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `reception_id` INT(11) NOT NULL,
            `warehouse` INT(11) NOT NULL,
            `item_code` VARCHAR(100) NOT NULL,
            `qty_in` DECIMAL(15,4) NOT NULL DEFAULT 0,
            `source` VARCHAR(50) NOT NULL,
            `source_id` INT(11) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `reception_id` (`reception_id`),
            KEY `item_code` (`item_code`),
            KEY `source` (`source`),
            KEY `source_id` (`source_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    }

    public function list_pos()
    {
        $this->ensure_tables();
        $tbl = $this->t('pur_orders');
        if (!$this->db->table_exists($tbl)) return [];
        // vendor may not exist; select * safest with limit columns
        $cols = ['id'];
        if ($this->db->field_exists('vendor', $tbl)) $cols[]='vendor';
        return $this->db->select(implode(',', $cols))->order_by('id','DESC')->get($tbl)->result_array();
    }

    public function list_receptions()
    {
        $this->ensure_tables();
        return $this->db->order_by('id','DESC')->get($this->t('ei_receptions'))->result_array();
    }

    public function get_po($po_id)
    {
        $tbl = $this->t('pur_orders');
        if (!$this->db->table_exists($tbl)) return null;
        return $this->db->where('id', (int)$po_id)->get($tbl)->row_array();
    }

    public function get_vendor_name_from_po($po_id)
    {
        $po = $this->get_po($po_id);
        if (!$po) return null;

        $vendor_id = isset($po['vendor']) ? (int)$po['vendor'] : 0;
        if ($vendor_id <= 0) return null;

        $candidates = [
            $this->t('pur_vendor'),
            $this->t('pur_vendors'),
            $this->t('vendors'),
            $this->t('vendor'),
        ];

        foreach ($candidates as $t) {
            if (!$this->db->table_exists($t)) continue;

            $has_company = $this->db->field_exists('company', $t);
            $has_name    = $this->db->field_exists('name', $t);
            $id_field    = $this->db->field_exists('id', $t) ? 'id' : ($this->db->field_exists('userid', $t) ? 'userid' : null);

            if (!$id_field) continue;

            $row = $this->db->where($id_field, $vendor_id)->get($t)->row_array();
            if (!$row) continue;

            if ($has_company && !empty($row['company'])) return $row['company'];
            if ($has_name && !empty($row['name'])) return $row['name'];
        }

        return 'Proveedor #' . $vendor_id;
    }

    public function create_from_po($po_id)
    {
        $this->ensure_tables();
        $po_id = (int)$po_id;
        if ($po_id <= 0) return 0;

        $rec_tbl = $this->t('ei_receptions');
        $it_tbl  = $this->t('ei_reception_items');

        $this->db->insert($rec_tbl, [
            'pur_order_id' => $po_id,
            'warehouse_id' => 1,
            'status'       => 'draft',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
        $rid = (int)$this->db->insert_id();
        if ($rid <= 0) return 0;

        $pod_tbl = $this->t('pur_order_detail');
        if (!$this->db->table_exists($pod_tbl)) return $rid;

        $link_col = $this->db->field_exists('pur_order',$pod_tbl) ? 'pur_order' : ($this->db->field_exists('pur_order_id',$pod_tbl) ? 'pur_order_id' : null);
        if (!$link_col) return $rid;

        $has_item_code = $this->db->field_exists('item_code', $pod_tbl);
        $has_qty       = $this->db->field_exists('quantity', $pod_tbl);
        $has_desc      = $this->db->field_exists('description', $pod_tbl);

        $rows = $this->db->where($link_col, $po_id)->get($pod_tbl)->result_array();
        foreach ($rows as $r) {
            $code = $has_item_code ? (string)$r['item_code'] : '';
            $qty  = $has_qty ? (float)$r['quantity'] : 0.0;
            $desc = $has_desc ? (string)$r['description'] : '';

            $this->db->insert($it_tbl, [
                'reception_id'  => $rid,
                'item_code'     => $code,
                'description'   => $desc,
                'qty_ordered'   => $qty,
                'qty_delivered' => $qty,
                'qty_accepted'  => $qty,
                'applied'       => 0
            ]);
        }
        return $rid;
    }

    
    public function get_commodity_name($item_code)
    {
        $tbl = $this->t('inventory_commodity_min');
        if (!$this->db->table_exists($tbl)) return null;

        $name_field = $this->db->field_exists('commodity_name', $tbl) ? 'commodity_name' : null;
        if (!$name_field) return null;

        $row = null;
        if ($this->db->field_exists('commodity_id', $tbl)) {
            $row = $this->db->where('commodity_id', $item_code)->get($tbl)->row_array();
        }
        if (!$row && $this->db->field_exists('id', $tbl)) {
            $row = $this->db->where('id', $item_code)->get($tbl)->row_array();
        }
        if (!$row) return null;

        return !empty($row[$name_field]) ? $row[$name_field] : null;
    }

public function inventory_before_after($item_code, $qty_accepted)
    {
        $inv_tbl = $this->t('inventory_manage');
        $before = 0.0;

        if ($this->db->table_exists($inv_tbl)
            && $this->db->field_exists('warehouse_id', $inv_tbl)
            && $this->db->field_exists('commodity_id', $inv_tbl)
            && $this->db->field_exists('inventory_number', $inv_tbl)) {

            $this->db->select_sum('inventory_number', 'q');
            $this->db->where('warehouse_id', 1);
            $this->db->where('commodity_id', $item_code);
            $r = $this->db->get($inv_tbl)->row_array();
            $before = ($r && $r['q'] !== null) ? (float)$r['q'] : 0.0;
        }

        return ['before'=>$before, 'after'=>$before + (float)$qty_accepted];
    }

    public function get_reception($rid)
    {
        $this->ensure_tables();
        $rid = (int)$rid;

        $rec_tbl = $this->t('ei_receptions');
        $it_tbl  = $this->t('ei_reception_items');

        $rec = $this->db->where('id', $rid)->get($rec_tbl)->row_array();
        if (!$rec) return false;

        $rec['vendor_name'] = $this->get_vendor_name_from_po((int)$rec['pur_order_id']);

        $items = $this->db->where('reception_id', $rid)->order_by('id','ASC')->get($it_tbl)->result_array();
        foreach ($items as &$it) {
            $it['commodity_name'] = $this->get_commodity_name((string)$it['item_code']);
            $ba = $this->inventory_before_after((string)$it['item_code'], (float)$it['qty_accepted']);
            $it['inv_before'] = $ba['before'];
            $it['inv_after']  = $ba['after'];
        }
        $rec['items'] = $items;
        return $rec;
    }

    public function update_item_qty($item_id, $delivered, $accepted)
    {
        $this->ensure_tables();
        $it_tbl = $this->t('ei_reception_items');
        $this->db->where('id', (int)$item_id)->update($it_tbl, [
            'qty_delivered' => max(0, (float)$delivered),
            'qty_accepted'  => max(0, (float)$accepted),
        ]);
        return true;
    }

    public function apply_item($item_id, &$err=null)
    {
        $this->ensure_tables();
        $this->ensure_moves_table();
        $err = null;

        $it_tbl  = $this->t('ei_reception_items');
        $rec_tbl = $this->t('ei_receptions');
        $inv_tbl = $this->t('inventory_manage');
        $mv_tbl  = $this->t('entrada_inventory_moves');

        $it = $this->db->where('id',(int)$item_id)->get($it_tbl)->row_array();
        if (!$it) { $err='missing_item'; return false; }
        if ((int)$it['applied'] === 1) return true;

        $rid = (int)$it['reception_id'];
        $rec = $this->db->where('id',$rid)->get($rec_tbl)->row_array();
        if (!$rec) { $err='missing_reception'; return false; }

        $po_id = (int)$rec['pur_order_id'];
        $code  = (string)$it['item_code'];
        $qty   = (float)$it['qty_accepted'];

        $row = $this->db->where('warehouse_id',1)->where('commodity_id',$code)->order_by('id','DESC')->limit(1)->get($inv_tbl)->row_array();
        if ($row) {
            $cur = (float)$row['inventory_number'];
            $this->db->where('id',(int)$row['id'])->update($inv_tbl, ['inventory_number' => $cur + $qty]);
        } else {
            $this->db->insert($inv_tbl, ['warehouse_id'=>1,'commodity_id'=>$code,'inventory_number'=>$qty]);
        }

        $this->db->insert($mv_tbl, [
            'reception_id' => $rid,
            'warehouse'    => 1,
            'item_code'    => $code,
            'qty_in'       => $qty,
            'source'       => 'oc',
            'source_id'    => $po_id,
            'created_at'   => date('Y-m-d H:i:s')
        ]);

        $this->db->where('id',(int)$item_id)->update($it_tbl, ['applied'=>1]);

        $pending = $this->db->where('reception_id',$rid)->where('applied',0)->count_all_results($it_tbl);
        if ($pending == 0) {
            $this->db->where('id',$rid)->update($rec_tbl, ['status'=>'posted','posted_at'=>date('Y-m-d H:i:s')]);
        }
        return true;
    }

    public function apply_all($rid, &$err=null)
    {
        $this->ensure_tables();
        $err = null;
        $it_tbl = $this->t('ei_reception_items');
        $items = $this->db->where('reception_id',(int)$rid)->get($it_tbl)->result_array();
        foreach ($items as $it) {
            $e=null;
            $this->apply_item((int)$it['id'], $e);
            if ($e && !$err) $err = $e;
        }
        return true;
    }
}
