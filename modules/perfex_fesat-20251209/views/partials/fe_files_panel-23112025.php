<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="panel_s mtop10">
    <div class="panel-body">
        <h4 class="no-margin"><?= _l('fe_sat_panel_title'); ?></h4>
        <hr class="hr-panel-heading" />
        <?php if (empty($document)) { ?>
            <p class="text-muted"><?= _l('fe_sat_panel_no_document'); ?></p>
            <?php if (staff_can('generate', 'fe_sat') && isset($invoice->id)) { ?>
                <a href="<?= admin_url('perfex_fesat/form/' . $invoice->id); ?>" class="btn btn-primary"><?= _l('fe_sat_generate_invoice_action'); ?></a>
            <?php } ?>
        <?php } else { ?>
            <div class="row">
                <div class="col-md-6">
                    <p><strong><?= _l('fe_sat_panel_status'); ?>:</strong>
                        <?php if ($document->status === 'success') { ?>
                            <span class="label label-success"><?= _l('fe_sat_status_success'); ?></span>
                        <?php } elseif ($document->status === 'error') { ?>
                            <span class="label label-danger"><?= _l('fe_sat_status_error'); ?></span>
                        <?php } else { ?>
                            <span class="label label-warning"><?= _l('fe_sat_status_pending'); ?></span>
                        <?php } ?>
                    </p>
                    <?php if (!empty($document->message)) { ?>
                        <p class="text-muted"><?= html_escape($document->message); ?></p>
                    <?php } ?>
                    <?php if (!empty($document->digibox_uuid)) { ?>
                        <p><strong><?= _l('fe_sat_panel_uuid'); ?>:</strong> <?= html_escape($document->digibox_uuid); ?></p>
                    <?php } ?>
                </div>
                <div class="col-md-6 text-right">
                    <?php if ($document->status === 'success') { ?>
                        <?php if (!empty($document->pdf_path) && is_file($document->pdf_path)) { ?>
                            <a target="_blank" href="<?= admin_url('perfex_fesat/view-file/' . $document->id . '/pdf'); ?>" class="btn btn-default"><?= _l('fe_sat_panel_view_pdf'); ?></a>
                        <?php } ?>
                        <?php if (!empty($document->xml_path) && is_file($document->xml_path)) { ?>
                            <a target="_blank" href="<?= admin_url('perfex_fesat/view-file/' . $document->id . '/xml'); ?>" class="btn btn-default"><?= _l('fe_sat_panel_view_xml'); ?></a>
                        <?php } ?>
                        <?php if (staff_can('permission_send_email', 'fe_sat')) { ?>
                            <a href="<?= admin_url('perfex_fesat/send-email/' . $document->id); ?>" class="btn btn-primary"><?= _l('fe_sat_panel_send_email'); ?></a>
                        <?php } ?>
                    <?php } else { ?>
                        <?php if (staff_can('generate', 'fe_sat') && isset($invoice->id)) { ?>
                            <a href="<?= admin_url('perfex_fesat/form/' . $invoice->id); ?>" class="btn btn-warning"><?= _l('fe_sat_panel_retry'); ?></a>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
    </div>
</div>
