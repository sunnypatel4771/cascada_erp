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

            <p>
              <a href="<?php echo admin_url('pedidos_vs_inventario'); ?>" class="btn btn-default">Volver</a>
              <a href="<?php echo admin_url('purchase/vendors'); ?>" target="_blank" class="btn btn-default">purchase/vendors</a>
              <a href="<?php echo admin_url('purchase/vendor_items'); ?>" target="_blank" class="btn btn-default">purchase/vendor_items</a>
            </p>

            <?php if (!empty($data['error'])) { ?>
              <div class="alert alert-danger"><?php echo html_escape($data['error']); ?></div>
            <?php } else { ?>
              <p class="text-muted">Vista deduplicada por <b>Proveedor + Item code</b>. Guardar actualiza filas duplicadas.</p>

              <form method="post" action="<?php echo admin_url('pedidos_vs_inventario/vendor_priority_save'); ?>">
                <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>

                <div class="table-responsive">
                  <table class="table table-bordered table-striped">
                    <thead>
                      <tr>
                        <th>Proveedor</th>
                        <th>Producto</th>
                        <th style="width:120px;">Item code</th>
                        <th style="width:120px;">Prioridad</th>
                        <th style="width:140px;">Precio compra</th>
                        <th style="width:90px;">Filas</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach (($data['rows'] ?? array()) as $r) { ?>
                        <tr>
                          <td><?php echo html_escape($r['vendor_name']); ?></td>
                          <td><?php echo html_escape($r['product_name']); ?></td>
                          <td><?php echo html_escape((string)$r['item_code']); ?></td>
                          <td>
                            <input type="number" class="form-control" style="max-width:120px;"
                                   name="priority[<?php echo html_escape($r['key']); ?>]"
                                   value="<?php echo html_escape((string)$r['priority']); ?>">
                          </td>
                          <td><?php echo html_escape((string)($r['purchase_price'] ?? '')); ?></td>
                          <td><?php echo html_escape((string)$r['row_count']); ?></td>
                        </tr>
                      <?php } ?>
                    </tbody>
                  </table>
                </div>

                <button type="submit" class="btn btn-primary">Guardar prioridades</button>
              </form>
            <?php } ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
