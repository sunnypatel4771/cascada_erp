<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h4 class="no-margin"><?= _l('fe_sat_form_heading', format_invoice_number($invoice->id)); ?></h4>
                                <p class="mtop5 text-muted"><?= _l('fe_sat_form_subheading'); ?></p>
                            </div>
                            <div class="col-md-4 text-right">
                                <a href="<?= admin_url('invoices/list_invoices/' . $invoice->id); ?>" class="btn btn-default"><?= _l('fe_sat_form_back_to_invoice'); ?></a>
                            </div>
                        </div>
                        <hr class="hr-panel-heading" />
                        <?php if ($document && $document->status === 'success') { ?>
                            <div class="alert alert-success">
                                <?= _l('fe_sat_form_already_stamped'); ?>
                                <div class="checkbox checkbox-primary mtop10">
                                    <input type="checkbox" id="force_regenerate" form="fe-sat-generate" name="force_regenerate" value="1">
                                    <label for="force_regenerate"><?= _l('fe_sat_form_force_regenerate'); ?></label>
                                </div>
                            </div>
                        <?php } elseif ($document && $document->status === 'error') { ?>
                            <div class="alert alert-danger">
                                <?= _l('fe_sat_form_previous_error'); ?>: <?= html_escape($document->message); ?>
                            </div>
                        <?php } ?>

                        <?= form_open(admin_url('perfex_fesat/generate/' . $invoice->id), ['id' => 'fe-sat-generate']); ?>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="customer_name"><?= _l('fe_sat_form_customer_name'); ?></label>
                                <input type="text" class="form-control" name="customer_name" id="customer_name"
                                       value="<?= html_escape($defaults['customer_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="customer_rfc"><?= _l('fe_sat_form_customer_rfc'); ?></label>
                                <input type="text" class="form-control" name="customer_rfc" id="customer_rfc"
                                       value="<?= html_escape($defaults['customer_rfc'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="customer_regimen"><?= _l('fe_sat_form_customer_regimen'); ?></label>
                                <input type="text" class="form-control" name="customer_regimen" id="customer_regimen"
                                       value="<?= html_escape($defaults['customer_regimen'] ?? '601'); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label for="serie"><?= _l('fe_sat_form_serie'); ?></label>
                                <input type="text" class="form-control" name="serie" id="serie"
                                       value="<?= html_escape($defaults['serie'] ?? 'A'); ?>" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="folio"><?= _l('fe_sat_form_folio'); ?></label>
                                <input type="text" class="form-control" name="folio" id="folio"
                                       value="<?= html_escape($defaults['folio'] ?? '1'); ?>" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="uso_cfdi"><?= _l('fe_sat_form_uso_cfdi'); ?></label>
                                <input type="text" class="form-control" name="uso_cfdi" id="uso_cfdi"
                                       value="<?= html_escape($defaults['uso_cfdi'] ?? 'G03'); ?>" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="currency"><?= _l('fe_sat_form_currency'); ?></label>
                                <input type="text" class="form-control" name="currency" id="currency"
                                       value="<?= html_escape($defaults['currency'] ?? 'MXN'); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label for="payment_method"><?= _l('fe_sat_form_payment_method'); ?></label>
                                <input type="text" class="form-control" name="payment_method" id="payment_method"
                                       value="<?= html_escape($defaults['payment_method'] ?? 'PPD'); ?>" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="payment_form"><?= _l('fe_sat_form_payment_form'); ?></label>
                                <input type="text" class="form-control" name="payment_form" id="payment_form"
                                       value="<?= html_escape($defaults['payment_form'] ?? '99'); ?>" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label><?= _l('fe_sat_form_sandbox_mode'); ?></label>
                                <div class="checkbox checkbox-primary">
                                    <input type="checkbox" id="sandbox" name="sandbox" value="1" <?= !empty($defaults['sandbox']) ? 'checked' : ''; ?>>
                                    <label for="sandbox"><?= _l('fe_sat_form_sandbox_label'); ?></label>
                                </div>
                            </div>
                        </div>

                        <h5><?= _l('fe_sat_form_items'); ?></h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th><?= _l('fe_sat_form_item_description'); ?></th>
                                        <th class="text-right"><?= _l('fe_sat_form_item_qty'); ?></th>
                                        <th class="text-right"><?= _l('fe_sat_form_item_rate'); ?></th>
                                        <th class="text-right"><?= _l('fe_sat_form_item_total'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $row) { ?>
                                        <tr>
                                            <td><?= html_escape($row['description']); ?></td>
                                            <td class="text-right"><?= number_format((float) $row['qty'], 2); ?></td>
                                            <td class="text-right"><?= app_format_money((float) $row['rate'], $invoice->currency_name); ?></td>
                                            <td class="text-right"><?= app_format_money((float) $row['qty'] * (float) $row['rate'], $invoice->currency_name); ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-right"><?= _l('fe_sat_form_subtotal'); ?></td>
                                        <td class="text-right"><?= app_format_money($totals['subtotal'], $invoice->currency_name); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-right"><?= _l('fe_sat_form_iva'); ?></td>
                                        <td class="text-right"><?= app_format_money($totals['iva'], $invoice->currency_name); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-right bold"><?= _l('fe_sat_form_total'); ?></td>
                                        <td class="text-right bold"><?= app_format_money($totals['total'], $invoice->currency_name); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="text-right text-muted"><?= html_escape($totals['amount_in_words']); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="text-right">
                            <button type="submit" class="btn btn-primary"><?= _l('fe_sat_form_submit'); ?></button>
                            <a href="<?= admin_url('invoices/list_invoices/' . $invoice->id); ?>" class="btn btn-default"><?= _l('fe_sat_form_cancel'); ?></a>
                        </div>
                        <?= form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
