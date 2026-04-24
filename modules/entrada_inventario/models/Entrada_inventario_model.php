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
            $this->db->query("CREATE TABLE IF NOT EXISTS `$rec` (
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
            $this->db->query("CREATE TABLE IF NOT EXISTS `$it` (
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

        $this->db->query("CREATE TABLE IF NOT EXISTS `$mv` (
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

        $cols = ['id'];
        if ($this->db->field_exists('vendor', $tbl)) $cols[] = 'vendor';
        if ($this->db->field_exists('date', $tbl)) $cols[] = 'date';
        if ($this->db->field_exists('datecreated', $tbl)) $cols[] = 'datecreated';
        if ($this->db->field_exists('created_at', $tbl)) $cols[] = 'created_at';
        if ($this->db->field_exists('pur_order_number', $tbl)) $cols[] = 'pur_order_number';

        $this->db->select(implode(',', $cols));

        if ($this->db->field_exists('datecreated', $tbl)) {
            $this->db->order_by('datecreated', 'DESC');
        } elseif ($this->db->field_exists('date', $tbl)) {
            $this->db->order_by('date', 'DESC');
        } elseif ($this->db->field_exists('created_at', $tbl)) {
            $this->db->order_by('created_at', 'DESC');
        } else {
            $this->db->order_by('id', 'DESC');
        }

        $rows = $this->db->get($tbl)->result_array();
        foreach ($rows as &$r) {
            $r['vendor_name'] = $this->get_vendor_name_from_po((int)$r['id']);
            if (isset($r['date']) && !empty($r['date'])) $r['po_date'] = $r['date'];
            elseif (isset($r['datecreated']) && !empty($r['datecreated'])) $r['po_date'] = $r['datecreated'];
            elseif (isset($r['created_at']) && !empty($r['created_at'])) $r['po_date'] = $r['created_at'];
            else $r['po_date'] = null;
        }
        return $rows;
    }

    public function list_receptions($filters = [])
    {
        $this->ensure_tables();
        $rec_tbl = $this->t('ei_receptions');
        $po_tbl  = $this->t('pur_orders');

        $this->db->from($rec_tbl);
        $this->db->select($rec_tbl.'.*');

        if ($this->db->table_exists($po_tbl)) {
            $this->db->join($po_tbl, $po_tbl.'.id = '.$rec_tbl.'.pur_order_id', 'left');

            if ($this->db->field_exists('date', $po_tbl)) {
                $this->db->select($po_tbl.'.date as po_date');
            } elseif ($this->db->field_exists('datecreated', $po_tbl)) {
                $this->db->select($po_tbl.'.datecreated as po_date');
            } elseif ($this->db->field_exists('created_at', $po_tbl)) {
                $this->db->select($po_tbl.'.created_at as po_date');
            }

            if ($this->db->field_exists('pur_order_number', $po_tbl)) {
                $this->db->select($po_tbl.'.pur_order_number as pur_order_number');
            }

            if (!empty($filters['date_from'])) {
                if ($this->db->field_exists('date', $po_tbl)) {
                    $this->db->where($po_tbl.'.date >=', $filters['date_from']);
                } elseif ($this->db->field_exists('datecreated', $po_tbl)) {
                    $this->db->where($po_tbl.'.datecreated >=', $filters['date_from']);
                } elseif ($this->db->field_exists('created_at', $po_tbl)) {
                    $this->db->where($po_tbl.'.created_at >=', $filters['date_from']);
                }
            }

            if (!empty($filters['date_to'])) {
                if ($this->db->field_exists('date', $po_tbl)) {
                    $this->db->where($po_tbl.'.date <=', $filters['date_to']);
                } elseif ($this->db->field_exists('datecreated', $po_tbl)) {
                    $this->db->where($po_tbl.'.datecreated <=', $filters['date_to'].' 23:59:59');
                } elseif ($this->db->field_exists('created_at', $po_tbl)) {
                    $this->db->where($po_tbl.'.created_at <=', $filters['date_to'].' 23:59:59');
                }
            }

            if ($this->db->field_exists('datecreated', $po_tbl)) {
                $this->db->order_by($po_tbl.'.datecreated', 'DESC');
            } elseif ($this->db->field_exists('date', $po_tbl)) {
                $this->db->order_by($po_tbl.'.date', 'DESC');
            } elseif ($this->db->field_exists('created_at', $po_tbl)) {
                $this->db->order_by($po_tbl.'.created_at', 'DESC');
            } else {
                $this->db->order_by($rec_tbl.'.id', 'DESC');
            }
        } else {
            $this->db->order_by($rec_tbl.'.id', 'DESC');
        }

        $rows = $this->db->get()->result_array();
        foreach ($rows as &$r) {
            $r['vendor_name'] = $this->get_vendor_name_from_po((int)$r['pur_order_id']);
            $r['items_total'] = $this->count_reception_items((int)$r['id']);
            $r['items_applied'] = $this->count_reception_items_applied((int)$r['id']);
        }
        return $rows;
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
            $this->t('clients'),
        ];

        foreach ($candidates as $t) {
            if (!$this->db->table_exists($t)) continue;

            $id_field = null;
            foreach (['id','userid','vendor_id'] as $cand) {
                if ($this->db->field_exists($cand, $t)) { $id_field = $cand; break; }
            }
            if (!$id_field) continue;

            $row = $this->db->where($id_field, $vendor_id)->get($t)->row_array();
            if (!$row) continue;

            foreach (['company','company_name','name','vendor_name','trade_name'] as $nf) {
                if (isset($row[$nf]) && !empty($row[$nf])) return $row[$nf];
            }
        }

        return 'Proveedor #' . $vendor_id;
    }

    
    public function get_po_display_date($po_id)
    {
        $po = $this->get_po($po_id);
        if (!$po) return null;

        if (isset($po['date']) && !empty($po['date'])) return $po['date'];
        if (isset($po['datecreated']) && !empty($po['datecreated'])) return $po['datecreated'];
        if (isset($po['created_at']) && !empty($po['created_at'])) return $po['created_at'];

        return null;
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
        $rec['po_date'] = $this->get_po_display_date((int)$rec['pur_order_id']);
        $po = $this->get_po((int)$rec['pur_order_id']);
        $rec['pur_order_number'] = isset($po['pur_order_number']) ? $po['pur_order_number'] : null;

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
        $this->ensure_real_warehouse_entry_table();
        $err = null;

        $it_tbl  = $this->t('ei_reception_items');
        $rec_tbl = $this->t('ei_receptions');

        $it = $this->db->where('id',(int)$item_id)->get($it_tbl)->row_array();
        if (!$it) { $err='missing_item'; return false; }
        if ((int)$it['applied'] === 1) return true;

        $rid = (int)$it['reception_id'];
        $rec = $this->db->where('id',$rid)->get($rec_tbl)->row_array();
        if (!$rec) { $err='missing_reception'; return false; }

        $po_id = (int)$rec['pur_order_id'];
        $code  = (string)$it['item_code'];
        $qty   = (float)$it['qty_accepted'];

        if ($qty <= 0) { $err='qty_not_positive'; return false; }

        $this->db->trans_begin();

        $inv_result = $this->increase_inventory_precisely($code, $qty);
        if ($inv_result !== true) {
            $this->db->trans_rollback();
            $err = $inv_result;
            return false;
        }

        $mv_tbl = $this->t('entrada_inventory_moves');
        if ($this->db->table_exists($mv_tbl)) {
            $this->db->insert($mv_tbl, [
                'reception_id' => $rid,
                'warehouse'    => 1,
                'item_code'    => $code,
                'qty_in'       => $qty,
                'source'       => 'oc',
                'source_id'    => $po_id,
                'created_at'   => date('Y-m-d H:i:s')
            ]);
        }

        $this->register_real_warehouse_entry($rid, $code, $qty, $po_id);
        $this->register_inventory_history($rid, $code, $qty, $po_id);

        $this->db->where('id',(int)$item_id)->update($it_tbl, ['applied'=>1]);

        $pending = $this->db->where('reception_id',$rid)->where('applied',0)->count_all_results($it_tbl);
        if ($pending == 0) {
            $this->db->where('id',$rid)->update($rec_tbl, [
                'status'=>'posted',
                'posted_at'=>date('Y-m-d H:i:s')
            ]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            $err = 'transaction_failed';
            return false;
        }

        $this->db->trans_commit();
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

    public function count_reception_items($rid)
    {
        $this->ensure_tables();
        return (int)$this->db->where('reception_id', (int)$rid)->count_all_results($this->t('ei_reception_items'));
    }

    public function count_reception_items_applied($rid)
    {
        $this->ensure_tables();
        return (int)$this->db->where('reception_id', (int)$rid)->where('applied', 1)->count_all_results($this->t('ei_reception_items'));
    }

    public function reception_totals()
    {
        $this->ensure_tables();
        $rec_tbl = $this->t('ei_receptions');
        $it_tbl = $this->t('ei_reception_items');

        $total_receptions = (int)$this->db->count_all($rec_tbl);
        $posted = (int)$this->db->where('status', 'posted')->count_all_results($rec_tbl);
        $draft = (int)$this->db->where('status', 'draft')->count_all_results($rec_tbl);

        $total_qty = 0.0;
        if ($this->db->table_exists($it_tbl) && $this->db->field_exists('qty_accepted', $it_tbl)) {
            $this->db->select_sum('qty_accepted', 'qty_total');
            $row = $this->db->get($it_tbl)->row_array();
            $total_qty = ($row && $row['qty_total'] !== null) ? (float)$row['qty_total'] : 0.0;
        }

        return [
            'total_receptions' => $total_receptions,
            'posted' => $posted,
            'draft' => $draft,
            'total_qty' => $total_qty,
        ];
    }


    public function ensure_real_warehouse_entry_table()
    {
        $tbl = $this->t('entrada_inventory_real');
        if ($this->db->table_exists($tbl)) {
            return;
        }

        $this->db->query("CREATE TABLE IF NOT EXISTS `$tbl` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `reception_id` INT(11) NOT NULL,
            `warehouse_id` INT(11) NOT NULL DEFAULT 1,
            `item_code` VARCHAR(100) NOT NULL,
            `qty_in` DECIMAL(15,4) NOT NULL DEFAULT 0,
            `concept` VARCHAR(100) NOT NULL DEFAULT 'orden de compra',
            `source` VARCHAR(50) NOT NULL DEFAULT 'oc',
            `source_id` INT(11) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `reception_id` (`reception_id`),
            KEY `warehouse_id` (`warehouse_id`),
            KEY `item_code` (`item_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    }

    private function increase_inventory_precisely($item_code, $qty)
    {
        $inv_tbl = $this->t('inventory_manage');
        if (!$this->db->table_exists($inv_tbl)) {
            return 'missing_inventory_manage';
        }

        foreach (['warehouse_id','commodity_id','inventory_number'] as $f) {
            if (!$this->db->field_exists($f, $inv_tbl)) {
                return 'missing_field_'.$f;
            }
        }

        $qty = (float)$qty;
        if ($qty <= 0) {
            return 'qty_not_positive';
        }

        $rows = $this->db->where('warehouse_id', 1)
                         ->where('commodity_id', $item_code)
                         ->get($inv_tbl)
                         ->result_array();

        if (!empty($rows)) {
            $target = end($rows);
            $current = isset($target['inventory_number']) ? (float)$target['inventory_number'] : 0.0;
            $this->db->where('id', (int)$target['id'])->update($inv_tbl, [
                'inventory_number' => $current + $qty
            ]);
            return true;
        }

        $data = [
            'warehouse_id'     => 1,
            'commodity_id'     => $item_code,
            'inventory_number' => $qty
        ];

        if ($this->db->field_exists('description', $inv_tbl)) $data['description'] = 'orden de compra';
        if ($this->db->field_exists('datecreated', $inv_tbl)) $data['datecreated'] = date('Y-m-d H:i:s');
        if ($this->db->field_exists('created_at', $inv_tbl)) $data['created_at'] = date('Y-m-d H:i:s');

        $this->db->insert($inv_tbl, $data);
        return true;
    }

    private function register_real_warehouse_entry($reception_id, $item_code, $qty_in, $po_id)
    {
        $this->ensure_real_warehouse_entry_table();

        $tbl = $this->t('entrada_inventory_real');
        $this->db->insert($tbl, [
            'reception_id' => (int)$reception_id,
            'warehouse_id' => 1,
            'item_code'    => (string)$item_code,
            'qty_in'       => (float)$qty_in,
            'concept'      => 'orden de compra',
            'source'       => 'oc',
            'source_id'    => (int)$po_id,
            'created_at'   => date('Y-m-d H:i:s')
        ]);
    }



    private function register_inventory_history($reception_id, $item_code, $qty_in, $po_id)
    {
        $candidate_tables = [
            $this->t('inventory_history'),
            $this->t('inventory_histories'),
            $this->t('inventory_history_list'),
            $this->t('inventory_ledger'),
            $this->t('inventory_movements'),
            $this->t('entrada_inventory_moves'),
        ];

        $inserted = false;

        foreach ($candidate_tables as $tbl) {
            if (!$this->db->table_exists($tbl)) continue;

            $data = [];
            if ($this->db->field_exists('warehouse_id', $tbl)) $data['warehouse_id'] = 1;
            if ($this->db->field_exists('warehouse', $tbl)) $data['warehouse'] = 1;
            if ($this->db->field_exists('commodity_id', $tbl)) $data['commodity_id'] = (string)$item_code;
            if ($this->db->field_exists('item_code', $tbl)) $data['item_code'] = (string)$item_code;
            if ($this->db->field_exists('quantity', $tbl)) $data['quantity'] = (float)$qty_in;
            if ($this->db->field_exists('qty', $tbl)) $data['qty'] = (float)$qty_in;
            if ($this->db->field_exists('qty_in', $tbl)) $data['qty_in'] = (float)$qty_in;
            if ($this->db->field_exists('type', $tbl)) $data['type'] = 'in';
            if ($this->db->field_exists('movement_type', $tbl)) $data['movement_type'] = 'in';
            if ($this->db->field_exists('reference', $tbl)) $data['reference'] = 'Entrada al inventario procedente de compra';
            if ($this->db->field_exists('source', $tbl)) $data['source'] = 'oc';
            if ($this->db->field_exists('reference_id', $tbl)) $data['reference_id'] = (int)$po_id;
            if ($this->db->field_exists('source_id', $tbl)) $data['source_id'] = (int)$po_id;
            if ($this->db->field_exists('reception_id', $tbl)) $data['reception_id'] = (int)$reception_id;
            if ($this->db->field_exists('concept', $tbl)) $data['concept'] = 'orden de compra';
            if ($this->db->field_exists('description', $tbl)) $data['description'] = 'Entrada al inventario procedente de compra. OC ID: '.(int)$po_id;
            if ($this->db->field_exists('note', $tbl)) $data['note'] = 'Entrada al inventario procedente de compra. OC ID: '.(int)$po_id;
            if ($this->db->field_exists('created_at', $tbl)) $data['created_at'] = date('Y-m-d H:i:s');
            if ($this->db->field_exists('datecreated', $tbl)) $data['datecreated'] = date('Y-m-d H:i:s');

            if (!empty($data)) {
                $this->db->insert($tbl, $data);
                $inserted = true;
            }
        }

        return $inserted;
    }
}
