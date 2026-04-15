<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">

            <div class="tw-flex tw-justify-between tw-items-center">
              <h4 class="tw-my-0"><?php echo html_escape($title); ?></h4>
              <?php if (($step ?? 'upload') !== 'upload'): ?>
                <a href="<?php echo admin_url('importar_productos/cancel'); ?>" class="btn btn-default">
                  <?php echo _l('importar_productos_cancel'); ?>
                </a>
              <?php endif; ?>
            </div>
            <hr class="hr-panel-heading" />

            <?php if (($step ?? 'upload') === 'upload'): ?>
              <div class="alert alert-info">
                <p><?php echo _l('importar_productos_upload_help'); ?></p>
              </div>

              <?php echo form_open_multipart(admin_url('importar_productos/upload')); ?>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label>Archivo</label>
                      <input type="file" name="file" class="form-control" required accept=".csv,.xls,.xlsx">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label><?php echo _l('importar_productos_duplicate_check'); ?></label>
                      <div class="checkbox"><label><input type="checkbox" name="duplicate_check" value="1" checked> <?php echo _l('importar_productos_duplicate_check'); ?></label></div>
                      <label><?php echo _l('importar_productos_duplicate_by'); ?></label>
                      <select name="duplicate_by" class="form-control">
                        <option value="description"><?php echo _l('importar_productos_dup_description'); ?></option>
                        <option value="sku_code"><?php echo _l('importar_productos_dup_sku'); ?></option>
                      </select>
                    </div>
                  </div>
                </div>
                <button type="submit" class="btn btn-primary">Subir y continuar</button>
              <?php echo form_close(); ?>

            <?php elseif (($step ?? '') === 'map'): ?>
              <div class="alert alert-info">
                <p><?php echo _l('importar_productos_map_help'); ?></p>
                <p class="text-muted mbot0"><?php echo _l('importar_productos_required_note'); ?></p>
              </div>

              <?php if (!empty($errors ?? [])): ?>
                <div class="alert alert-danger">
                  <ul class="mbot0">
                    <?php foreach ($errors as $e): ?>
                      <li><?php echo html_escape($e); ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

              <h5><?php echo _l('importar_productos_preview_headers'); ?></h5>
              <div class="table-responsive">
                <table class="table table-bordered table-condensed">
                  <tr>
                    <?php foreach (($headers ?? []) as $h): ?>
                      <th><?php echo html_escape((string) $h); ?></th>
                    <?php endforeach; ?>
                  </tr>
                </table>
              </div>

              <h5 class="mtop20"><?php echo _l('importar_productos_preview_rows'); ?></h5>
              <div class="table-responsive" style="max-height:320px; overflow:auto;">
                <table class="table table-bordered table-condensed">
                  <?php foreach (($preview_rows ?? []) as $r): ?>
                    <tr>
                      <?php foreach ($r as $c): ?>
                        <td><?php echo html_escape((string) $c); ?></td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                </table>
              </div>

              <h5 class="mtop20"><?php echo _l('importar_productos_mapping'); ?></h5>
              <?php echo form_open(admin_url('importar_productos/save_mapping')); ?>
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label><?php echo _l('importar_productos_duplicate_check'); ?></label>
                      <div class="checkbox"><label><input type="checkbox" name="duplicate_check" value="1" <?php echo (($duplicate_check ?? '0') === '1') ? 'checked' : ''; ?>> Activar</label></div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label><?php echo _l('importar_productos_duplicate_by'); ?></label>
                      <select name="duplicate_by" class="form-control">
                        <option value="description" <?php echo (($duplicate_by ?? 'description') === 'description') ? 'selected' : ''; ?>><?php echo _l('importar_productos_dup_description'); ?></option>
                        <option value="sku_code" <?php echo (($duplicate_by ?? '') === 'sku_code') ? 'selected' : ''; ?>><?php echo _l('importar_productos_dup_sku'); ?></option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="table-responsive">
                  <table class="table table-bordered">
                    <thead>
                      <tr>
                        <th>Columna (archivo)</th>
                        <th>Mapear a</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                        $hdrs = $headers ?? [];
                        $map  = $column_map ?? [];
                        foreach ($hdrs as $i => $label):
                      ?>
                        <tr>
                          <td><code><?php echo html_escape((string) $label); ?></code></td>
                          <td>
                            <select name="column_map[<?php echo (int) $i; ?>]" class="form-control">
                              <option value=""><?php echo _l('importar_productos_ignore'); ?></option>
                              <optgroup label="<?php echo _l('importar_productos_db_field'); ?>">
                                <?php foreach ($items_fields as $f): ?>
                                  <option value="db:<?php echo html_escape($f); ?>" <?php echo (($map[$i] ?? '') === 'db:'.$f) ? 'selected' : ''; ?>>
                                    <?php echo html_escape($f); ?>
                                  </option>
                                <?php endforeach; ?>
                              </optgroup>
                              <?php if (!empty($custom_fields)): ?>
                                <optgroup label="<?php echo _l('importar_productos_cf_field'); ?>">
                                  <?php foreach ($custom_fields as $cf): ?>
                                    <option value="cf:<?php echo (int) $cf['id']; ?>" <?php echo (($map[$i] ?? '') === 'cf:'.$cf['id']) ? 'selected' : ''; ?>>
                                      <?php echo html_escape($cf['name']); ?> (id <?php echo (int) $cf['id']; ?>)
                                    </option>
                                  <?php endforeach; ?>
                                </optgroup>
                              <?php endif; ?>
                            </select>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <button type="submit" class="btn btn-default"><?php echo _l('importar_productos_save_mapping'); ?></button>
              <?php echo form_close(); ?>

              <hr />
              <?php if (empty($errors ?? [])): ?>
                <?php echo form_open(admin_url('importar_productos/run')); ?>
                  <input type="hidden" name="simulate" value="1" />
                  <button type="submit" class="btn btn-info mright5"><?php echo _l('importar_productos_simulate'); ?></button>
                <?php echo form_close(); ?>

                <?php echo form_open(admin_url('importar_productos/run')); ?>
                  <input type="hidden" name="simulate" value="0" />
                  <button type="submit" class="btn btn-primary" onclick="return confirm('¿Importar productos a la base de datos?');"><?php echo _l('importar_productos_import'); ?></button>
                <?php echo form_close(); ?>
              <?php else: ?>
                <div class="alert alert-warning mtop10">Corrige el mapeo para habilitar simulación e importación.</div>
              <?php endif; ?>

            <?php elseif (($step ?? '') === 'results'): ?>
              <?php $lr = $last_result ?? null; ?>
              <?php if (!$lr): ?>
                <div class="alert alert-warning">No hay resultados. Vuelve a subir un archivo.</div>
              <?php else: ?>
                <div class="alert <?php echo !empty($lr['simulate']) ? 'alert-info' : 'alert-success'; ?>">
                  <?php echo !empty($lr['simulate']) ? _l('importar_productos_results_simulated') : _l('importar_productos_results_imported'); ?>
                  <br />
                  Procesadas: <strong><?php echo (int) ($lr['imported'] ?? 0); ?></strong> &mdash;
                  Omitidas / con error: <strong><?php echo (int) ($lr['skipped'] ?? 0); ?></strong>
                </div>

                <?php if (!empty($lr['row_errors'])): ?>
                  <h5>Errores por fila (máx. 300)</h5>
                  <div class="table-responsive" style="max-height:280px; overflow:auto;">
                    <table class="table table-bordered table-condensed">
                      <?php foreach ($lr['row_errors'] as $er): ?>
                        <tr><td><?php echo html_escape($er); ?></td></tr>
                      <?php endforeach; ?>
                    </table>
                  </div>
                <?php endif; ?>

                <?php if (!empty($lr['simulate']) && !empty($lr['sim_rows'])): ?>
                  <h5>Vista simulada (máx. 80 filas)</h5>
                  <div class="table-responsive" style="max-height:360px; overflow:auto;">
                    <table class="table table-bordered table-condensed">
                      <thead>
                        <tr>
                          <th>Fila</th>
                          <th>Insert (tblitems)</th>
                          <th>Campos personalizados</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($lr['sim_rows'] as $sr): ?>
                          <tr>
                            <td><?php echo (int) $sr['row']; ?></td>
                            <td><pre class="mbot0" style="white-space:pre-wrap;font-size:11px;"><?php echo html_escape(json_encode($sr['insert'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre></td>
                            <td><pre class="mbot0" style="white-space:pre-wrap;font-size:11px;"><?php echo html_escape(json_encode($sr['customs'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              <?php endif; ?>

              <a href="<?php echo admin_url('importar_productos/cancel'); ?>" class="btn btn-default mtop15">Nueva importación</a>

            <?php endif; ?>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
