<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">

            <div class="tw-flex tw-justify-between tw-items-center">
              <h4 class="tw-my-0"><?php echo html_escape($title ?? 'Importar Clientes (Ramos XLSX)'); ?></h4>
              <?php if (($step ?? 'upload') !== 'upload'): ?>
                <a href="<?php echo admin_url('importar_clientes/importar_clientes_ramos/cancel'); ?>" class="btn btn-default">
                  Cancelar y empezar de nuevo
                </a>
              <?php endif; ?>
            </div>
            <hr class="hr-panel-heading" />

            <?php if (($step ?? 'upload') === 'upload'): ?>
              <div class="alert alert-info">
                <strong>Archivo con encabezados</strong>
                <p class="mbot0">
                  Este importador está diseñado para el archivo de Ramos con columnas como
                  <code>company</code>, <code>vat</code>, <code>CUSTOMER_EMAIL</code>, <code>Customer_password</code>.
                  Se omiten duplicados por email y se crea login de cliente.
                </p>
              </div>

              <?php echo form_open_multipart(admin_url('importar_clientes/importar_clientes_ramos/upload')); ?>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label>Archivo (CSV, XLS, XLSX)</label>
                      <input type="file" name="file" class="form-control" required accept=".csv,.xls,.xlsx">
                    </div>
                  </div>
                </div>
                <button type="submit" class="btn btn-primary">Subir y previsualizar</button>
              <?php echo form_close(); ?>

            <?php elseif (($step ?? '') === 'preview'): ?>
              <?php if (!empty($errors ?? [])): ?>
                <div class="alert alert-danger">
                  <strong>Errores detectados (se bloquea la importación)</strong>
                  <ul class="mtop10 mbot0">
                    <?php foreach ($errors as $e): ?>
                      <li><?php echo html_escape($e); ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php else: ?>
                <div class="alert alert-success">
                  <strong>OK</strong>
                  <p class="mbot0">Sin errores de encabezados. Puedes confirmar la importación.</p>
                </div>
              <?php endif; ?>

              <h5>Encabezados detectados</h5>
              <div class="table-responsive">
                <table class="table table-bordered table-condensed">
                  <tr>
                    <?php foreach (($headers ?? []) as $h): ?>
                      <th><?php echo html_escape((string)$h); ?></th>
                    <?php endforeach; ?>
                  </tr>
                </table>
              </div>

              <h5 class="mtop20">Vista previa (primeras filas)</h5>
              <div class="table-responsive" style="max-height:320px; overflow:auto;">
                <table class="table table-bordered table-condensed">
                  <?php foreach (($preview_rows ?? []) as $r): ?>
                    <tr>
                      <?php foreach ($r as $c): ?>
                        <td><?php echo html_escape((string)$c); ?></td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                </table>
              </div>

              <hr />
              <div class="clearfix">
                <a href="<?php echo admin_url('importar_clientes/importar_clientes_ramos'); ?>" class="btn btn-default pull-left">Volver</a>
                <?php if (empty($errors ?? [])): ?>
                  <?php echo form_open(admin_url('importar_clientes/importar_clientes_ramos/confirm_import'), ['class'=>'pull-right']); ?>
                    <button type="submit" class="btn btn-primary" onclick="return confirm('¿Importar clientes y crear logins?');">
                      Confirmar e importar
                    </button>
                  <?php echo form_close(); ?>
                <?php else: ?>
                  <button class="btn btn-primary pull-right" disabled>Confirmar e importar</button>
                <?php endif; ?>
              </div>

            <?php elseif (($step ?? '') === 'results'): ?>
              <?php $lr = $last_result ?? null; ?>
              <?php if (!$lr): ?>
                <div class="alert alert-warning">No hay resultados.</div>
              <?php else: ?>
                <div class="alert alert-success">
                  Importación completada.
                  <br />Clientes insertados: <strong><?php echo (int)($lr['imported_clients'] ?? 0); ?></strong>
                  <br />Logins creados: <strong><?php echo (int)($lr['created_contacts'] ?? 0); ?></strong>
                  <br />Omitidos (email duplicado en archivo): <strong><?php echo (int)($lr['skipped_email_dup_file'] ?? 0); ?></strong>
                  <br />Omitidos (email ya existe en BD): <strong><?php echo (int)($lr['skipped_email_dup_db'] ?? 0); ?></strong>
                  <br />Omitidos (fila vacía): <strong><?php echo (int)($lr['skipped_empty'] ?? 0); ?></strong>
                </div>

                <?php if (!empty($lr['row_errors'])): ?>
                  <h5>Errores por fila (máx. 200)</h5>
                  <div class="table-responsive" style="max-height:280px; overflow:auto;">
                    <table class="table table-bordered table-condensed">
                      <?php foreach ($lr['row_errors'] as $er): ?>
                        <tr><td><?php echo html_escape($er); ?></td></tr>
                      <?php endforeach; ?>
                    </table>
                  </div>
                <?php endif; ?>
              <?php endif; ?>

              <a href="<?php echo admin_url('importar_clientes/importar_clientes_ramos/cancel'); ?>" class="btn btn-default mtop15">Nueva importación</a>
            <?php endif; ?>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>

