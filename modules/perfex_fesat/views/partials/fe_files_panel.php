<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="panel_s mtop10">
    <div class="panel-body">
        <h4 class="no-margin"><?= _l('fe_sat_panel_title'); ?></h4>
        <hr class="hr-panel-heading" />
        <?php if (empty($document)) { ?>
            <p class="text-muted"><?= _l('fe_sat_panel_no_document'); ?></p>

            <?php
            // Show sample files even when no document exists
            $samplePdf = FCPATH . 'media/90DCC64C-D2BF-4A63-A97D-5543208E5E10.pdf';
            $sampleXml = FCPATH . 'media/90DCC64C-D2BF-4A63-A97D-5543208E5E10.xml';
            $showSamplePdf = is_file($samplePdf);
            $showSampleXml = is_file($sampleXml);

            if ($showSamplePdf || $showSampleXml) { ?>
                <div class="mtop10">
                    <?php if ($showSamplePdf) { ?>
                        <a target="_blank" href="<?= base_url('media/90DCC64C-D2BF-4A63-A97D-5543208E5E10.pdf'); ?>" class="btn btn-default">
                            <?= _l('fe_sat_panel_view_pdf'); ?> <small></small>
                        </a>
                    <?php } ?>

                    <?php if ($showSampleXml) { ?>
                        <a target="_blank" href="<?= base_url('media/90DCC64C-D2BF-4A63-A97D-5543208E5E10.xml'); ?>" class="btn btn-default">
                            <?= _l('fe_sat_panel_view_xml'); ?> <small></small>
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if (staff_can('generate', 'fe_sat') && isset($invoice->id)) { ?>
                <a href="<?= admin_url('fe_sat/form/' . $invoice->id); ?>" class="btn btn-primary mtop10"><?= _l('fe_sat_generate_invoice_action'); ?></a>
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
                        <?php
                        // Check if actual files exist, otherwise use sample files from media folder
                        $hasPdf = !empty($document->pdf_path) && is_file($document->pdf_path);
                        $hasXml = !empty($document->xml_path) && is_file($document->xml_path);

                        // Sample files from media folder
                        $samplePdf = FCPATH . 'media/90DCC64C-D2BF-4A63-A97D-5543208E5E10.pdf';
                        $sampleXml = FCPATH . 'media/90DCC64C-D2BF-4A63-A97D-5543208E5E10.xml';

                        $showPdf = $hasPdf || is_file($samplePdf);
                        $showXml = $hasXml || is_file($sampleXml);

                        // Check cancellation status
                        $isCancelled = isset($document->cancellation_status) && in_array($document->cancellation_status, ['cancelled', 'pending']);
                        $canCancel = !empty($document->digibox_uuid) && !$isCancelled;
                        ?>

                        <?php if ($showPdf) { ?>
                            <a target="_blank"
                               href="<?= $hasPdf ? admin_url('fe_sat/view-file/' . $document->id . '/pdf') : base_url('media/90DCC64C-D2BF-4A63-A97D-5543208E5E10.pdf'); ?>"
                               class="btn btn-default">
                                <?= _l('fe_sat_panel_view_pdf'); ?>
                                <?php if (!$hasPdf) { ?>
                                    <small>(Sample)</small>
                                <?php } ?>
                            </a>
                        <?php } ?>

                        <?php if ($showXml) { ?>
                            <a target="_blank"
                               href="<?= $hasXml ? admin_url('fe_sat/view-file/' . $document->id . '/xml') : base_url('media/90DCC64C-D2BF-4A63-A97D-5543208E5E10.xml'); ?>"
                               class="btn btn-default">
                                <?= _l('fe_sat_panel_view_xml'); ?>
                                <?php if (!$hasXml) { ?>
                                    <small>(Sample)</small>
                                <?php } ?>
                            </a>
                        <?php } ?>

                        <?php
                        // Show Send Email only if actual files exist
                        // Show Generate/Retry if no actual files exist (even if status is success)
                        if ($hasPdf && $hasXml) {
                            // Real files exist - show Send Email button
                            if (staff_can('permission_send_email', 'fe_sat')) { ?>
                                <a href="<?= admin_url('fe_sat/send-email/' . $document->id); ?>" class="btn btn-primary"><?= _l('fe_sat_panel_send_email'); ?></a>
                            <?php }

                            // Show Cancel button if invoice can be cancelled
                            if ($canCancel && staff_can('generate', 'fe_sat') && isset($invoice->id)) { ?>
                                <a href="<?= admin_url('fe_sat/cancel-form/' . $invoice->id); ?>" class="btn btn-danger">
                                    <i class="fa fa-ban"></i> <?= _l('fe_sat_cancel_invoice_action'); ?>
                                </a>
                            <?php }
                        } else {
                            // No real files - show Generate button even if status is 'success'
                            if (staff_can('generate', 'fe_sat') && isset($invoice->id)) { ?>
                                <a href="<?= admin_url('fe_sat/form/' . $invoice->id); ?>" class="btn btn-primary"><?= _l('fe_sat_generate_invoice_action'); ?></a>
                            <?php }
                        }
                        ?>

                        <?php
                        // Show cancellation status if applicable
                        if ($isCancelled) { ?>
                            <div class="mtop10">
                                <?php if ($document->cancellation_status === 'pending') { ?>
                                    <span class="label label-warning"><?= _l('fe_sat_cancel_status_pending'); ?></span>
                                <?php } elseif ($document->cancellation_status === 'cancelled') { ?>
                                    <span class="label label-danger"><?= _l('fe_sat_cancel_status_cancelled'); ?></span>
                                <?php } elseif ($document->cancellation_status === 'rejected') { ?>
                                    <span class="label label-danger"><?= _l('fe_sat_cancel_status_rejected'); ?></span>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    <?php } else { ?>
                        <?php if (staff_can('generate', 'fe_sat') && isset($invoice->id)) { ?>
                            <a href="<?= admin_url('fe_sat/form/' . $invoice->id); ?>" class="btn btn-warning"><?= _l('fe_sat_panel_retry'); ?></a>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
    </div>
</div>
