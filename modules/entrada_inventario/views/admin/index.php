<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin"><?php echo html_escape($title); ?></h4>
            <hr class="hr-panel-heading" />
            <p><strong>Almacén fijo:</strong> RAMOS01 (warehouse_id=<?php echo (int)$warehouse_id; ?>)</p>

            <div class="row">
              <div class="col-md-6">
                <div class="panel_s">
                  <div class="panel-body">
                    <h5 class="bold">Crear entrada desde Orden de Compra</h5>
                    <?php echo form_open(admin_url('entrada_inventario/create')); ?>
                      <div class="form-group">
                        <label for="po_id">ID Orden de Compra</label>
                        <select name="po_id" id="po_id" class="form-control" required>
                          <option value="">Selecciona...</option>
                          <?php foreach($pos as $po){ ?>
                            <option value="<?php echo (int)$po['id']; ?>"><?php echo (int)$po['id']; ?></option>
                          <?php } ?>
                        </select>
                      </div>
                      <button type="submit" class="btn btn-primary">Crear</button>
                    <?php echo form_close(); ?>
                  </div>
                </div>
              </div>

              <div class="col-md-6">
                <div class="panel_s">
                  <div class="panel-body">
                    <h5 class="bold">Entradas</h5>
                    <?php if(empty($recs)){ ?>
                      <p class="text-muted">No hay entradas aún.</p>
                    <?php } else { ?>
                      <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                          <thead>
                            <tr>
                              <th>ID</th>
                              <th>OC</th>
                              <th>Status</th>
                              <th>Acción</th>
                            </tr>
                          </thead>
                          <tbody>
                          <?php foreach($recs as $r){ ?>
                            <tr>
                              <td><?php echo (int)$r['id']; ?></td>
                              <td><?php echo (int)$r['pur_order_id']; ?></td>
                              <td><?php echo html_escape($r['status']); ?></td>
                              <td><a class="btn btn-default btn-sm" href="<?php echo admin_url('entrada_inventario/view/'.$r['id']); ?>">Ver</a></td>
                            </tr>
                          <?php } ?>
                          </tbody>
                        </table>
                      </div>
                    <?php } ?>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
