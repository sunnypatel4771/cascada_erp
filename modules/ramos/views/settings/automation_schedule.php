<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-text-2xl tw-font-semibold tw-text-slate-900 tw-mb-1">
                            <i class="<?php echo RAMOS_MODULE_ICON; ?> tw-mr-1"></i>
                            <?php echo html_escape($title); ?>
                        </h4>
                        <p class="tw-text-slate-500 tw-mb-4">
                            <?php echo html_escape($subtitle); ?>
                        </p>

                        <form id="automation-schedule-form" class="form-horizontal">
            
            <!-- Automation Enabled -->
            <div class="form-group">
                <label class="col-sm-4 control-label">
                    <?php echo _l('ramos_settings_enable_automation'); ?>
                </label>
                <div class="col-sm-8">
                    <div class="checkbox checkbox-inline">
                        <input type="checkbox" id="automation_enabled" name="automation_enabled" 
                               <?php if ($automation_enabled) echo 'checked'; ?>>
                        <label for="automation_enabled">
                            <?php echo _l('ramos_settings_automation_description'); ?>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Schedule Hours -->
            <div class="form-group" id="schedule-hours-group">
                <label class="col-sm-4 control-label">
                    <?php echo _l('ramos_settings_schedule_hours'); ?>
                </label>
                <div class="col-sm-8">
                    <select id="schedule_hours" name="schedule_hours[]" class="form-control" multiple>
                        <?php foreach ($available_hours as $hour => $label): ?>
                            <option value="<?php echo $hour; ?>" 
                                    <?php if (in_array($hour, $schedule_hours)) echo 'selected'; ?>>
                                <?php echo sprintf('%02d:00 (%s)', $hour, $hour < 12 ? 'AM' : 'PM'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">
                        <?php echo _l('ramos_settings_schedule_hours_help'); ?>
                    </small>
                </div>
            </div>

            <!-- Last Run Info -->
            <div class="form-group">
                <label class="col-sm-4 control-label">
                    <?php echo _l('ramos_settings_last_automation_run'); ?>
                </label>
                <div class="col-sm-8">
                    <p class="form-control-static">
                        <strong><?php echo $last_automation_run_date; ?></strong>
                    </p>
                </div>
            </div>

            <hr class="mtop20">

            <!-- Route Generation Settings -->
            <div class="form-group">
                <label class="col-sm-4 control-label">
                    <?php echo _l('ramos_settings_route_generation'); ?>
                </label>
                <div class="col-sm-8">
                    <div class="checkbox checkbox-inline">
                        <input type="checkbox" id="route_generation_auto" name="route_generation_auto" 
                               <?php if ($route_generation_auto) echo 'checked'; ?>>
                        <label for="route_generation_auto">
                            <?php echo _l('ramos_settings_auto_generate_routes'); ?>
                        </label>
                    </div>
                    <small class="form-text text-muted">
                        <?php echo _l('ramos_settings_auto_generate_routes_description'); ?>
                    </small>
                </div>
            </div>

            <!-- Default Max Stops -->
            <div class="form-group">
                <label class="col-sm-4 control-label">
                    <?php echo _l('ramos_settings_default_max_stops'); ?>
                </label>
                <div class="col-sm-8">
                    <input type="number" id="default_max_stops" name="default_max_stops" 
                           class="form-control" value="<?php echo $default_max_stops; ?>"
                           min="1" max="100">
                    <small class="form-text text-muted">
                        <?php echo _l('ramos_settings_default_max_stops_description'); ?>
                    </small>
                </div>
            </div>

            <!-- Default Route Prefix -->
            <div class="form-group">
                <label class="col-sm-4 control-label">
                    <?php echo _l('ramos_settings_default_route_prefix'); ?>
                </label>
                <div class="col-sm-8">
                    <input type="text" id="default_route_prefix" name="default_route_prefix" 
                           class="form-control" value="<?php echo $default_route_prefix; ?>"
                           placeholder="Route">
                    <small class="form-text text-muted">
                        <?php echo _l('ramos_settings_default_route_prefix_description'); ?>
                    </small>
                </div>
            </div>

            <!-- Default Route Start Time -->
            <div class="form-group">
                <label class="col-sm-4 control-label">
                    <?php echo _l('ramos_settings_default_route_start_time'); ?>
                </label>
                <div class="col-sm-8">
                    <input type="time" id="default_route_start_time" name="default_route_start_time" 
                           class="form-control" value="<?php echo substr($default_route_start_time, 0, 5); ?>">
                    <small class="form-text text-muted">
                        <?php echo _l('ramos_settings_default_route_start_time_description'); ?>
                    </small>
                </div>
            </div>

            <div class="form-group mtop20">
                <div class="col-sm-8 col-sm-offset-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i>
                        <?php echo _l('save'); ?>
                    </button>
                </div>
            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
$(function() {
    // Initialize multiple select (Perfex loads select2 in admin layout)
    if ($.fn.select2) {
        $('#schedule_hours').select2({
            allowClear: true,
            placeholder: '<?php echo _l("ramos_settings_select_hours"); ?>'
        });
    }

    // Toggle schedule hours visibility
    function toggleScheduleHours() {
        const isEnabled = $('#automation_enabled').is(':checked');
        $('#schedule-hours-group').toggle(isEnabled);
    }

    $('#automation_enabled').on('change', toggleScheduleHours);
    toggleScheduleHours(); // Initial state

    // Form submission
    $('#automation-schedule-form').on('submit', function(e) {
        e.preventDefault();

        // Get selected hours as comma-separated
        const selectedHours = $('#schedule_hours').val() || [];
        
        let formData = new FormData(this);
        formData.set('schedule_hours', selectedHours.join(','));

        $.ajax({
            url: '<?php echo admin_url("ramos/settings/automation_schedule"); ?>',
            type: 'POST',
            data: formData,
            dataType: 'json',
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert_float('success', response.message);
                } else {
                    alert_float('danger', response.message);
                }
            },
            error: function() {
                alert_float('danger', '<?php echo _l("ramos_settings_save_error"); ?>');
            }
        });
    });
});
</script>
