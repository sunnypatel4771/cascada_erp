<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$statusLabels = [
    'red'    => 'label-danger',
    'yellow' => 'label-warning',
    'green'  => 'label-success',
];
$canEditConsole       = $can_edit ?? false;
$canManageShifts      = $can_manage_shifts ?? false;
$availableModules     = $available_modules ?? [];
$pickerActiveModules  = $picker_active_modules ?? [];
$pickerActiveShifts   = $picker_active_shifts ?? [];
$isFullAdmin          = $is_full_admin ?? false;

// A picker (manage_shifts only) without an active module needs to see the claim panel.
$showClaimPanel = $canManageShifts && !$isFullAdmin && empty($pickerActiveModules);
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-col md:tw-flex-row tw-justify-between tw-items-start md:tw-items-center tw-gap-4 tw-mb-4">
                    <div>
                        <h4 class="tw-text-2xl tw-font-semibold tw-text-slate-900 tw-mb-1"><?php echo html_escape($title); ?></h4>
                        <p class="tw-text-slate-500 tw-mb-0"><?php echo html_escape($subtitle); ?></p>
                    </div>
                    <div class="tw-flex tw-gap-2">
                        <?php if ($isFullAdmin) : ?>
                        <a href="<?php echo admin_url('ramos/picking'); ?>" class="btn btn-default">
                            <i class="fa-regular fa-gear tw-mr-1"></i><?php echo _l('settings'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($showClaimPanel) : ?>
                    <div class="panel_s tw-mb-4">
                        <div class="panel-body">
                            <h5 class="tw-text-base tw-font-semibold tw-mb-3"><?php echo _l('ramos_picking_claim_module_heading'); ?></h5>
                            <?php if (empty($availableModules)) : ?>
                                <div class="alert alert-warning tw-text-sm"><?php echo _l('ramos_picking_no_modules_available'); ?></div>
                            <?php else : ?>
                                <?php echo form_open(admin_url('ramos/picking/claim_shift/' . $availableModules[0]['id'])); ?>
                                    <div class="row tw-items-end">
                                        <div class="col-sm-8 col-md-5">
                                            <div class="form-group">
                                                <label class="control-label"><?php echo _l('ramos_picking_claim_module_label'); ?></label>
                                                <select name="module_id" id="ramos-claim-module" class="selectpicker form-control" data-width="100%" data-live-search="true">
                                                    <?php foreach ($availableModules as $am) : ?>
                                                        <option value="<?php echo (int) $am['id']; ?>"
                                                            data-action="<?php echo admin_url('ramos/picking/claim_shift/' . (int) $am['id']); ?>">
                                                            <?php echo html_escape($am['display_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-sm-4 col-md-3">
                                            <div class="form-group">
                                                <button type="submit" id="ramos-claim-btn" class="btn btn-primary btn-block">
                                                    <i class="fa-regular fa-play tw-mr-1"></i><?php echo _l('ramos_picking_claim_shift_button'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php echo form_close(); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif (!$has_modules) : ?>
                    <div class="alert alert-info tw-text-sm"><?php echo _l('ramos_picking_console_no_access'); ?></div>
                <?php endif; ?>

                <?php if (!$showClaimPanel && $canManageShifts && !$isFullAdmin && !empty($pickerActiveShifts)) : ?>
                    <?php foreach ($pickerActiveShifts as $activeShift) : ?>
                    <div class="tw-mb-3 tw-text-right">
                        <a href="<?php echo admin_url('ramos/picking/release_shift/' . (int) $activeShift['module_id']); ?>"
                           class="btn btn-danger btn-sm"
                           onclick="return confirm('<?php echo _l('ramos_picking_end_own_shift_confirm'); ?>');">
                            <i class="fa-regular fa-stop tw-mr-1"></i><?php echo _l('ramos_picking_end_own_shift_button'); ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="row" id="ramos-console-modules">
                    <?php foreach ($module_data as $entry) : ?>
                        <?php
                        $this->load->view('ramos/picking/partials/module_card', [
                            'module'       => $entry['module'],
                            'orders'       => $entry['orders'],
                            'statusLabels' => $statusLabels,
                            'can_edit'     => $canEditConsole,
                        ]);
                        ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
(function($) {
    "use strict";

    // Update claim-shift form action when picker selects a different module from the dropdown.
    var $claimSelect = $('#ramos-claim-module');
    var $claimBtn    = $('#ramos-claim-btn');
    if ($claimSelect.length) {
        $claimSelect.on('change', function () {
            var $selected = $claimSelect.find('option:selected');
            var action = $selected.data('action');
            if (action) {
                $claimSelect.closest('form').attr('action', action);
            }
        });
    }

    var refreshInterval = 20000;
    var timerId = null;

    function renderModules(html) {
        var $container = $('#ramos-console-modules');
        if (!$container.length) {
            return;
        }
        $container.html(html);
    }

    function scheduleNext() {
        timerId = setTimeout(fetchUpdates, refreshInterval);
    }

    function fetchUpdates() {
        $.get(admin_url + 'ramos/picking/console_refresh')
            .done(function(response) {
                if (response && response.success && response.html) {
                    renderModules(response.html);
                }
            })
            .always(function() {
                scheduleNext();
            });
    }

    scheduleNext();
})(jQuery);
</script>
