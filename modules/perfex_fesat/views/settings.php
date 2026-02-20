<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-7">
                <?= form_open_multipart(admin_url('fe_sat/settings')); ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?= _l('fe_sat_settings_header_connection'); ?></h4>
                        <hr class="hr-panel-heading" />
                        <div class="form-group">
                            <label for="base_url"><?= _l('fe_sat_setting_base_url'); ?></label>
                            <input type="text" id="base_url" name="base_url" class="form-control"
                                   value="<?= html_escape($options['base_url'] ?? ''); ?>"
                                   placeholder="https://testtimbrado.digibox.com.mx/api/autenticacion/autenticarbasico">
                            <small class="text-muted">
                                <strong>Test:</strong> https://testtimbrado.digibox.com.mx/api/autenticacion/autenticarbasico<br>
                                <strong>Production:</strong> https://timbrado.digibox.com.mx/api/autenticacion/autenticarbasico
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="username"><?= _l('fe_sat_setting_username'); ?></label>
                            <input type="text" id="username" name="username" class="form-control"
                                   value="<?= html_escape($options['username'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="password"><?= _l('fe_sat_setting_password'); ?></label>
                            <input type="password" id="password" name="password" class="form-control" value="">
                            <?php if (!empty($options['has_password'])) { ?>
                                <small class="text-muted"><?= _l('fe_sat_setting_secret_saved'); ?></small>
                            <?php } ?>
                        </div>
                        <div class="form-group">
                            <label for="api_key"><?= _l('fe_sat_setting_api_key'); ?></label>
                            <input type="text" id="api_key" name="api_key" class="form-control" value="">
                            <?php if (!empty($options['has_api_key'])) { ?>
                                <small class="text-muted"><?= _l('fe_sat_setting_secret_saved'); ?></small>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?= _l('fe_sat_settings_header_defaults'); ?></h4>
                        <hr class="hr-panel-heading" />
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="series_default"><?= _l('fe_sat_setting_series_default'); ?></label>
                                <input type="text" id="series_default" name="series_default" class="form-control"
                                       value="<?= html_escape($options['series_default'] ?? 'A'); ?>">
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="folio_next"><?= _l('fe_sat_setting_folio_next'); ?></label>
                                <input type="text" id="folio_next" name="folio_next" class="form-control"
                                       value="<?= html_escape($options['folio_next'] ?? '1'); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="cfdi_use_default"><?= _l('fe_sat_setting_cfdi_use_default'); ?></label>
                                <input type="text" id="cfdi_use_default" name="cfdi_use_default" class="form-control"
                                       value="<?= html_escape($options['cfdi_use_default'] ?? 'G03'); ?>">
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="payment_method"><?= _l('fe_sat_setting_payment_method'); ?></label>
                                <input type="text" id="payment_method" name="payment_method" class="form-control"
                                       value="<?= html_escape($options['payment_method'] ?? 'PPD'); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="payment_form"><?= _l('fe_sat_setting_payment_form'); ?></label>
                                <input type="text" id="payment_form" name="payment_form" class="form-control"
                                       value="<?= html_escape($options['payment_form'] ?? '99'); ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="currency"><?= _l('fe_sat_setting_currency'); ?></label>
                                <input type="text" id="currency" name="currency" class="form-control"
                                       value="<?= html_escape($options['currency'] ?? 'MXN'); ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="company_regimen"><?= _l('fe_sat_setting_company_regimen'); ?></label>
                                <input type="text" id="company_regimen" name="company_regimen" class="form-control"
                                       value="<?= html_escape($options['company_regimen'] ?? '601'); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="storage_driver"><?= _l('fe_sat_setting_storage_driver'); ?></label>
                            <select id="storage_driver" name="storage_driver" class="selectpicker" data-width="100%">
                                <option value="local" <?= ($options['storage_driver'] ?? 'local') === 'local' ? 'selected' : ''; ?>><?= _l('fe_sat_storage_local'); ?></option>
                                <option value="s3" <?= ($options['storage_driver'] ?? 'local') === 's3' ? 'selected' : ''; ?>><?= _l('fe_sat_storage_s3'); ?></option>
                            </select>
                        </div>

                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" id="sandbox_mode" name="sandbox_mode" <?= !empty($options['sandbox_mode']) ? 'checked' : ''; ?>>
                            <label for="sandbox_mode"><?= _l('fe_sat_setting_sandbox'); ?></label>
                        </div>

                        <div class="checkbox checkbox-danger mtop10">
                            <input type="checkbox" id="ssl_verify_disabled" name="ssl_verify_disabled" <?= !empty($options['ssl_verify_disabled']) ? 'checked' : ''; ?>>
                            <label for="ssl_verify_disabled">
                                <strong>Disable SSL Verification</strong>
                                <small class="text-danger display-block">(Only for local development! Never enable in production)</small>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- CSD Certificate Settings for Cancellation -->
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <i class="fa fa-certificate"></i> CSD Certificate (For Cancellation)
                        </h4>
                        <hr class="hr-panel-heading" />

                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i> <strong>REQUIRED for CFDI Cancellation:</strong>
                            <p class="mtop5">According to DigiBox API documentation, CSD (Certificado de Sello Digital) certificate files are REQUIRED to cancel invoices.</p>
                            <p class="mtop5"><strong>You need 3 items:</strong></p>
                            <ul class="mtop5">
                                <li><strong>.cer file:</strong> CSD certificate (public key)</li>
                                <li><strong>.key file:</strong> CSD private key (encrypted)</li>
                                <li><strong>Password:</strong> Password to decrypt the .key file</li>
                            </ul>
                            <p class="mtop5"><strong>Where to get these:</strong> Download from SAT portal or ask your accountant. These are the same CSD files used for timbrado.</p>
                        </div>

                        <!-- CSD Certificate Upload -->
                        <div class="form-group">
                            <label for="csd_cer_file">
                                <i class="fa fa-certificate"></i> CSD Certificate File (.cer) <span class="text-danger">*</span>
                            </label>

                            <?php if (!empty($options['csd_cer_filename'])) { ?>
                                <div class="alert alert-success mtop5 mbot10">
                                    <i class="fa fa-check-circle"></i>
                                    <strong>Current file:</strong> <?= html_escape($options['csd_cer_filename']); ?>
                                    <br>
                                    <small>Uploaded: <?= html_escape($options['csd_cer_uploaded_at'] ?? 'Unknown'); ?></small>
                                </div>
                            <?php } ?>

                            <input type="file" id="csd_cer_file" name="csd_cer_file" class="form-control" accept=".cer">
                            <small class="text-muted">
                                <?= !empty($options['csd_cer_filename']) ? 'Upload a new file to replace the current one' : 'Select your CSD .cer certificate file'; ?>
                            </small>
                        </div>

                        <!-- CSD Private Key Upload -->
                        <div class="form-group">
                            <label for="csd_key_file">
                                <i class="fa fa-key"></i> CSD Private Key File (.key) <span class="text-danger">*</span>
                            </label>

                            <?php if (!empty($options['csd_key_filename'])) { ?>
                                <div class="alert alert-success mtop5 mbot10">
                                    <i class="fa fa-check-circle"></i>
                                    <strong>Current file:</strong> <?= html_escape($options['csd_key_filename']); ?>
                                    <br>
                                    <small>Uploaded: <?= html_escape($options['csd_key_uploaded_at'] ?? 'Unknown'); ?></small>
                                </div>
                            <?php } ?>

                            <input type="file" id="csd_key_file" name="csd_key_file" class="form-control" accept=".key">
                            <small class="text-muted">
                                <?= !empty($options['csd_key_filename']) ? 'Upload a new file to replace the current one' : 'Select your CSD .key private key file'; ?>
                            </small>
                        </div>

                        <!-- CSD Password -->
                        <div class="form-group">
                            <label for="csd_password">
                                <i class="fa fa-lock"></i> CSD Private Key Password <span class="text-danger">*</span>
                            </label>
                            <input type="password" id="csd_password" name="csd_password" class="form-control"
                                   value="" autocomplete="new-password"
                                   placeholder="Enter your CSD private key password">
                            <?php if (!empty($options['has_csd_password'])) { ?>
                                <small class="text-success">
                                    <i class="fa fa-check"></i> Password saved (leave blank to keep current password)
                                </small>
                            <?php } ?>
                        </div>

                        <div class="alert alert-warning">
                            <i class="fa fa-shield"></i> <strong>Security:</strong>
                            <ul class="mtop5">
                                <li>Files are stored securely in <code>uploads/fe_sat/csd/</code> directory</li>
                                <li>Files are protected with .htaccess (not accessible via web browser)</li>
                                <li>Only administrators with "Manage Settings" permission can access this page</li>
                                <li>Passwords are encrypted before storage</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <button type="submit" class="btn btn-primary"><?= _l('fe_sat_settings_save'); ?></button>
                        <a href="<?= admin_url('fe_sat/test-connection'); ?>" class="btn btn-default"><?= _l('fe_sat_settings_test_connection'); ?></a>
                    </div>
                </div>
                <?= form_close(); ?>
            </div>
            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?= _l('fe_sat_settings_recent_logs'); ?></h4>
                        <hr class="hr-panel-heading" />
                        <?php if (empty($recent_logs)) { ?>
                            <p class="text-muted"><?= _l('fe_sat_settings_no_logs'); ?></p>
                        <?php } else { ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                    <tr>
                                        <th><?= _l('fe_sat_settings_log_direction'); ?></th>
                                        <th><?= _l('fe_sat_settings_log_endpoint'); ?></th>
                                        <th><?= _l('fe_sat_settings_log_status'); ?></th>
                                        <th><?= _l('fe_sat_settings_log_date'); ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($recent_logs as $log) { ?>
                                        <tr>
                                            <td><?= ucfirst(html_escape($log['direction'])); ?></td>
                                            <td><?= html_escape($log['endpoint']); ?></td>
                                            <td><?= html_escape($log['http_code']); ?></td>
                                            <td><?= _dt($log['created_at']); ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?= _l('fe_sat_settings_diagnostics'); ?></h4>
                        <hr class="hr-panel-heading" />
                        <?php if (empty($diagnostics)) { ?>
                            <p class="text-muted"><?= _l('fe_sat_settings_diagnostics_empty'); ?></p>
                        <?php } else { ?>
                            <ul class="list-unstyled mtop10">
                                <li><strong><?= _l('fe_sat_settings_diagnostics_status'); ?>:</strong>
                                    <?php if ($diagnostics['success']) { ?>
                                        <span class="label label-success"><?= _l('fe_sat_ok'); ?></span>
                                    <?php } else { ?>
                                        <span class="label label-danger"><?= _l('fe_sat_error'); ?></span>
                                    <?php } ?>
                                </li>
                                <li class="mtop5"><strong><?= _l('fe_sat_settings_diagnostics_message'); ?>:</strong><br>
                                    <code style="white-space: pre-wrap; word-break: break-word;"><?= html_escape($diagnostics['message']); ?></code>
                                </li>
                                <?php if (!empty($diagnostics['http_code'])) { ?>
                                <li class="mtop5"><strong>HTTP Code:</strong> <?= $diagnostics['http_code']; ?></li>
                                <?php } ?>
                                <li class="mtop5"><strong><?= _l('fe_sat_settings_diagnostics_date'); ?>:</strong> <?= _dt($diagnostics['time']); ?></li>
                            </ul>
                        <?php } ?>
                        <div class="mtop15">
                            <p class="text-muted"><small><strong>Debug Info:</strong></small></p>
                            <ul class="list-unstyled text-muted" style="font-size: 11px;">
                                <li>Base URL: <code><?= html_escape($options['base_url'] ?? 'Not set'); ?></code></li>
                                <li>Has Username: <?= !empty($options['username']) ? 'Yes' : 'No'; ?></li>
                                <li>Has Password: <?= !empty($options['has_password']) ? 'Yes' : 'No'; ?></li>
                                <li>Has API Key: <?= !empty($options['has_api_key']) ? 'Yes' : 'No'; ?></li>
                                <li>Sandbox Mode: <?= !empty($options['sandbox_mode']) ? 'Yes' : 'No'; ?></li>
                                <li>SSL Verify Disabled: <?= !empty($options['ssl_verify_disabled']) ? '<span class="text-danger">Yes</span>' : 'No'; ?></li>
                                <li>cURL Enabled: <?= function_exists('curl_version') ? 'Yes' : '<span class="text-danger">No</span>'; ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
