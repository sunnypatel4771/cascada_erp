<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<link rel="stylesheet" href="<?php echo module_dir_url('facturacion', 'assets/css/facturacion.css'); ?>">
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <div class="clearfix">
              <h4 class="no-margin pull-left"><?php echo html_escape($title); ?></h4>
              
            </div>
            <hr class="hr-panel-heading" />

            <div class="alert alert-info" style="margin-top:10px;">
              <strong>Cómo facturar:</strong> Selecciona productos en <strong>VERDE</strong> (o usa <em>Seleccionar VERDES</em> por cliente) y luego presiona <strong>Generar factura con seleccionados</strong>.
            </div>


            <?php if (empty($groups)) { ?>
              <p>No hay pedidos completos pendientes de facturar.</p>
            <?php } else { ?>

            <form method="post" action="<?php echo admin_url('facturacion/generate_invoice'); ?>">
              <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>

              <?php foreach ($groups as $g) { ?>
                <div class="panel panel-default facturacion-client">
                  <div class="panel-heading">
                    <strong><?php echo html_escape($g['client_name']); ?></strong>
                    <span class="text-muted"> | userid: <?php echo (int)$g['userid']; ?> | plist: <?php echo (float)$g['plist']; ?>%</span>
                    <button type="button" class="btn btn-xs btn-success pull-right js-select-green" data-userid="<?php echo (int)$g['userid']; ?>">
                      Seleccionar VERDES
                    </button>
                    <div class="clearfix"></div>
                  </div>
                  <div class="panel-body">
                    <div class="table-responsive">
                      <table class="table table-bordered table-hover">
                        <thead>
                          <tr>
                            <th style="width:40px;"></th>
                            <th>Pedido</th>
                            <th>Producto</th>
                            <th style="width:90px;">Qty</th>
                            <th style="width:110px;">Stock</th>
                            <th style="width:120px;">Compra</th>
                            <th style="width:130px;">Precio Cliente</th>
                            <th>Estado</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($g['rows'] as $r) {
                            $cls = 'row-green';
                            $label = 'VERDE';
                            if ($r['status'] === 'yellow') { $cls='row-yellow'; $label='AMARILLO'; }
                            if ($r['status'] === 'red') { $cls='row-red'; $label='ROJO'; }
                            $canSelect = ($r['status'] === 'green');
                          ?>
                          <tr class="<?php echo $cls; ?>" data-userid="<?php echo (int)$g['userid']; ?>" data-status="<?php echo html_escape($r['status']); ?>">
                            <td class="text-center">
                              <?php if ($canSelect) { ?>
                                <input type="checkbox" name="detail_ids[]" value="<?php echo (int)$r['detail_id']; ?>" />
                              <?php } else { ?>
                                <span class="text-muted">—</span>
                              <?php } ?>
                            </td>
                            <td><?php echo html_escape($r['order_number']); ?> <span class="text-muted">(#<?php echo (int)$r['cart_id']; ?>)</span></td>
                            <td><?php echo html_escape($r['product_name']); ?> <span class="text-muted">(ID <?php echo (int)$r['product_id']; ?>)</span></td>
                            <td><?php echo (float)$r['qty']; ?> <?php echo html_escape($r['unit_name']); ?></td>
                            <td><?php echo ($r['stock'] === null ? '—' : (float)$r['stock']); ?></td>
                            <td><?php echo number_format((float)$r['purchase_price'], 2); ?></td>
                            <td><strong><?php echo number_format((float)$r['rate_client'], 2); ?></strong></td>
                            <td><strong><?php echo $label; ?></strong> <span class="text-muted"><?php echo html_escape($r['reason']); ?></span></td>
                          </tr>
                          <?php } ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              <?php } ?>

              <div class="text-right">
                <button type="submit" class="btn btn-primary">Generar factura con seleccionados</button>
              </div>

            </form>

            <?php } ?>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="<?php echo module_dir_url('facturacion', 'assets/js/facturacion.js'); ?>"></script>
<?php init_tail(); ?>
