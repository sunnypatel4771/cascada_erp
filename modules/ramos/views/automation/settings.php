<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">

        <!-- Page Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-col md:tw-flex-row tw-justify-between tw-items-start md:tw-items-center tw-gap-4 tw-mb-4">
                    <div>
                        <h4 class="tw-text-2xl tw-font-semibold tw-text-slate-900 tw-mb-1">
                            <?php echo html_escape($title); ?>
                        </h4>
                        <p class="tw-text-slate-500 tw-mb-0">
                            <?php echo html_escape($subtitle); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Permission Alert -->
        <?php if (!isset($can_edit) || !$can_edit): ?>
            <div class="row">
                <div class="col-md-8">
                    <div class="alert alert-warning">
                        <i class="fa fa-lock"></i>
                        <strong><?php echo _l('access_denied'); ?></strong><br>
                        <?php echo _l('access_denied_description'); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Settings Form -->
        <form id="automation-settings-form" class="form-horizontal">
            <div class="row">
                <div class="col-md-8">

                    <!-- Automation Schedule Section -->
                    <div class="panel_s">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                <i class="fa fa-clock-o"></i>
                                <?php echo _l('ramos_settings_automation_enabled'); ?>
                            </h3>
                        </div>
                        <div class="panel-body">
                            <!-- Enable/Disable Toggle -->
                            <div class="form-group">
                                <div class="col-sm-12">
                                    <div class="checkbox checkbox-primary checkbox-inline">
                                        <input type="checkbox" id="automation_enabled" name="automation_enabled" 
                                               value="on" <?php if ($automation_enabled) echo 'checked'; ?> <?php if (!isset($can_edit) || !$can_edit) echo 'disabled'; ?>>
                                        <label for="automation_enabled">
                                            <?php echo _l('ramos_settings_enable_scheduled_automation'); ?>
                                        </label>
                                    </div>
                                    <p class="help-block">
                                        <?php echo _l('ramos_settings_automation_enabled_help'); ?>
                                    </p>
                                </div>
                            </div>

                            <hr>

                            <!-- Schedule Hours and Minutes -->
                            <div class="form-group" id="schedule-hours-group">
                                <label class="col-sm-3 control-label">
                                    <?php echo _l('ramos_settings_schedule_hours'); ?>
                                </label>
                                <div class="col-sm-9">
                                    <div class="row">
                                        <div class="col-xs-6">
                                            <label class="small text-muted">Hour(s)</label>
                                            <select id="schedule_hours" name="schedule_hours[]" class="form-control select-multiple" multiple="multiple" <?php if (!isset($can_edit) || !$can_edit) echo 'disabled'; ?>>
                                                <?php foreach ($available_hours as $hour => $label): ?>
                                                    <option value="<?php echo $hour; ?>" 
                                                            <?php if (in_array($hour, $schedule_hours)) echo 'selected'; ?>>
                                                        <?php echo sprintf('%02d:00 (%s)', $hour, $hour < 12 ? 'AM' : 'PM'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-xs-6">
                                            <label class="small text-muted">Minute(s)</label>
                                            <select id="schedule_minutes" name="schedule_minutes[]" class="form-control select-multiple" multiple="multiple" <?php if (!isset($can_edit) || !$can_edit) echo 'disabled'; ?>>
                                                <?php for ($min = 0; $min < 60; $min += 5): ?>
                                                    <option value="<?php echo $min; ?>" 
                                                            <?php if (in_array($min, $schedule_minutes)) echo 'selected'; ?>>
                                                        <?php echo sprintf('%02d', $min); ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <p class="help-block">
                                        <?php echo _l('ramos_settings_schedule_hours_help'); ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Last Run Info -->
                            <div class="form-group">
                                <label class="col-sm-3 control-label">
                                    <?php echo _l('ramos_settings_last_run'); ?>
                                </label>
                                <div class="col-sm-9">
                                    <p class="form-control-static">
                                        <?php echo $last_automation_run_date; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Route Generation Section -->
                    <div class="panel_s">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                <i class="fa fa-road"></i>
                                <?php echo _l('ramos_settings_route_generation'); ?>
                            </h3>
                        </div>
                        <div class="panel-body">
                            <!-- Auto-Generate Routes Toggle -->
                            <div class="form-group">
                                <div class="col-sm-12">
                                    <div class="checkbox checkbox-primary checkbox-inline">
                                        <input type="checkbox" id="route_generation_auto" name="route_generation_auto" 
                                               value="on" <?php if ($route_generation_auto) echo 'checked'; ?> <?php if (!isset($can_edit) || !$can_edit) echo 'disabled'; ?>>
                                        <label for="route_generation_auto">
                                            <?php echo _l('ramos_settings_auto_generate_routes'); ?>
                                        </label>
                                    </div>
                                    <p class="help-block">
                                        <?php echo _l('ramos_settings_auto_generate_routes_help'); ?>
                                    </p>
                                </div>
                            </div>

                            <hr>

                            <!-- Max Stops Per Route -->
                            <div class="form-group">
                                <label class="col-sm-3 control-label">
                                    <?php echo _l('ramos_settings_default_max_stops'); ?>
                                </label>
                                <div class="col-sm-4">
                                    <input type="number" id="default_max_stops" name="default_max_stops" 
                                           class="form-control" value="<?php echo $default_max_stops; ?>"
                                           min="1" max="100" <?php if (!isset($can_edit) || !$can_edit) echo 'disabled'; ?>>
                                    <p class="help-block">
                                        <?php echo _l('ramos_settings_default_max_stops_help'); ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Route Name Prefix -->
                            <div class="form-group">
                                <label class="col-sm-3 control-label">
                                    <?php echo _l('ramos_settings_default_route_prefix'); ?>
                                </label>
                                <div class="col-sm-4">
                                    <input type="text" id="default_route_prefix" name="default_route_prefix" 
                                           class="form-control" value="<?php echo $default_route_prefix; ?>"
                                           placeholder="Route" <?php if (!isset($can_edit) || !$can_edit) echo 'disabled'; ?>>
                                    <p class="help-block">
                                        <?php echo _l('ramos_settings_default_route_prefix_help'); ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Route Start Time -->
                            <div class="form-group">
                                <label class="col-sm-3 control-label">
                                    <?php echo _l('ramos_settings_default_route_start_time'); ?>
                                </label>
                                <div class="col-sm-9">
                                    <div class="row">
                                        <div class="col-xs-4">
                                            <label class="small text-muted">Hour</label>
                                            <select id="route_start_hour" name="route_start_hour" class="form-control" <?php if (!isset($can_edit) || !$can_edit) echo 'disabled'; ?>>
                                                <?php for ($h = 0; $h < 24; $h++): ?>
                                                    <option value="<?php echo sprintf('%02d', $h); ?>" 
                                                            <?php if ($h == intval(substr($default_route_start_time, 0, 2))) echo 'selected'; ?>>
                                                        <?php echo sprintf('%02d:00 (%s)', $h, $h < 12 ? 'AM' : 'PM'); ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-xs-4">
                                            <label class="small text-muted">Minute</label>
                                            <select id="route_start_minute" name="route_start_minute" class="form-control" <?php if (!isset($can_edit) || !$can_edit) echo 'disabled'; ?>>
                                                <?php for ($min = 0; $min < 60; $min += 5): ?>
                                                    <option value="<?php echo sprintf('%02d', $min); ?>" 
                                                            <?php if ($min == intval(substr($default_route_start_time, 3, 2))) echo 'selected'; ?>>
                                                        <?php echo sprintf('%02d', $min); ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-xs-4">
                                            <label class="small text-muted">Second</label>
                                            <select id="route_start_second" name="route_start_second" class="form-control" <?php if (!isset($can_edit) || !$can_edit) echo 'disabled'; ?>>
                                                <option value="00" selected>00</option>
                                            </select>
                                        </div>
                                    </div>
                                    <p class="help-block">
                                        <?php echo _l('ramos_settings_default_route_start_time_help'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <?php if (isset($can_edit) && $can_edit): ?>
                        <div class="form-group mtop20">
                            <div class="col-sm-12">
                                <button type="button" id="save-settings-btn" class="btn btn-primary btn-lg">
                                    <i class="fa fa-save"></i>
                                    <?php echo _l('save'); ?>
                                </button>
                                <button type="button" id="run-automation-btn" class="btn btn-success btn-lg">
                                    <i class="fa fa-play"></i>
                                    <?php echo _l('ramos_run_automation_now'); ?>
                                </button>
                                <a href="<?php echo admin_url('ramos/automation'); ?>" class="btn btn-default btn-lg">
                                    <i class="fa fa-arrow-left"></i>
                                    <?php echo _l('back'); ?>
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="form-group mtop20">
                            <div class="col-sm-12">
                                <a href="<?php echo admin_url('ramos/automation'); ?>" class="btn btn-default btn-lg">
                                    <i class="fa fa-arrow-left"></i>
                                    <?php echo _l('back'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- Sidebar Info -->
                <div class="col-md-4">
                    <div class="panel_s">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                <i class="fa fa-info-circle"></i>
                                <?php echo _l('information'); ?>
                            </h3>
                        </div>
                        <div class="panel-body">
                            <p><strong><?php echo _l('ramos_settings_how_it_works'); ?></strong></p>
                            <ol class="small">
                                <li><?php echo _l('ramos_settings_info_step1'); ?></li>
                                <li><?php echo _l('ramos_settings_info_step2'); ?></li>
                                <li><?php echo _l('ramos_settings_info_step3'); ?></li>
                            </ol>

                            <hr>

                            <p class="small text-muted">
                                <i class="fa fa-clock-o"></i>
                                <?php echo _l('ramos_settings_info_cron'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </form>

    </div><!-- .content -->
</div><!-- #wrapper -->

<?php init_tail(); ?>

<script>
jQuery(document).ready(function($) {
    // Toggle schedule hours
    $('#automation_enabled').on('change', function() {
        $('#schedule-hours-group').slideToggle($(this).is(':checked'));
    });
    
    if (!$('#automation_enabled').is(':checked')) {
        $('#schedule-hours-group').hide();
    }

    // Save button click
    $('#save-settings-btn').click(function(e) {
        e.preventDefault();
        
        var formData = {
            automation_enabled: $('#automation_enabled').is(':checked') ? 'on' : 'off',
            schedule_hours: ($('#schedule_hours').val() || []).join(','),
            schedule_minutes: ($('#schedule_minutes').val() || []).join(','),
            route_generation_auto: $('#route_generation_auto').is(':checked') ? 'on' : 'off',
            default_max_stops: $('#default_max_stops').val(),
            default_route_prefix: $('#default_route_prefix').val(),
            route_start_hour: $('#route_start_hour').val(),
            route_start_minute: $('#route_start_minute').val(),
            route_start_second: $('#route_start_second').val()
        };

        $.ajax({
            url: '<?php echo admin_url("ramos/automation/settings"); ?>',
            type: 'POST',
            data: formData,
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                if (response.success) {
                    alert_float('success', response.message || '<?php echo _l("ramos_settings_saved_successfully"); ?>');
                } else {
                    alert_float('danger', response.message || '<?php echo _l("ramos_settings_save_error"); ?>');
                }
            },
            error: function(xhr, status, error) {
                alert_float('danger', 'Error saving settings');
            }
        });
    });

    // Run automation button click
    $('#run-automation-btn').click(function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var originalText = $btn.html();
        
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> <?php echo _l("ramos_running_automation"); ?>');

        $.ajax({
            url: '<?php echo admin_url("ramos/automation/run"); ?>',
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                if (response.success) {
                    alert_float('success', '<?php echo _l("ramos_automation_completed"); ?> - ' + response.orders_processed + ' <?php echo _l("ramos_orders_processed"); ?>, ' + response.batches_created + ' <?php echo _l("ramos_batches_created"); ?>');
                    
                    // Reload page to show updated last run time
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    alert_float('danger', response.message || '<?php echo _l("ramos_automation_failed"); ?>');
                    $btn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                alert_float('danger', '<?php echo _l("ramos_automation_error"); ?>');
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });
</script>
