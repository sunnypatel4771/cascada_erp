<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">

            <h4 class="no-margin"><?php echo html_escape($title); ?> (v1.5)</h4>
            <hr class="hr-panel-heading" />

            <div class="alert alert-info">
              <strong>Ejemplo / Orden de columnas (sin encabezados)</strong>
              <p class="m-t-10 m-b-0">
                Tu archivo <strong>NO</strong> debe tener encabezados. Las columnas deben ir EXACTAMENTE en el orden real de
                <code><?php echo db_prefix(); ?>clients</code> (tblclients). Aquí está el orden detectado en tu instalación:
              </p>

              <div class="m-t-10" style="max-height:220px; overflow:auto; border:1px solid #e5e5e5; padding:10px; border-radius:6px;">
                <ol class="m-b-0">
                  <?php foreach($tblclients_columns as $c): ?>
                    <li>
                      <code><?php echo html_escape($c); ?></code>
                      <?php if(in_array($c, $tblclients_required ?? [], true)): ?>
                        <span class="text-danger">*</span>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ol>
              </div>

              <p class="m-t-10 text-muted m-b-0">
                <span class="text-danger">*</span> Campo obligatorio según tu BD. <br>
                Si agregas columnas extra al final, podrás mapearlas a <strong>Campos personalizados</strong>.
              </p>
            </div>

            <?php if(($step ?? 'upload') === 'upload'): ?>
              <?php echo form_open_multipart(admin_url('importar_clientes/upload')); ?>

                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label>Archivo (CSV, XLS, XLSX) sin encabezados</label>
                      <input type="file" name="file" class="form-control" required accept=".csv,.xls,.xlsx">
                      <p class="text-muted m-t-5">
                        Nota: para XLS/XLSX el servidor debe tener <code>PhpSpreadsheet</code>. Si no, usa CSV.
                      </p>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-group">
                      <label>Modo de importación</label>
                      <div class="radio">
                        <label><input type="radio" name="mode" value="insert" checked> Insertar solamente</label>
                      </div>
                      <div class="radio">
                        <label><input type="radio" name="mode" value="upsert"> Actualizar si existe (UPSERT)</label>
                      </div>
                      <p class="text-muted">En UPSERT, si encuentra el cliente por la llave elegida, hace UPDATE; si no, INSERT.</p>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label>Llave para “Actualizar si existe”</label>
                      <select name="upsert_key" class="form-control">
                        <option value="vat">vat</option>
                        <option value="email">email</option>
                      </select>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-group">
                      <label>Duplicados dentro del archivo</label>
                      <div class="checkbox">
                        <label><input type="checkbox" name="block_duplicates" value="1" checked> Bloquear importación si hay duplicados</label>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="alert alert-warning">
                  <strong>Validación extra (para que no falten campos)</strong>
                  <p class="m-t-10">Además de los campos obligatorios detectados en tu BD, puedes exigir:</p>
                  <div class="row">
                    <div class="col-md-3"><label class="checkbox"><input type="checkbox" name="extra_required[company]" value="1" checked> company</label></div>
                    <div class="col-md-3"><label class="checkbox"><input type="checkbox" name="extra_required[email]" value="1"> email</label></div>
                    <div class="col-md-3"><label class="checkbox"><input type="checkbox" name="extra_required[phonenumber]" value="1"> phonenumber</label></div>
                    <div class="col-md-3"><label class="checkbox"><input type="checkbox" name="extra_required[country]" value="1"> country</label></div>
                  </div>
                </div>

                <button type="submit" class="btn btn-primary">Subir y previsualizar</button>
              <?php echo form_close(); ?>
            <?php endif; ?>

            <?php if(($step ?? '') === 'preview' && !empty($preview)): ?>

              <?php if(!empty(($preview['read_error'] ?? ''))): ?>
                <div class="alert alert-danger">
                  <?php echo html_escape($preview['read_error']); ?>
                </div>
              <?php endif; ?>

              <?php if(!empty($errors)): ?>
                <div class="alert alert-danger">
                  <strong>Errores detectados (se bloquea la importación)</strong>
                  <ul class="m-t-10">
                    <?php foreach($errors as $e): ?>
                      <li><?php echo html_escape($e); ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php else: ?>
                <div class="alert alert-success">
                  <strong>OK</strong>
                  <p class="m-b-0">Sin errores. Ya puedes confirmar la importación.</p>
                </div>
              <?php endif; ?>

              <h4 class="m-t-20">Vista previa</h4>
              <p class="text-muted">Mostrando hasta 20 filas.</p>

              <div style="overflow:auto; border:1px solid #e5e5e5; border-radius:6px;">
                <table class="table table-bordered table-striped m-b-0">
                  <thead>
                    <tr>
                      <?php
                        $maxCols = (int)($preview['max_cols'] ?? 0);
                        for($i=0; $i<$maxCols; $i++):
                          $label = $i < count($tblclients_columns)
                            ? $tblclients_columns[$i]
                            : ('extra_' . ($i - count($tblclients_columns) + 1));
                      ?>
                        <th style="white-space:nowrap;"><code><?php echo html_escape($label); ?></code></th>
                      <?php endfor; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach(($preview['rows'] ?? []) as $r): ?>
                      <tr>
                        <?php for($i=0; $i<$maxCols; $i++): ?>
                          <td style="white-space:nowrap;"><?php echo html_escape($r[$i] ?? ''); ?></td>
                        <?php endfor; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <?php if(($preview['extra_columns'] ?? 0) > 0): ?>
                <h4 class="m-t-30">Mapeo de columnas extra a campos personalizados</h4>
                <p class="text-muted">Selecciona a qué campo personalizado de Clientes (customers) se asigna cada columna extra.</p>

                <?php echo form_open(admin_url('importar_clientes/save_mapping')); ?>
                  <div class="row">
                    <?php for($i=0; $i<(int)$preview['extra_columns']; $i++): ?>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Columna extra <?php echo $i+1; ?> <span class="text-muted">(posición: <?php echo count($tblclients_columns) + $i + 1; ?>)</span></label>
                          <select name="mapping[<?php echo $i; ?>]" class="form-control">
                            <option value="">-- No importar --</option>
                            <?php foreach($custom_fields as $cf): ?>
                              <option value="<?php echo (int)$cf['id']; ?>" <?php echo ((string)($mapping[$i] ?? '') === (string)$cf['id']) ? 'selected' : ''; ?>>
                                <?php echo html_escape($cf['name']); ?> (ID: <?php echo (int)$cf['id']; ?>)
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>
                    <?php endfor; ?>
                  </div>
                  <button type="submit" class="btn btn-default">Guardar mapeo</button>
                <?php echo form_close(); ?>
              <?php endif; ?>

              <hr class="m-t-30" />
              <div class="clearfix">
                <a href="<?php echo admin_url('importar_clientes'); ?>" class="btn btn-default pull-left">Volver</a>

                <?php if(empty($errors) && empty(($preview['read_error'] ?? ''))): ?>
                  <?php echo form_open(admin_url('importar_clientes/confirm_import'), ['class'=>'pull-right']); ?>
                    <button type="submit" class="btn btn-primary">Confirmar e importar</button>
                  <?php echo form_close(); ?>
                <?php else: ?>
                  <button class="btn btn-primary pull-right" disabled>Confirmar e importar</button>
                <?php endif; ?>
              </div>

            <?php endif; ?>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
