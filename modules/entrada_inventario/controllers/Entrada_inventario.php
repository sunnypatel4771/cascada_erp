<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Entrada_inventario extends AdminController
{
    public function __construct(){
        parent::__construct();
        $this->load->model('entrada_inventario/entrada_inventario_model');
        $this->load->helper('form');
    }

    public function index()
    {
        $filters = [
            'date_from' => $this->input->get('date_from'),
            'date_to'   => $this->input->get('date_to'),
        ];

        $data['title'] = 'Entrada Inventario';
        $data['warehouse_id'] = 1;
        $data['pos']  = $this->entrada_inventario_model->list_pos();
        $data['recs'] = $this->entrada_inventario_model->list_receptions($filters);
        $data['totals'] = $this->entrada_inventario_model->reception_totals();
        $data['filters'] = $filters;
        $this->load->view('entrada_inventario/admin/index', $data);
    }

    public function create()
    {
        $po_id = (int)$this->input->post('po_id');
        $rid = $this->entrada_inventario_model->create_from_po($po_id);
        if (!$rid) { show_error('No se pudo crear la entrada.'); }
        redirect(admin_url('entrada_inventario/view/'.$rid));
    }

    public function view($rid)
    {
        $rec = $this->entrada_inventario_model->get_reception($rid);
        if (!$rec) { show_404(); }
        $data['title'] = 'Entrada #'.$rec['id'];
        $data['rec'] = $rec;
        $this->load->view('entrada_inventario/admin/view', $data);
    }

    public function update_item($item_id)
    {
        $rid = (int)$this->input->post('reception_id');
        $this->entrada_inventario_model->update_item_qty($item_id, $this->input->post('qty_delivered'), $this->input->post('qty_accepted'));
        redirect(admin_url('entrada_inventario/view/'.$rid));
    }

    public function apply_item($item_id)
    {
        $rid = (int)$this->input->get('rid');
        $err=null;
        $this->entrada_inventario_model->apply_item($item_id,$err);
        if ($err) { show_error('No se pudo aplicar: '.$err); }
        redirect(admin_url('entrada_inventario/view/'.$rid));
    }

    public function apply_all($rid)
    {
        $err=null;
        $this->entrada_inventario_model->apply_all($rid,$err);
        if ($err) { show_error('No se pudo aplicar todo: '.$err); }
        redirect(admin_url('entrada_inventario/view/'.$rid));
    }


    public function report()
    {
        $filters = [
            'date_from' => $this->input->get('date_from'),
            'date_to'   => $this->input->get('date_to'),
        ];
        $data['title'] = 'Reporte de Entradas';
        $data['recs'] = $this->entrada_inventario_model->list_receptions($filters);
        $data['filters'] = $filters;
        $this->load->view('entrada_inventario/admin/report', $data);
    }

}
