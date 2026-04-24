<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <div class="clearfix">
              <a href="<?php echo admin_url('entrada_inventario'); ?>" class="btn btn-default pull-left"><?php echo _l('back'); ?></a>
              <a href="<?php echo admin_url('entrada_inventario/apply_all/'.$rec['id']); ?>" class="btn btn-success pull-right">Aplicar TODO</a>
            </div>

            <h4 class="m-top-20 no-margin">Entrada #<?php echo (int)$rec['id']; ?></h4>
            <hr class="hr-panel-heading" />

            <div class="row">
              <div class="col-md-3">
                <p><strong>Orden de Compra:</strong> #<?php echo (int)$rec['pur_order_id']; ?> - <?php echo html_escape($rec['vendor_name']); ?></p>
              </div>
              <div class="col-md-3">
                <p><strong>Fecha OC:</strong> <?php echo !empty($rec['po_date']) ? html_escape($rec['po_date']) : 'N/D'; ?></p>
              </div>
              <div class="col-md-3">
                <p><strong>No. OC:</strong> <?php echo !empty($rec['pur_order_number']) ? html_escape($rec['pur_order_number']) : 'N/D'; ?></p>
              </div>
              <div class="col-md-3">
                <p><strong>Proveedor:</strong> <?php echo html_escape($rec['vendor_name']); ?></p>
              </div>
            </div>

            <p class="text-muted">Movimientos: <code>tblentrada_inventory_moves</code> con <code>source=oc</code> y <code>source_id</code>=ID de OC. Inventario: <strong>tblinventory_manage.inventory_number</strong> (warehouse_id=1). Además se registra una entrada real al almacén 1 con concepto <strong>orden de compra</strong>.</p>

            <div class="table-responsive">
              <table class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>item_code</th>
                    <th>Descripción</th>
                    <th class="text-right">Pedido</th>
                    <th class="text-right">Entregado</th>
                    <th class="text-right">Aceptado</th>
                    <th class="text-right">Pendiente</th>
                    <th class="text-right">Inv actual</th>
                    <th class="text-right">Inv después</th>
                    <th>Acción</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $i=1; foreach($rec['items'] as $it){ $pending = (float)$it['qty_ordered'] - (float)$it['qty_accepted']; ?>
                    <tr>
                      <td><?php echo $i++; ?></td>
                      <td><?php echo html_escape($it['item_code']); ?></td>
                      <td><?php echo html_escape(!empty($it['commodity_name']) ? $it['commodity_name'] : $it['description']); ?></td>
                      <td class="text-right"><?php echo html_escape($it['qty_ordered']); ?></td>

                      <td class="text-right" style="min-width:170px;">
                        <?php echo form_open(admin_url('entrada_inventario/update_item/'.$it['id']), ['class'=>'m0']); ?>
                        <?php echo form_hidden('reception_id', $rec['id']); ?>
                        <input type="number" step="0.0001" name="qty_delivered" class="form-control input-sm text-right" value="<?php echo html_escape($it['qty_delivered']); ?>">
                      </td>

                      <td class="text-right" style="min-width:200px;">
                        <div class="input-group">
                          <input type="number" step="0.0001" name="qty_accepted" class="form-control input-sm text-right" value="<?php echo html_escape($it['qty_accepted']); ?>">
                          <span class="input-group-btn">
                            <button type="submit" class="btn btn-info btn-sm">Guardar</button>
                          </span>
                        </div>
                        <?php echo form_close(); ?>
                      </td>

                      <td class="text-right"><?php echo html_escape($pending); ?></td>
                      <td class="text-right"><?php echo html_escape($it['inv_before']); ?></td>
                      <td class="text-right"><?php echo html_escape($it['inv_after']); ?></td>

                      <td>
                        <?php if((int)$it['applied']===1){ ?>
                          <span class="label label-success">APLICADO</span>
                        <?php } else { ?>
                          <a class="btn btn-success btn-sm" href="<?php echo admin_url('entrada_inventario/apply_item/'.$it['id'].'?rid='.$rec['id']); ?>">Aplicar</a>
                        <?php } ?>
                      </td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
