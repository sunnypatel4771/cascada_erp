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

            <div class="mbot15">
              <a href="<?php echo admin_url('purchase/vendor_items'); ?>" target="_blank" class="btn btn-default">Vendor items (precio compra)</a>
              <a href="<?php echo admin_url('pedidos_vs_inventario/vendor_priority'); ?>" class="btn btn-default">Prioridad proveedores</a>
              <a href="<?php echo admin_url('pedidos_vs_inventario/export_csv'); ?>" class="btn btn-default">Exportar CSV</a>
            </div>

            <h4 class="bold">Faltantes</h4>
            <?php if (!empty($missing)) { ?>
              <form method="post" action="<?php echo admin_url('pedidos_vs_inventario/generate_po_selected'); ?>">
                <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
                <div class="mbot15">
                  <button type="submit" class="btn btn-primary">Generar Órdenes de Compra (seleccionados)</button>
                  <a href="<?php echo admin_url('pedidos_vs_inventario/generate_po'); ?>" class="btn btn-default">Generar Órdenes de Compra (todos)</a>
                </div>

                <div class="table-responsive">
                  <table class="table table-bordered table-striped">
                    <thead>
                      <tr>
                        <th style="width:40px;"></th>
                        <th>Producto</th>
                        <th style="width:110px;">Item code</th>
                        <th style="width:110px;">Faltante</th>
                        <th>Proveedor (prioridad)</th>
                        <th style="width:130px;">Precio compra</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($missing as $r) {
                        $vendorLabel = trim((string)($r['best_vendor_name'] ?? ''));
                        $prio = (string)($r['best_vendor_priority'] ?? '');
                        if ($vendorLabel !== '') $vendorLabel .= ($prio !== '' ? ' ('.$prio.')' : '');
                      ?>
                        <tr class="warning">
                          <td><input type="checkbox" name="items[]" value="<?php echo html_escape((string)$r['item_name']); ?>" checked></td>
                          <td><?php echo html_escape((string)$r['item_name']); ?></td>
                          <td><?php echo html_escape((string)($r['item_code'] ?? '')); ?></td>
                          <td><?php echo html_escape((string)($r['qty_missing'] ?? '0')); ?></td>
                          <td><?php echo html_escape($vendorLabel); ?></td>
                          <td><?php echo html_escape((string)($r['best_vendor_purchase_price'] ?? '')); ?></td>
                        </tr>
                      <?php } ?>
                    </tbody>
                  </table>
                </div>
              </form>
            <?php } else { ?>
              <div class="alert alert-success">No hay faltantes.</div>
            <?php } ?>

            <h4 class="bold mtop25">Reporte completo (Pedidos vs Inventario)</h4>
            <div class="table-responsive">
              <table class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>Producto</th>
                    <th style="width:140px;">Total pedidos</th>
                    <th style="width:140px;">Inventario</th>
                    <th style="width:140px;">Faltante</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($rows)) { foreach ($rows as $r) { $miss = (float)($r['qty_missing'] ?? 0); ?>
                    <tr class="<?php echo $miss > 0 ? 'warning' : ''; ?>">
                      <td><?php echo html_escape((string)$r['item_name']); ?></td>
                      <td><?php echo html_escape((string)$r['qty_orders']); ?></td>
                      <td><?php echo html_escape((string)$r['qty_inventory']); ?></td>
                      <td><?php echo html_escape((string)$r['qty_missing']); ?></td>
                    </tr>
                  <?php } } else { ?>
                    <tr><td colspan="4">No hay datos</td></tr>
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
