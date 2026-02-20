<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Pedidos_vs_inventario_model extends App_Model
{
    private $vendor_item_cols = null;

    public function __construct()
    {
        parent::__construct();
    }

    private function norm($s)
    {
        $s = strtolower(trim((string)$s));
        $s = preg_replace('/\s+/', ' ', $s);
        return $s ?: '';
    }

    private function detect_col($table, $candidates)
    {
        foreach ($candidates as $c) {
            if ($this->db->field_exists($c, $table)) return $c;
        }
        return null;
    }

    private function detect_col_fuzzy($fields, $needles)
    {
        foreach ($fields as $f) {
            $lf = strtolower($f);
            foreach ($needles as $n) {
                if (strpos($lf, strtolower($n)) !== false) return $f;
            }
        }
        return null;
    }

    private function ensure_vendor_items_columns()
    {
        if (!$this->db->table_exists('tblpur_vendor_items')) return;
        if (!$this->db->field_exists('priority', 'tblpur_vendor_items')) {
            try { $this->db->query("ALTER TABLE `tblpur_vendor_items` ADD COLUMN `priority` INT(11) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
        }
        if (!$this->db->field_exists('purchase_price', 'tblpur_vendor_items')) {
            try { $this->db->query("ALTER TABLE `tblpur_vendor_items` ADD COLUMN `purchase_price` DECIMAL(18,2) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
        }
    }

    private function detect_vendor_item_cols()
    {
        if ($this->vendor_item_cols !== null) return $this->vendor_item_cols;

        $cols = array('vendorIdCol'=>null,'itemCodeCol'=>null,'fields'=>array());
        if (!$this->db->table_exists('tblpur_vendor_items')) { $this->vendor_item_cols = $cols; return $cols; }

        $fields = $this->db->list_fields('tblpur_vendor_items');
        $cols['fields'] = $fields;

        $vendorIdCol = $this->detect_col('tblpur_vendor_items', array('vendor','vendor_id','supplier_id','pur_vendor','pur_vendor_id','vendorid','supplier'));
        $itemCodeCol = $this->detect_col('tblpur_vendor_items', array('item_code','commodity_id','commodity_code','itemid','item_id','product_id','product','itemcode','code'));

        if (!$vendorIdCol) $vendorIdCol = $this->detect_col_fuzzy($fields, array('vendor','supplier'));
        if (!$itemCodeCol) $itemCodeCol = $this->detect_col_fuzzy($fields, array('item','commodity','product','code'));

        $cols['vendorIdCol'] = $vendorIdCol;
        $cols['itemCodeCol'] = $itemCodeCol;

        $this->vendor_item_cols = $cols;
        return $cols;
    }

    private function get_vendor_name_map()
    {
        $map = array();
        if (!$this->db->table_exists('tblpur_vendor')) return $map;

        $vFields = $this->db->list_fields('tblpur_vendor');
        $vendorPkCol = $this->detect_col('tblpur_vendor', array('id','vendor_id','supplier_id','pur_vendor_id'));
        if (!$vendorPkCol) $vendorPkCol = $this->detect_col_fuzzy($vFields, array('id','vendor'));

        $vendorNameCol = $this->detect_col('tblpur_vendor', array('company','name','vendor_name','supplier_name'));

        if ($vendorPkCol && $vendorNameCol) {
            $rows = $this->db->query("SELECT `$vendorPkCol` as vid, `$vendorNameCol` as vname FROM tblpur_vendor")->result_array();
            foreach ($rows as $r) $map[(int)$r['vid']] = (string)$r['vname'];
        }
        return $map;
    }

    private function get_product_name_map()
    {
        $map = array();
        if ($this->db->table_exists('tblinventory_commodity_min')
            && $this->db->field_exists('commodity_id','tblinventory_commodity_min')
            && $this->db->field_exists('commodity_name','tblinventory_commodity_min')) {
            $rows = $this->db->query("SELECT commodity_id, commodity_name FROM tblinventory_commodity_min")->result_array();
            foreach ($rows as $r) $map[(int)$r['commodity_id']] = (string)$r['commodity_name'];
        }
        return $map;
    }

    public function get_vendor_item_purchase_price($vendorId, $itemCode)
    {
        $this->ensure_vendor_items_columns();
        $res = array('ok'=>false,'purchase_price'=>0,'error'=>null);

        if (!$this->db->table_exists('tblpur_vendor_items')) { $res['error']='No existe tblpur_vendor_items'; return $res; }
        $cols = $this->detect_vendor_item_cols();
        if (!$cols['vendorIdCol'] || !$cols['itemCodeCol']) { $res['error']='No se detectaron columnas vendor/item_code'; return $res; }

        $this->db->select('purchase_price');
        $this->db->from('tblpur_vendor_items');
        $this->db->where($cols['vendorIdCol'], (int)$vendorId);
        $this->db->where($cols['itemCodeCol'], (string)$itemCode);
        $this->db->limit(1);
        $row = $this->db->get()->row_array();

        $res['ok'] = true;
        $res['purchase_price'] = $row ? (float)$row['purchase_price'] : 0;
        return $res;
    }

    public function save_vendor_item_purchase_price($vendorId, $itemCode, $price)
    {
        $this->ensure_vendor_items_columns();
        $res = array('ok'=>false,'updated'=>0,'error'=>null);

        if (!$this->db->table_exists('tblpur_vendor_items')) { $res['error']='No existe tblpur_vendor_items'; return $res; }
        $cols = $this->detect_vendor_item_cols();
        if (!$cols['vendorIdCol'] || !$cols['itemCodeCol']) { $res['error']='No se detectaron columnas vendor/item_code'; return $res; }
        if ((int)$vendorId <= 0 || $itemCode === null || $itemCode === '') { $res['error']='vendor_id/item_code requeridos'; return $res; }

        $this->db->where($cols['vendorIdCol'], (int)$vendorId);
        $this->db->where($cols['itemCodeCol'], (string)$itemCode);
        $this->db->update('tblpur_vendor_items', array('purchase_price'=>(float)$price));

        $res['updated'] = (int)$this->db->affected_rows();
        $res['ok'] = true;
        return $res;
    }

    public function build_report()
    {
        if (!$this->db->table_exists('tblcart_detailt')) return array();

        $nameCol = $this->detect_col('tblcart_detailt', array('product_name','item_name','name','description'));
        $qtyCol  = $this->detect_col('tblcart_detailt', array('quantity','qty','qnt','cantidad','item_qty'));
        if (!$nameCol || !$qtyCol) return array();

        // Orders aggregation
        $this->db->select("d.$nameCol as item_name, SUM(d.$qtyCol) as qty_orders", false);
        $this->db->from("tblcart_detailt d");
        $this->db->group_by("d.$nameCol");
        $orders = $this->db->get()->result_array();
        if (!$orders) return array();

        // Inventory map & name->code map
        $invMap = array();
        $nameToCode = array();

        if ($this->db->table_exists('tblinventory_manage') && $this->db->table_exists('tblinventory_commodity_min')
            && $this->db->field_exists('inventory_number','tblinventory_manage')
            && $this->db->field_exists('commodity_id','tblinventory_manage')
            && $this->db->field_exists('commodity_id','tblinventory_commodity_min')
            && $this->db->field_exists('commodity_name','tblinventory_commodity_min')) {

            $cmRows = $this->db->query("SELECT commodity_id, commodity_name FROM tblinventory_commodity_min")->result_array();
            foreach ($cmRows as $cr) $nameToCode[$this->norm((string)$cr['commodity_name'])] = (int)$cr['commodity_id'];

            $this->db->select("cm.commodity_name,
                SUM(CAST(REPLACE(REPLACE(im.inventory_number, ',', ''), ' ', '') AS DECIMAL(18,2))) as qty_inventory", false);
            $this->db->from("tblinventory_manage im");
            $this->db->join("tblinventory_commodity_min cm", "cm.commodity_id = im.commodity_id", "inner");
            $this->db->group_by("cm.commodity_name");
            $inv = $this->db->get()->result_array();
            foreach ($inv as $row) $invMap[$this->norm((string)$row['commodity_name'])] = (float)$row['qty_inventory'];
        }

        $out=array();
        foreach($orders as $o){
            $name=(string)$o['item_name'];
            $k=$this->norm($name);
            $qOrders=(float)$o['qty_orders'];
            $qInv=isset($invMap[$k])?(float)$invMap[$k]:0.0;
            $itemCode=isset($nameToCode[$k])?(int)$nameToCode[$k]:0;

            $best = ($itemCode>0) ? $this->get_best_vendor_for_itemcode($itemCode) : null;

            $out[] = array(
                'item_name'=>$name,
                'item_code'=>$itemCode ? (string)$itemCode : '',
                'qty_orders'=>$qOrders,
                'qty_inventory'=>$qInv,
                'qty_missing'=>max(0,$qOrders-$qInv),
                'best_vendor_id'=>$best['vendor_id'] ?? null,
                'best_vendor_name'=>$best['vendor_name'] ?? '',
                'best_vendor_priority'=>$best['priority'] ?? null,
                'best_vendor_purchase_price'=>$best['purchase_price'] ?? null,
            );
        }
        return $out;
    }

    private function get_best_vendor_for_itemcode($itemCode)
    {
        $this->ensure_vendor_items_columns();
        if (!$this->db->table_exists('tblpur_vendor_items')) return null;

        $cols = $this->detect_vendor_item_cols();
        if (!$cols['vendorIdCol'] || !$cols['itemCodeCol']) return null;

        $vendorIdCol = $cols['vendorIdCol'];
        $itemCodeCol = $cols['itemCodeCol'];

        $this->db->from('tblpur_vendor_items');
        $this->db->where($itemCodeCol, (string)$itemCode);
        $this->db->order_by('priority', 'DESC');
        $this->db->order_by($vendorIdCol, 'ASC');
        $this->db->limit(1);
        $vi = $this->db->get()->row_array();
        if (!$vi) return null;

        $vendorId=(int)$vi[$vendorIdCol];
        $vendorNameMap=$this->get_vendor_name_map();

        return array(
            'vendor_id'=>$vendorId,
            'vendor_name'=>$vendorNameMap[$vendorId] ?? ('Proveedor '.$vendorId),
            'priority'=>(int)($vi['priority'] ?? 0),
            'purchase_price'=>(float)($vi['purchase_price'] ?? 0),
        );
    }

    public function get_vendor_items_with_priority_deduped()
    {
        $this->ensure_vendor_items_columns();
        $out = array('rows'=>array(), 'error'=>null);
        if (!$this->db->table_exists('tblpur_vendor_items')) { $out['error']='No existe la tabla tblpur_vendor_items'; return $out; }

        $cols = $this->detect_vendor_item_cols();
        if (!$cols['vendorIdCol'] || !$cols['itemCodeCol']) { $out['error']='No se detectaron columnas vendor/item_code en tblpur_vendor_items'; return $out; }

        $vendorNameMap = $this->get_vendor_name_map();
        $productNameMap = $this->get_product_name_map();

        $vendorIdCol = $cols['vendorIdCol'];
        $itemCodeCol = $cols['itemCodeCol'];

        $sql = "SELECT `$vendorIdCol` as vendor_id, `$itemCodeCol` as item_code, MAX(priority) as priority, MAX(purchase_price) as purchase_price, COUNT(*) as row_count
                FROM tblpur_vendor_items
                GROUP BY `$vendorIdCol`, `$itemCodeCol`
                ORDER BY `$vendorIdCol`, `$itemCodeCol`";
        $pairs = $this->db->query($sql)->result_array();

        foreach ($pairs as $p) {
            $vendorId = (int)$p['vendor_id'];
            $itemCodeRaw = (string)$p['item_code'];
            $itemCode = (int)$itemCodeRaw;

            $vendorName = $vendorNameMap[$vendorId] ?? ('Proveedor '.$vendorId);
            $productName = $productNameMap[$itemCode] ?? ('Item '.$itemCode);

            $out['rows'][] = array(
                'key' => $vendorId.'|'.$itemCodeRaw,
                'vendor_id' => $vendorId,
                'vendor_name' => $vendorName.' ('.$vendorId.')',
                'item_code' => $itemCodeRaw,
                'product_name' => $productName,
                'priority' => (int)$p['priority'],
                'purchase_price' => (float)$p['purchase_price'],
                'row_count' => (int)$p['row_count'],
            );
        }
        return $out;
    }

    public function save_vendor_item_priorities_deduped($posted)
    {
        $this->ensure_vendor_items_columns();
        $res = array('ok'=>false,'updated'=>0,'error'=>null);
        if (!$this->db->table_exists('tblpur_vendor_items')) { $res['error']='No existe la tabla tblpur_vendor_items'; return $res; }
        if (!is_array($posted)) { $res['error']='Sin datos'; return $res; }

        $cols = $this->detect_vendor_item_cols();
        if (!$cols['vendorIdCol'] || !$cols['itemCodeCol']) { $res['error']='No se detectaron columnas vendor/item_code para guardar'; return $res; }

        foreach ($posted as $key => $prio) {
            $parts = explode('|', (string)$key, 2);
            if (count($parts) !== 2) continue;
            $vendorId = (int)$parts[0];
            $itemCode = $parts[1];

            $this->db->where($cols['vendorIdCol'], $vendorId);
            $this->db->where($cols['itemCodeCol'], $itemCode);
            $this->db->update('tblpur_vendor_items', array('priority'=>(int)$prio));
            $res['updated'] += (int)$this->db->affected_rows();
        }
        $res['ok'] = true;
        return $res;
    }

    private function detect_pur_detail_table()
    {
        foreach (array('tblpur_order_detail','tblpur_orders_detail') as $t) if ($this->db->table_exists($t)) return $t;
        return null;
    }

    public function create_purchase_orders_by_product($missingRows)
    {
        $this->ensure_vendor_items_columns();
        $result = array('ok'=>false,'detail_table'=>null,'created'=>array(),'unmapped'=>array(),'error'=>null);

        if (!$this->db->table_exists('tblpur_orders')) { $result['error']='No existe la tabla tblpur_orders'; return $result; }
        $detailTable = $this->detect_pur_detail_table();
        $result['detail_table'] = $detailTable;
        if (!$detailTable) { $result['error']='No se encontró tabla detalle de compras'; return $result; }

        $vendorNameMap = $this->get_vendor_name_map();

        foreach ($missingRows as $r) {
            $nm = (string)($r['item_name'] ?? '');
            $qty = (float)($r['qty_missing'] ?? 0);
            if ($qty <= 0) continue;

            $itemCode = (int)($r['item_code'] ?? 0);
            if ($itemCode <= 0) { $result['unmapped'][] = array('item_name'=>$nm,'qty'=>$qty,'reason'=>'no_item_code'); continue; }

            $best = $this->get_best_vendor_for_itemcode($itemCode);
            if (!$best || empty($best['vendor_id'])) {
                $result['unmapped'][] = array('item_name'=>$nm,'item_code'=>$itemCode,'qty'=>$qty,'reason'=>'no_vendor_for_item');
                continue;
            }

            $vendorId = (int)$best['vendor_id'];
            $vendorPriority = (int)($best['priority'] ?? 0);
            $unitPrice = (float)($best['purchase_price'] ?? 0);
            $lineTotal = $qty * $unitPrice;

            $po = array();
            if ($this->db->field_exists('pur_order_number','tblpur_orders')) $po['pur_order_number'] = 'PVI-'.date('Ymd-His').'-'.$itemCode;
            if ($this->db->field_exists('order_date','tblpur_orders')) $po['order_date'] = date('Y-m-d');
            if ($this->db->field_exists('datecreated','tblpur_orders')) $po['datecreated'] = date('Y-m-d H:i:s');
            if ($this->db->field_exists('addedfrom','tblpur_orders')) $po['addedfrom'] = function_exists('get_staff_user_id') ? get_staff_user_id() : 0;
            if ($this->db->field_exists('status','tblpur_orders')) $po['status'] = 0;
            if ($this->db->field_exists('vendor','tblpur_orders')) $po['vendor'] = $vendorId;
            if ($this->db->field_exists('pur_order_name','tblpur_orders')) $po['pur_order_name'] = 'PVI - '.$nm;
            if ($this->db->field_exists('subtotal','tblpur_orders')) $po['subtotal'] = $lineTotal;
            if ($this->db->field_exists('total','tblpur_orders')) $po['total'] = $lineTotal;

            $this->db->insert('tblpur_orders', $po);
            $poId = (int)$this->db->insert_id();

            $detail = array();
            if ($this->db->field_exists('pur_order', $detailTable)) $detail['pur_order'] = $poId;
            elseif ($this->db->field_exists('pur_order_id', $detailTable)) $detail['pur_order_id'] = $poId;

            if ($this->db->field_exists('item_code', $detailTable)) $detail['item_code'] = $itemCode;
            if ($this->db->field_exists('quantity', $detailTable)) $detail['quantity'] = $qty;
            if ($this->db->field_exists('unit_price', $detailTable)) $detail['unit_price'] = $unitPrice;
            if ($this->db->field_exists('total', $detailTable)) $detail['total'] = $lineTotal;

            $this->db->insert($detailTable, $detail);

            $result['created'][] = array(
                'po_id'=>$poId,
                'vendor_id'=>$vendorId,
                'vendor_name'=>$vendorNameMap[$vendorId] ?? ('Proveedor '.$vendorId),
                'vendor_priority'=>$vendorPriority,
                'item_name'=>$nm,
                'item_code'=>$itemCode,
                'quantity'=>$qty,
                'unit_price'=>$unitPrice,
                'line_total'=>$lineTotal,
            );
        }

        $result['ok'] = true;
        return $result;
    }
}
