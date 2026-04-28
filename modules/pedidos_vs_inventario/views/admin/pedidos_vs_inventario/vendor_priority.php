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
              <p class="text-muted">Vista deduplicada por <b>Proveedor + Item code</b>. Guardar actualiza filas duplicadas y sincroniza el precio al inventario.</p>

              <form method="post" action="<?php echo admin_url('pedidos_vs_inventario/vendor_priority_save'); ?>">
                <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>

                <div class="table-responsive">
                  <table class="table table-bordered table-striped" id="vendor-priority-table">
                    <thead>
                      <tr>
                        <th>Proveedor</th>
                        <th>Producto</th>
                        <th style="width:100px;">Item ID</th>
                        <th style="width:110px;">Prioridad</th>
                        <th style="width:150px;">Precio compra <small class="text-muted">(sincroniza a inventario)</small></th>
                        <th style="width:80px;">Filas</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach (($data['rows'] ?? array()) as $r) { ?>
                        <tr>
                          <td><?php echo html_escape($r['vendor_name']); ?></td>
                          <td><?php echo html_escape($r['product_name']); ?></td>
                          <td><?php echo html_escape((string)$r['item_code']); ?></td>
                          <td>
                            <input type="number" class="form-control input-sm" style="max-width:110px;"
                                   name="priority[<?php echo html_escape($r['key']); ?>]"
                                   value="<?php echo html_escape((string)$r['priority']); ?>">
                          </td>
                          <td>
                            <input type="number" class="form-control input-sm" style="max-width:140px;"
                                   name="purchase_price[<?php echo html_escape($r['key']); ?>]"
                                   value="<?php echo html_escape((string)($r['purchase_price'] ?? '')); ?>"
                                   step="0.01" min="0"
                                   placeholder="0.00">
                          </td>
                          <td><?php echo html_escape((string)$r['row_count']); ?></td>
                        </tr>
                      <?php } ?>
                    </tbody>
                  </table>
                </div>

                <button type="submit" class="btn btn-primary">Guardar prioridades y precios</button>
              </form>
              <script>
              if (typeof $ !== 'undefined' && $.fn.DataTable) {
                $('#vendor-priority-table').DataTable({ pageLength: 25, order: [[0,  'asc']], columnDefs: [{orderable: false, targets: [3,4]}] });
              }
              </script>
            <?php } ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
