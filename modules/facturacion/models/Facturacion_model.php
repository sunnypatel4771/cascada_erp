<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Facturacion_model extends App_Model
{
    private $cart_table = 'tblcart';
    private $detail_table = 'tblcart_detailt';
    private $inv_min_table = 'tblinventory_commodity_min';
    private $inv_manage_table = 'tblinventory_manage';

    // Column mapping (based on your real schema)
    private $cart_pk = 'id';
    private $cart_userid = 'userid';
    private $cart_complete = 'complete';
    private $cart_create_invoice = 'create_invoice';
    private $cart_invoice_field = 'invoice';

    private $detail_pk = 'id';
    private $detail_cart_fk = 'cart_id';
    private $detail_product_fk = 'product_id';
    private $detail_qty = 'quantity';
    private $detail_product_name = 'product_name';
    private $detail_unit_name = 'unit_name';

    private $inv_manage_commodity_id = 'commodity_id';
    private $inv_manage_stock = 'inventory_number';
    private $inv_manage_purchase_price = 'purchase_price';

    public function env_check_tables()
    {
        $tables = [$this->cart_table, $this->detail_table, $this->inv_min_table, $this->inv_manage_table];
        $out = [];
        foreach ($tables as $t) {
            $out[$t] = $this->db->table_exists($t);
        }
        return $out;
    }

    public function get_ready_groups()
    {
        // Ready-to-invoice carts: complete=1 and create_invoice=0 (default)
        $this->db->from($this->cart_table);
        $this->db->where($this->cart_complete, 1);
        $this->db->where('IFNULL(' . $this->cart_create_invoice . ',0)=0', null, false);
        $this->db->order_by($this->cart_userid, 'ASC');
        $carts = $this->db->get()->result_array();

        // Group by client (userid)
        $groups = [];
        foreach ($carts as $c) {
            $uid = (int)($c[$this->cart_userid] ?? 0);
            if (!isset($groups[$uid])) {
                $groups[$uid] = [
                    'userid' => $uid,
                    'client_name' => $this->resolve_client_name($c),
                    'plist' => $this->get_client_plist($uid),
                    'rows' => [],
                ];
            }
            $cart_id = (int)$c[$this->cart_pk];
            $items = $this->get_cart_items_with_stock($cart_id);

            foreach ($items as $it) {
                $status = $this->row_status($uid, $c, $it);
                $groups[$uid]['rows'][] = array_merge($it, [
                    'cart_id' => $cart_id,
                    'order_number' => $c['order_number'] ?? $cart_id,
                    'status' => $status['status'],
                    'reason' => $status['reason'],
                    'rate_client' => $this->calc_client_price($groups[$uid]['plist'], $it['purchase_price']),
                ]);
            }
        }

        // remove groups without rows
        return array_values(array_filter($groups, function ($g) { return count($g['rows']) > 0; }));
    }

    private function resolve_client_name($cart_row)
    {
        // Prefer tblclients.company if possible
        $uid = (int)($cart_row[$this->cart_userid] ?? 0);
        if ($uid > 0 && $this->db->table_exists('tblclients')) {
            $client = $this->db->select('company')->from('tblclients')->where('userid', $uid)->get()->row_array();
            if ($client && !empty($client['company'])) {
                return $client['company'];
            }
        }
        // fallback to cart fields
        $fn = trim((string)($cart_row['first_name'] ?? ''));
        $ln = trim((string)($cart_row['last_name'] ?? ''));
        $co = trim((string)($cart_row['company'] ?? ''));
        if ($co !== '') return $co;
        $name = trim($fn . ' ' . $ln);
        return $name !== '' ? $name : ('Cliente #' . $uid);
    }

    public function get_client_plist($userid)
    {
        $userid = (int)$userid;
        if ($userid <= 0 || !$this->db->table_exists('tblclients')) {
            return 0.0;
        }
        $row = $this->db->select('plist')->from('tblclients')->where('userid', $userid)->get()->row_array();
        if (!$row || $row['plist'] === null || $row['plist'] === '') return 0.0;
        return (float)$row['plist'];
    }

    private function get_cart_items_with_stock($cart_id)
    {
        $cart_id = (int)$cart_id;
        $this->db->from($this->detail_table);
        $this->db->where($this->detail_cart_fk, $cart_id);
        $details = $this->db->get()->result_array();

        $product_ids = [];
        foreach ($details as $d) {
            $product_ids[] = (int)$d[$this->detail_product_fk];
        }
        $product_ids = array_values(array_unique(array_filter($product_ids)));

        $inv = [];
        if (count($product_ids) > 0) {
            $this->db->from($this->inv_manage_table);
            $this->db->where_in($this->inv_manage_commodity_id, $product_ids);
            $rows = $this->db->get()->result_array();
            foreach ($rows as $r) {
                $inv[(int)$r[$this->inv_manage_commodity_id]] = $r;
            }
        }

        $out = [];
        foreach ($details as $d) {
            $pid = (int)$d[$this->detail_product_fk];
            $invrow = $inv[$pid] ?? null;

            $stock = $invrow ? (float)($invrow[$this->inv_manage_stock] ?? 0) : null;
            $purchase_price = $invrow ? (float)($invrow[$this->inv_manage_purchase_price] ?? 0) : 0.0;

            $out[] = [
                'detail_id' => (int)$d[$this->detail_pk],
                'product_id' => $pid,
                'product_name' => $d[$this->detail_product_name] ?? ('Producto #' . $pid),
                'qty' => (float)($d[$this->detail_qty] ?? 0),
                'unit_name' => $d[$this->detail_unit_name] ?? '',
                'stock' => $stock, // null if not found
                'purchase_price' => $purchase_price,
                'product_exists' => $invrow !== null,
            ];
        }
        return $out;
    }

    private function row_status($userid, $cart_row, $item)
    {
        // Red if product missing or stock insufficient
        if (!$item['product_exists']) {
            return ['status' => 'red', 'reason' => 'Producto no existe en inventario'];
        }
        if ($item['stock'] === null) {
            return ['status' => 'red', 'reason' => 'Sin stock disponible'];
        }
        if ((float)$item['qty'] <= 0) {
            return ['status' => 'yellow', 'reason' => 'Cantidad inválida'];
        }
        if ((float)$item['stock'] < (float)$item['qty']) {
            return ['status' => 'red', 'reason' => 'Stock insuficiente'];
        }

        // Yellow if missing key data
        if ((int)$userid <= 0) {
            return ['status' => 'yellow', 'reason' => 'Pedido sin cliente (userid vacío)'];
        }
        if ((int)($cart_row[$this->cart_complete] ?? 0) !== 1) {
            return ['status' => 'yellow', 'reason' => 'Pedido no está completo'];
        }

        return ['status' => 'green', 'reason' => 'OK'];
    }

    private function calc_client_price($plist_percent, $purchase_price)
    {
        $plist_percent = (float)$plist_percent;
        $purchase_price = (float)$purchase_price;
        $mult = 1.0 + ($plist_percent / 100.0);
        return round($purchase_price * $mult, 2);
    }

    public function create_invoice_from_details($detail_ids)
    {
        // Load details + cart + validate same client
        $this->db->from($this->detail_table . ' d');
        $this->db->join($this->cart_table . ' c', 'c.' . $this->cart_pk . ' = d.' . $this->detail_cart_fk, 'inner');
        $this->db->where_in('d.' . $this->detail_pk, $detail_ids);
        $rows = $this->db->get()->result_array();

        if (!$rows || count($rows) === 0) {
            return ['ok' => false, 'message' => 'No se encontraron productos seleccionados.'];
        }

        $userid = (int)($rows[0][$this->cart_userid] ?? 0);
        foreach ($rows as $r) {
            if ((int)($r[$this->cart_userid] ?? 0) !== $userid) {
                return ['ok' => false, 'message' => 'Solo puedes facturar productos de un solo cliente a la vez.'];
            }
        }
        if ($userid <= 0) {
            return ['ok' => false, 'message' => 'El pedido no tiene cliente (userid).'];
        }

        // Recompute inventory and validate green only
        $plist = $this->get_client_plist($userid);

        $product_ids = array_values(array_unique(array_map(function($r){ return (int)$r[$this->detail_product_fk]; }, $rows)));
        $inv = [];
        $this->db->from($this->inv_manage_table);
        $this->db->where_in($this->inv_manage_commodity_id, $product_ids);
        foreach ($this->db->get()->result_array() as $ir) {
            $inv[(int)$ir[$this->inv_manage_commodity_id]] = $ir;
        }

        $invoice_items = [];
        $cart_ids_to_update = [];
        foreach ($rows as $r) {
            $pid = (int)$r[$this->detail_product_fk];
            $invrow = $inv[$pid] ?? null;
            if (!$invrow) {
                return ['ok' => false, 'message' => 'Hay productos sin inventario. No se puede facturar.'];
            }
            $stock = (float)($invrow[$this->inv_manage_stock] ?? 0);
            $qty = (float)($r[$this->detail_qty] ?? 0);
            if ($qty <= 0) {
                return ['ok' => false, 'message' => 'Hay productos con cantidad inválida.'];
            }
            if ($stock < $qty) {
                return ['ok' => false, 'message' => 'Hay productos con stock insuficiente.'];
            }

            $purchase_price = (float)($invrow[$this->inv_manage_purchase_price] ?? 0);
            $rate = $this->calc_client_price($plist, $purchase_price);

            $invoice_items[] = [
                'description' => (string)($r[$this->detail_product_name] ?? ('Producto #' . $pid)),
                'long_description' => (string)($r['long_description'] ?? ''),
                'qty' => $qty,
                'rate' => $rate,
                'unit' => (string)($r[$this->detail_unit_name] ?? ''),
            ];

            $cart_ids_to_update[] = (int)$r[$this->detail_cart_fk];
        }

        // Create invoice using Perfex invoices_model if available
        if (!file_exists(APPPATH . 'models/Invoices_model.php') && !file_exists(APPPATH . 'models/Invoices_model.php')) {
            // Perfex should have it; keep safe
        }
        $this->load->model('invoices_model');

        $date = date('Y-m-d');
        $invoice_data = [
            'clientid' => $userid,
            'date' => $date,
            'duedate' => $date,
            'allowed_payment_modes' => [],
            'newitems' => $invoice_items,
        ];

        $invoice_id = $this->invoices_model->add($invoice_data);
        if (!$invoice_id) {
            return ['ok' => false, 'message' => 'No se pudo crear la factura en Perfex.'];
        }

        // Update carts as invoiced
        $cart_ids_to_update = array_values(array_unique($cart_ids_to_update));
        if (count($cart_ids_to_update) > 0) {
            $this->db->where_in($this->cart_pk, $cart_ids_to_update);
            $this->db->update($this->cart_table, [
                $this->cart_create_invoice => 1,
                $this->cart_invoice_field => $invoice_id,
            ]);
        }

        return ['ok' => true, 'invoice_id' => $invoice_id];
    }
}
