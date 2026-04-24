<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="row mtop15">
          <div class="col-md-3">
            <div class="panel_s">
              <div class="panel-body text-center">
                <h3 class="no-margin"><?php echo (int)$totals['total_receptions']; ?></h3>
                <p class="text-muted mtop10">Entradas</p>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="panel_s">
              <div class="panel-body text-center">
                <h3 class="no-margin"><?php echo (int)$totals['posted']; ?></h3>
                <p class="text-muted mtop10">Aplicadas</p>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="panel_s">
              <div class="panel-body text-center">
                <h3 class="no-margin"><?php echo (int)$totals['draft']; ?></h3>
                <p class="text-muted mtop10">Pendientes</p>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="panel_s">
              <div class="panel-body text-center">
                <h3 class="no-margin"><?php echo html_escape($totals['total_qty']); ?></h3>
                <p class="text-muted mtop10">Cantidad aceptada</p>
              </div>
            </div>
          </div>
        </div>

        <div class="panel_s">
          <div class="panel-body">
            <div class="clearfix">
              <h4 class="no-margin pull-left"><?php echo html_escape($title); ?></h4>
              <a href="<?php echo admin_url('entrada_inventario/report?date_from='.urlencode((string)($filters['date_from'] ?? '')).'&date_to='.urlencode((string)($filters['date_to'] ?? ''))); ?>" class="btn btn-default pull-right">Reporte</a>
            </div>
            <hr class="hr-panel-heading" />
            <p><strong>Almacén fijo:</strong> RAMOS01 (warehouse_id=<?php echo (int)$warehouse_id; ?>)</p>

            <div class="row">
              <div class="col-md-5">
                <div class="panel_s">
                  <div class="panel-body">
                    <h5 class="bold">Crear entrada desde Orden de Compra</h5>
                    <?php echo form_open(admin_url('entrada_inventario/create')); ?>
                      <div class="form-group">
                        <label for="po_id">Orden de Compra (ID | Proveedor | Fecha)</label>
                        <select name="po_id" id="po_id" class="form-control" required>
                          <option value="">Selecciona...</option>
                          <?php foreach($pos as $po){ ?>
                            <option value="<?php echo (int)$po['id']; ?>">
                              ID: <?php echo (int)$po['id']; ?>
                              <?php echo !empty($po['pur_order_number']) ? ' | OC: '.html_escape($po['pur_order_number']) : ''; ?>
                              <?php echo !empty($po['vendor_name']) ? ' | Proveedor: '.html_escape($po['vendor_name']) : ''; ?>
                              <?php echo !empty($po['po_date']) ? ' | Fecha: '.html_escape($po['po_date']) : ''; ?>
                            </option>
                          <?php } ?>
                        </select>
                      </div>
                      <button type="submit" class="btn btn-primary">Crear</button>
                    <?php echo form_close(); ?>
                  </div>
                </div>
              </div>

              <div class="col-md-7">
                <div class="panel_s">
                  <div class="panel-body">
                    <h5 class="bold">Filtros</h5>
                    <?php echo form_open(admin_url('entrada_inventario'), ['method'=>'get']); ?>
                    <div class="row">
                      <div class="col-md-5">
                        <div class="form-group">
                          <label>Desde</label>
                          <input type="date" name="date_from" class="form-control" value="<?php echo html_escape($filters['date_from']); ?>">
                        </div>
                      </div>
                      <div class="col-md-5">
                        <div class="form-group">
                          <label>Hasta</label>
                          <input type="date" name="date_to" class="form-control" value="<?php echo html_escape($filters['date_to']); ?>">
                        </div>
                      </div>
                      <div class="col-md-2">
                        <div class="form-group">
                          <label>&nbsp;</label>
                          <button type="submit" class="btn btn-info btn-block">Filtrar</button>
                        </div>
                      </div>
                    </div>
                    <?php echo form_close(); ?>
                  </div>
                </div>
              </div>
            </div>

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
                          <th>OC / Proveedor</th>
                          <th>Fecha OC</th>
                          <th>No. OC</th>
                          <th>Avance</th>
                          <th>Acción</th>
                        </tr>
                      </thead>
                      <tbody>
                      <?php foreach($recs as $r){ ?>
                        <tr>
                          <td><?php echo (int)$r['id']; ?></td>
                          <td>#<?php echo (int)$r['pur_order_id']; ?> - <?php echo html_escape(isset($r['vendor_name']) ? $r['vendor_name'] : ''); ?></td>
                          <td><?php echo !empty($r['po_date']) ? html_escape($r['po_date']) : 'N/D'; ?></td>
                          <td><?php echo !empty($r['pur_order_number']) ? html_escape($r['pur_order_number']) : 'N/D'; ?></td>
                          <td><?php echo (int)$r['items_applied']; ?>/<?php echo (int)$r['items_total']; ?></td>
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
<?php init_tail(); ?>
