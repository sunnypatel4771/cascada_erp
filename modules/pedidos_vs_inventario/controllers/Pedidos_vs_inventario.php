<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Pedidos_vs_inventario extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('pedidos_vs_inventario/Pedidos_vs_inventario_model');
    }

    public function index()
    {
        $data = array();
        $data['title'] = 'Pedidos vs Inventario';

        $data['rows'] = $this->Pedidos_vs_inventario_model->build_report();
        $data['missing'] = array_values(array_filter($data['rows'], function ($r) {
            return (float)($r['qty_missing'] ?? 0) > 0;
        }));

        $this->load->view('admin/pedidos_vs_inventario/report', $data);
    }

    public function vendor_priority()
    {
        $data = array();
        $data['title'] = 'Prioridad Proveedores';
        $data['data'] = $this->Pedidos_vs_inventario_model->get_vendor_items_with_priority_deduped();
        $this->load->view('admin/pedidos_vs_inventario/vendor_priority', $data);
    }

    public function vendor_priority_save()
    {
        $posted = $this->input->post('priority');
        $res = $this->Pedidos_vs_inventario_model->save_vendor_item_priorities_deduped($posted);
        if (($res['ok'] ?? false)) {
            set_alert('success', 'Prioridades guardadas.');
        } else {
            set_alert('danger', 'Error: '.($res['error'] ?? ''));
        }
        redirect(admin_url('pedidos_vs_inventario/vendor_priority'));
    }

    public function vendor_item_price_get()
    {
        $vendorId=(int)$this->input->post('vendor_id');
        $itemCode=$this->input->post('item_code');
        $res=$this->Pedidos_vs_inventario_model->get_vendor_item_purchase_price($vendorId,$itemCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($res);
        exit;
    }

    public function vendor_item_price_save()
    {
        $vendorId=(int)$this->input->post('vendor_id');
        $itemCode=$this->input->post('item_code');
        $price=$this->input->post('purchase_price');
        $res=$this->Pedidos_vs_inventario_model->save_vendor_item_purchase_price($vendorId,$itemCode,$price);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($res);
        exit;
    }

    public function generate_po()
    {
        $rows=$this->Pedidos_vs_inventario_model->build_report();
        $missing=array_values(array_filter($rows,function($r){ return (float)($r['qty_missing']??0)>0; }));
        $result=$this->Pedidos_vs_inventario_model->create_purchase_orders_by_product($missing);
        $data=array('title'=>'Órdenes de Compra generadas','result'=>$result);
        $this->load->view('admin/pedidos_vs_inventario/po_result',$data);
    }

    public function generate_po_selected()
    {
        $selected=$this->input->post('items'); if(!is_array($selected)) $selected=array();
        $rows=$this->Pedidos_vs_inventario_model->build_report();
        $missing=array_values(array_filter($rows,function($r)use($selected){
            if((float)($r['qty_missing']??0)<=0) return false;
            return in_array((string)$r['item_name'],$selected,true);
        }));
        $result=$this->Pedidos_vs_inventario_model->create_purchase_orders_by_product($missing);
        $data=array('title'=>'Órdenes de Compra generadas','result'=>$result);
        $this->load->view('admin/pedidos_vs_inventario/po_result',$data);
    }

    public function export_csv()
    {
        $rows=$this->Pedidos_vs_inventario_model->build_report();
        $missing=array_values(array_filter($rows,function($r){ return (float)($r['qty_missing']??0)>0; }));

        $filename='faltantes_'.date('Ymd_His').'.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        $out=fopen('php://output','w');
        fputcsv($out,array('Producto','Item code','Faltante','Proveedor','Prioridad','Precio compra'));
        foreach($missing as $r){
            fputcsv($out,array(
                (string)$r['item_name'],
                (string)($r['item_code']??''),
                (string)$r['qty_missing'],
                (string)($r['best_vendor_name']??''),
                (string)($r['best_vendor_priority']??''),
                (string)($r['best_vendor_purchase_price']??''),
            ));
        }
        fclose($out); exit;
    }
}
