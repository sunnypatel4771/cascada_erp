<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin"><?php echo html_escape($title); ?> <small class="text-muted">v1.3.1</small></h4>
            <hr class="hr-panel-heading" />
            <p><a href="<?php echo admin_url('pedidos_vs_inventario'); ?>" class="btn btn-default">Volver</a></p>

            <?php if (!empty($result['created'])) { ?>
              <div class="table-responsive">
                <table class="table table-bordered table-striped">
                  <thead>
                    <tr>
                      <th style="width:90px;">PO ID</th>
                      <th>Producto</th>
                      <th style="width:110px;">Item code</th>
                      <th>Proveedor</th>
                      <th style="width:100px;">Prioridad</th>
                      <th style="width:110px;">Cantidad</th>
                      <th style="width:120px;">Unit price</th>
                      <th style="width:120px;">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($result['created'] as $c) { ?>
                      <tr>
                        <td><?php echo html_escape((string)$c['po_id']); ?></td>
                        <td><?php echo html_escape((string)$c['item_name']); ?></td>
                        <td><?php echo html_escape((string)$c['item_code']); ?></td>
                        <td><?php echo html_escape((string)$c['vendor_name']); ?></td>
                        <td><?php echo html_escape((string)($c['vendor_priority'] ?? '')); ?></td>
                        <td><?php echo html_escape((string)$c['quantity']); ?></td>
                        <td><?php echo html_escape((string)$c['unit_price']); ?></td>
                        <td><?php echo html_escape((string)$c['line_total']); ?></td>
                      </tr>
                    <?php } ?>
                  </tbody>
                </table>
              </div>
            <?php } else { ?>
              <div class="alert alert-info">No se generaron OCs.</div>
            <?php } ?>

            <?php if (!empty($result['unmapped'])) { ?>
              <h4 class="bold mtop25">Sin proveedor / sin mapeo</h4>
              <pre><?php echo html_escape(print_r($result['unmapped'], true)); ?></pre>
            <?php } ?>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
