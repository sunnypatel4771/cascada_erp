<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="row">
    <div class="col-md-12">
        <div class="panel_s">
            <div class="panel-body">
                <h1 class="h3 no-margin"><?php echo $title; ?></h1>
                <p class="text-muted"><?php echo $subtitle; ?></p>
            </div>
        </div>
    </div>
</div>

<?php echo form_open('ramos/automation/settings', ['id' => 'automation-settings-form', 'class' => 'form-horizontal']); ?>

<div class="row">
    <div class="col-md-8">
        <!-- Automation Schedule Section -->
        <div class="panel panel-default">
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
                                   value="on" <?php if ($automation_enabled) echo 'checked'; ?>>
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

                <!-- Schedule Hours -->
                <div class="form-group" id="schedule-hours-group">
                    <label class="col-sm-3 control-label">
                        <?php echo _l('ramos_settings_schedule_hours'); ?>
                    </label>
                    <div class="col-sm-9">
                        <select id="schedule_hours" name="schedule_hours[]" class="form-control select-multiple" multiple="multiple">
                            <?php foreach ($available_hours as $hour => $label): ?>
                                <option value="<?php echo $hour; ?>" 
                                        <?php if (in_array($hour, $schedule_hours)) echo 'selected'; ?>>
                                    <?php echo sprintf('%02d:00 (%s)', $hour, $hour < 12 ? 'AM' : 'PM'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                            <strong><?php echo $last_automation_run_date; ?></strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Route Generation Section -->
        <div class="panel panel-default">
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
                                   value="on" <?php if ($route_generation_auto) echo 'checked'; ?>>
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
                               min="1" max="100">
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
                               placeholder="Route">
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
                    <div class="col-sm-4">
                        <input type="time" id="default_route_start_time" name="default_route_start_time" 
                               class="form-control" value="<?php echo substr($default_route_start_time, 0, 5); ?>">
                        <p class="help-block">
                            <?php echo _l('ramos_settings_default_route_start_time_help'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="form-group">
            <div class="col-sm-12">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fa fa-save"></i>
                    <?php echo _l('save'); ?>
                </button>
                <button type="button" class="btn btn-default btn-lg" onclick="history.back();">
                    <i class="fa fa-arrow-left"></i>
                    <?php echo _l('back'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Sidebar Info -->
    <div class="col-md-4">
        <div class="panel panel-info">
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

<?php echo form_close(); ?>

<script>
$(function() {
    // Initialize select2 for multiple selection
    $('#schedule_hours').select2({
        allowClear: true,
        placeholder: '<?php echo _l("ramos_settings_select_hours"); ?>'
    });

    // Toggle schedule hours visibility based on enabled state
    function toggleScheduleHours() {
        const isEnabled = $('#automation_enabled').is(':checked');
        $('#schedule-hours-group').slideToggle(isEnabled);
    }

    $('#automation_enabled').on('change', toggleScheduleHours);
    
    // Set initial visibility
    if (!$('#automation_enabled').is(':checked')) {
        $('#schedule-hours-group').hide();
    }

    // Form submission handler
    $('#automation-settings-form').on('submit', function(e) {
        e.preventDefault();

        // Collect selected hours
        const selectedHours = $('#schedule_hours').val() || [];

        const formData = {
            automation_enabled: $('#automation_enabled').is(':checked') ? 'on' : 'off',
            schedule_hours: selectedHours.join(','),
            route_generation_auto: $('#route_generation_auto').is(':checked') ? 'on' : 'off',
            default_max_stops: $('#default_max_stops').val(),
            default_route_prefix: $('#default_route_prefix').val(),
            default_route_start_time: $('#default_route_start_time').val() + ':00'
        };

        $.ajax({
            url: '<?php echo admin_url("ramos/automation/settings"); ?>',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert_float('success', response.message);
                    // Optionally reload to reflect changes
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    alert_float('danger', response.message || '<?php echo _l("ramos_settings_save_error"); ?>');
                }
            },
            error: function(xhr, status, error) {
                alert_float('danger', '<?php echo _l("ramos_settings_save_error"); ?>');
                console.error('AJAX Error:', error);
            }
        });
    });
});
</script>
