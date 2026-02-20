<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-7">
                <?= form_open(admin_url('perfex_fesat/settings')); ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?= _l('fe_sat_settings_header_connection'); ?></h4>
                        <hr class="hr-panel-heading" />
                        <div class="form-group">
                            <label for="base_url"><?= _l('fe_sat_setting_base_url'); ?></label>
                            <input type="text" id="base_url" name="base_url" class="form-control"
                                   value="<?= html_escape($options['base_url'] ?? ''); ?>" placeholder="https://api.digibox.com.mx">
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
                    </div>
                </div>
                <div class="panel_s">
                    <div class="panel-body">
                        <button type="submit" class="btn btn-primary"><?= _l('fe_sat_settings_save'); ?></button>
                        <a href="<?= admin_url('perfex_fesat/test-connection'); ?>" class="btn btn-default"><?= _l('fe_sat_settings_test_connection'); ?></a>
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
                                <li><strong><?= _l('fe_sat_settings_diagnostics_status'); ?>:</strong> <?= $diagnostics['success'] ? _l('fe_sat_ok') : _l('fe_sat_error'); ?></li>
                                <li><strong><?= _l('fe_sat_settings_diagnostics_message'); ?>:</strong> <?= html_escape($diagnostics['message']); ?></li>
                                <li><strong><?= _l('fe_sat_settings_diagnostics_date'); ?>:</strong> <?= _dt($diagnostics['time']); ?></li>
                            </ul>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
