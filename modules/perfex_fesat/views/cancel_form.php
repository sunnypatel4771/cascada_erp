<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <?= _l('fe_sat_cancel_form_heading'); ?>
                        </h4>
                        <hr class="hr-panel-heading" />

                        <p class="text-muted"><?= _l('fe_sat_cancel_form_subheading'); ?></p>

                        <!-- Invoice Information -->
                        <div class="alert alert-info">
                            <strong><?= _l('invoice'); ?> #<?= $invoice->id; ?></strong> - <?= format_invoice_number($invoice->id); ?><br>
                            <strong><?= _l('fe_sat_panel_uuid'); ?>:</strong> <?= htmlspecialchars($document->digibox_uuid); ?><br>
                            <strong><?= _l('client'); ?>:</strong> <?= htmlspecialchars($invoice->client->company ?? ''); ?>
                        </div>

                        <!-- Cancellation Warning -->
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle"></i>
                            <strong><?= _l('fe_sat_cancel_acceptance_required'); ?></strong>
                            <p class="mtop10">
                                <?= _l('fe_sat_cancel_confirm_text'); ?>
                            </p>
                        </div>

                        <!-- Cancellation Form -->
                        <?= form_open(admin_url('fe_sat/cancel/' . $invoice->id)); ?>

                        <div class="form-group">
                            <label for="motivo" class="control-label">
                                <?= _l('fe_sat_cancel_motivo'); ?> <span class="text-danger">*</span>
                            </label>
                            <select name="motivo" id="motivo" class="form-control selectpicker" required>
                                <option value="">-- <?= _l('select'); ?> --</option>
                                <option value="01"><?= _l('fe_sat_cancel_motivo_01'); ?></option>
                                <option value="02"><?= _l('fe_sat_cancel_motivo_02'); ?></option>
                                <option value="03"><?= _l('fe_sat_cancel_motivo_03'); ?></option>
                                <option value="04"><?= _l('fe_sat_cancel_motivo_04'); ?></option>
                            </select>
                        </div>

                        <div class="form-group" id="folio-sustitucion-group" style="display:none;">
                            <label for="folio_sustitucion" class="control-label">
                                <?= _l('fe_sat_cancel_folio_sustitucion'); ?> <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   name="folio_sustitucion"
                                   id="folio_sustitucion"
                                   class="form-control"
                                   placeholder="XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX">
                            <p class="help-block">
                                <?= _l('fe_sat_cancel_folio_sustitucion_help'); ?>
                            </p>
                        </div>

                        <div class="checkbox checkbox-primary mtop20">
                            <input type="checkbox" name="confirm" id="confirm_cancel" value="1" required>
                            <label for="confirm_cancel">
                                <?= _l('fe_sat_cancel_confirm_text'); ?>
                            </label>
                        </div>

                        <hr />

                        <div class="btn-bottom-toolbar text-right">
                            <a href="<?= admin_url('invoices/list_invoices/' . $invoice->id); ?>" class="btn btn-default">
                                <i class="fa fa-arrow-left"></i> <?= _l('fe_sat_cancel_back'); ?>
                            </a>
                            <button type="submit" class="btn btn-danger">
                                <i class="fa fa-ban"></i> <?= _l('fe_sat_cancel_submit'); ?>
                            </button>
                        </div>

                        <?= form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
    $(document).ready(function() {
        console.log('Cancel form script loaded');

        // Initialize selectpicker
        $('#motivo').selectpicker();

        // Show/hide folio_sustitucion field based on motivo selection
        // Use 'changed.bs.select' event for bootstrap-select plugin
        $('#motivo').on('changed.bs.select', function() {
            var motivo = $(this).val();
            var $folioGroup = $('#folio-sustitucion-group');
            var $folioInput = $('#folio_sustitucion');

            console.log('Motivo changed to:', motivo); // Debug logging

            if (motivo === '01') {
                $folioGroup.show(); // Use show() instead of slideDown() for immediate visibility
                $folioInput.prop('required', true);
                console.log('Showing folio sustitucion field');
            } else {
                $folioGroup.hide(); // Use hide() instead of slideUp()
                $folioInput.prop('required', false);
                $folioInput.val('');
                console.log('Hiding folio sustitucion field');
            }
        });

        // Also add regular change event as fallback
        $('#motivo').on('change', function() {
            var motivo = $(this).val();
            var $folioGroup = $('#folio-sustitucion-group');
            var $folioInput = $('#folio_sustitucion');

            console.log('Change event - Motivo:', motivo);

            if (motivo === '01') {
                $folioGroup.show();
                $folioInput.prop('required', true);
            } else {
                $folioGroup.hide();
                $folioInput.prop('required', false);
                $folioInput.val('');
            }
        });

        // Form validation
        $('form').on('submit', function(e) {
            var motivo = $('#motivo').val();
            var folioSustitucion = $.trim($('#folio_sustitucion').val());
            var confirmed = $('#confirm_cancel').is(':checked');

            console.log('Form submit - Motivo:', motivo, 'Folio:', folioSustitucion, 'Confirmed:', confirmed);

            if (!motivo) {
                alert('<?= _l('fe_sat_cancel_motivo'); ?> es requerido');
                e.preventDefault();
                return false;
            }

            if (motivo === '01' && !folioSustitucion) {
                alert('<?= _l('fe_sat_cancel_folio_sustitucion'); ?> es requerido para motivo 01');
                e.preventDefault();
                return false;
            }

            if (!confirmed) {
                alert('<?= _l('fe_sat_cancel_confirm'); ?>');
                e.preventDefault();
                return false;
            }

            // Confirm action
            if (!confirm('¿Está seguro que desea cancelar este CFDI?\n\nEsta acción enviará una solicitud de cancelación al SAT.')) {
                e.preventDefault();
                return false;
            }

            return true;
        });
    });
</script>
