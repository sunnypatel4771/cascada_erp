<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <div class="clearfix"><h4 class="no-margin pull-left"><?php echo html_escape($title); ?></h4><a class="btn btn-default pull-right" href="<?php echo admin_url('ramos/facturacion'); ?>">Volver a Facturación</a></div>
            <hr class="hr-panel-heading" />
            <p>Tablas detectadas:</p>
            <ul>
              <?php foreach ($tables as $t => $ok) { ?>
                <li><strong><?php echo html_escape($t); ?></strong>: <?php echo $ok ? '<span class="text-success">OK</span>' : '<span class="text-danger">NO EXISTE</span>'; ?></li>
              <?php } ?>
            </ul>
            <hr>
            <p><strong>Reglas actuales:</strong></p>
            <ul>
              <li>Listos para facturar: <code>tblcart.complete = 1</code> y <code>create_invoice = 0</code></li>
              <li>Stock real: <code>tblinventory_manage.inventory_number</code></li>
              <li>Precio cliente: <code>purchase_price * (1 + tblclients.plist/100)</code></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
