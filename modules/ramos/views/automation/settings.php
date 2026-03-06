<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <!-- Page Header -->
        <div class="row tw-mb-6">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-col md:tw-flex-row tw-justify-between tw-items-start md:tw-items-center tw-gap-4">
                    <div>
                        <h4 class="tw-text-3xl tw-font-bold tw-text-slate-900 tw-mb-2">
                            <i class="fa-solid fa-sliders tw-mr-2 tw-text-primary"></i><?php echo html_escape($title); ?>
                        </h4>
                        <p class="tw-text-slate-600 tw-mb-0">
                            <?php echo html_escape($subtitle); ?>
                        </p>
                    </div>
                    <?php if (!$can_edit) : ?>
                        <div class="tw-flex tw-items-center tw-gap-2 tw-px-4 tw-py-2 tw-bg-amber-50 tw-border tw-border-amber-200 tw-rounded-lg">
                            <i class="fa-solid fa-lock tw-text-amber-600"></i>
                            <span class="tw-text-sm tw-text-amber-800 tw-font-medium"><?php echo _l('view_only'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Settings Form -->
        <form id="automation-settings-form" method="post" action="<?php echo admin_url('ramos/automation/settings'); ?>">
            <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
            <div class="row">
                <div class="col-lg-8">
                    <!-- Automation Schedule Card -->
                    <div class="panel_s tw-mb-6 tw-shadow-sm hover:tw-shadow-md tw-transition-shadow">
                        <div class="panel-heading tw-bg-gradient-to-r tw-from-blue-50 tw-to-blue-25 tw-border-b tw-border-blue-100">
                            <h4 class="tw-mb-0 tw-text-slate-900">
                                <i class="fa-solid fa-clock tw-mr-2 tw-text-blue-600"></i>
                                <span class="tw-font-semibold"><?php echo _l('ramos_settings_automation_schedule'); ?></span>
                                <span class="tw-text-xs tw-font-normal tw-text-slate-500 tw-ml-2">
                                    (<?php echo $automation_enabled ? '<span class="tw-text-green-600"><i class="fa-solid fa-check-circle tw-mr-1"></i>Active</span>' : '<span class="tw-text-slate-400"><i class="fa-solid fa-circle tw-mr-1"></i>Inactive</span>'; ?>)
                                </span>
                            </h4>
                        </div>
                        <div class="panel-body tw-space-y-5">
                            <!-- Enable/Disable Toggle -->
                            <div class="tw-relative tw-p-4 tw-bg-slate-50 tw-rounded-lg tw-border tw-border-slate-200 <?php echo $can_edit ? 'hover:tw-bg-slate-75 tw-cursor-pointer tw-transition-colors' : ''; ?>">
                                <label class="tw-flex tw-items-center tw-gap-4 tw-cursor-pointer <?php echo !$can_edit ? 'tw-opacity-75' : ''; ?>">
                                    <div class="tw-relative tw-flex tw-items-center">
                                        <input 
                                            type="checkbox" 
                                            name="automation_enabled" 
                                            id="automation_enabled"
                                            <?php echo $automation_enabled ? 'checked' : ''; ?>
                                            <?php echo !$can_edit ? 'disabled' : ''; ?>
                                            class="tw-rounded tw-w-5 tw-h-5 tw-cursor-pointer tw-accent-blue-600"
                                        />
                                    </div>
                                    <div>
                                        <span class="tw-text-slate-900 tw-font-semibold tw-block">
                                            <?php echo _l('ramos_settings_enable_automation'); ?>
                                        </span>
                                        <small class="tw-text-slate-500 tw-block tw-mt-1">
                                            <?php echo _l('ramos_settings_automation_description'); ?>
                                        </small>
                                    </div>
                                </label>
                            </div>

                            <!-- Simple Time Settings with Dropdowns -->
                            <div id="schedule-time-picker" style="<?php echo !$automation_enabled ? 'display: none;' : ''; ?>" class="tw-mt-6 tw-transition-all tw-duration-300">
                                <div class="tw-bg-blue-50 tw-border tw-border-blue-200 tw-rounded-lg tw-p-5">
                                    <label class="tw-text-slate-900 tw-font-semibold tw-block tw-mb-4">
                                        <i class="fa-solid fa-calendar-clock tw-mr-2 tw-text-blue-600"></i>Schedule Settings
                                    </label>

                                    <!-- Run Daily Option -->
                                    <div class="tw-mb-4 tw-p-4 tw-bg-white tw-rounded-lg tw-border tw-border-blue-100">
                                        <label class="tw-flex tw-items-center tw-gap-3 tw-cursor-pointer">
                                            <input 
                                                type="checkbox" 
                                                name="schedule_run_daily" 
                                                id="schedule_run_daily"
                                                class="tw-w-5 tw-h-5 tw-cursor-pointer tw-accent-blue-600 tw-rounded"
                                                <?php echo $schedule_run_daily ? 'checked' : ''; ?>
                                                <?php echo !$can_edit ? 'disabled' : ''; ?>
                                            />
                                            <span class="tw-text-slate-900 tw-font-semibold">
                                                <i class="fa-solid fa-repeat tw-mr-2 tw-text-blue-600"></i>Run Daily
                                            </span>
                                        </label>
                                        <small class="tw-text-slate-500 tw-block tw-mt-2 tw-ml-8">
                                            Enable to run automation every day at the same time
                                        </small>
                                    </div>

                                    <!-- Three Dropdowns Layout -->
                                    <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-4">
                                        <!-- Date/Day Dropdown -->
                                        <div class="tw-space-y-2">
                                            <label for="schedule_date" class="tw-text-slate-900 tw-font-semibold tw-block tw-text-sm">
                                                <i class="fa-solid fa-calendar tw-mr-2 tw-text-blue-600"></i>Date/Day
                                            </label>
                                            <select name="schedule_date" id="schedule_date" class="form-control tw-py-2 tw-px-3 tw-border tw-border-slate-300 tw-rounded-lg focus:tw-ring-2 focus:tw-ring-blue-500 focus:tw-border-transparent tw-transition-all" <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                                <option value="">Select Day...</option>
                                                <option value="monday" <?php echo $schedule_date === 'monday' ? 'selected' : ''; ?>>Monday</option>
                                                <option value="tuesday" <?php echo $schedule_date === 'tuesday' ? 'selected' : ''; ?>>Tuesday</option>
                                                <option value="wednesday" <?php echo $schedule_date === 'wednesday' ? 'selected' : ''; ?>>Wednesday</option>
                                                <option value="thursday" <?php echo $schedule_date === 'thursday' ? 'selected' : ''; ?>>Thursday</option>
                                                <option value="friday" <?php echo $schedule_date === 'friday' ? 'selected' : ''; ?>>Friday</option>
                                                <option value="saturday" <?php echo $schedule_date === 'saturday' ? 'selected' : ''; ?>>Saturday</option>
                                                <option value="sunday" <?php echo $schedule_date === 'sunday' ? 'selected' : ''; ?>>Sunday</option>
                                            </select>
                                            <small class="tw-text-slate-500 tw-text-xs tw-block">Which day to run</small>
                                        </div>

                                        <!-- Hour Dropdown -->
                                        <div class="tw-space-y-2">
                                            <label for="schedule_hour" class="tw-text-slate-900 tw-font-semibold tw-block tw-text-sm">
                                                <i class="fa-solid fa-hourglass-start tw-mr-2 tw-text-blue-600"></i>Hour
                                            </label>
                                            <select name="schedule_hour" id="schedule_hour" class="form-control tw-py-2 tw-px-3 tw-border tw-border-slate-300 tw-rounded-lg focus:tw-ring-2 focus:tw-ring-blue-500 focus:tw-border-transparent tw-transition-all" <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                                <option value="">Select Hour...</option>
                                                <?php for ($h = 0; $h < 24; $h++) : ?>
                                                    <option value="<?php echo $h; ?>" <?php echo $schedule_hour == $h ? 'selected' : ''; ?>>
                                                        <?php echo str_pad($h, 2, '0', STR_PAD_LEFT); ?>:00 (<?php echo $h < 12 ? 'AM' : 'PM'; ?>)
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                            <small class="tw-text-slate-500 tw-text-xs tw-block">Hour of the day</small>
                                        </div>

                                        <!-- Minutes Dropdown -->
                                        <div class="tw-space-y-2">
                                            <label for="schedule_minutes" class="tw-text-slate-900 tw-font-semibold tw-block tw-text-sm">
                                                <i class="fa-solid fa-timer tw-mr-2 tw-text-blue-600"></i>Minutes
                                            </label>
                                            <select name="schedule_minutes" id="schedule_minutes" class="form-control tw-py-2 tw-px-3 tw-border tw-border-slate-300 tw-rounded-lg focus:tw-ring-2 focus:tw-ring-blue-500 focus:tw-border-transparent tw-transition-all" <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                                <option value="0" <?php echo $schedule_minutes == 0 ? 'selected' : ''; ?>>00 minutes</option>
                                                <option value="10" <?php echo $schedule_minutes == 10 ? 'selected' : ''; ?>>10 minutes</option>
                                                <option value="20" <?php echo $schedule_minutes == 20 ? 'selected' : ''; ?>>20 minutes</option>
                                                <option value="30" <?php echo $schedule_minutes == 30 ? 'selected' : ''; ?>>30 minutes</option>
                                                <option value="40" <?php echo $schedule_minutes == 40 ? 'selected' : ''; ?>>40 minutes</option>
                                                <option value="50" <?php echo $schedule_minutes == 50 ? 'selected' : ''; ?>>50 minutes</option>
                                            </select>
                                            <small class="tw-text-slate-500 tw-text-xs tw-block">Minutes offset in hour</small>
                                        </div>
                                    </div>

                                    <!-- Time Summary -->
                                    <div class="tw-mt-4 tw-p-3 tw-bg-white tw-rounded-lg tw-border tw-border-blue-100">
                                        <p class="tw-text-sm tw-text-slate-700">
                                            <i class="fa-solid fa-info-circle tw-mr-2 tw-text-blue-600"></i>
                                            <strong>Next Run:</strong> 
                                            <span id="schedule-summary" class="tw-font-mono tw-text-blue-600">
                                                Select day, hour, and seconds
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Last Run Info -->
                            <div class="tw-relative tw-p-4 tw-bg-gradient-to-r tw-from-emerald-50 tw-to-emerald-25 tw-rounded-lg tw-border tw-border-emerald-200">
                                <div class="tw-flex tw-items-start tw-gap-3">
                                    <i class="fa-solid fa-circle-check tw-text-emerald-600 tw-mt-0.5 tw-text-lg"></i>
                                    <div>
                                        <small class="tw-text-slate-900 tw-font-semibold tw-block">
                                            <?php echo _l('ramos_settings_last_automation_run'); ?>
                                        </small>
                                        <span class="tw-font-mono tw-text-sm tw-text-slate-600 tw-block tw-mt-1">
                                            <?php echo html_escape($last_automation_run_date); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Route Generation Card -->
                    <div class="panel_s tw-shadow-sm hover:tw-shadow-md tw-transition-shadow">
                        <div class="panel-heading tw-bg-gradient-to-r tw-from-purple-50 tw-to-purple-25 tw-border-b tw-border-purple-100">
                            <h4 class="tw-mb-0 tw-text-slate-900">
                                <i class="fa-solid fa-map tw-mr-2 tw-text-purple-600"></i>
                                <span class="tw-font-semibold"><?php echo _l('ramos_settings_route_generation'); ?></span>
                                <span class="tw-text-xs tw-font-normal tw-text-slate-500 tw-ml-2">
                                    (<?php echo $route_generation_auto ? '<span class="tw-text-green-600"><i class="fa-solid fa-check-circle tw-mr-1"></i>Enabled</span>' : '<span class="tw-text-slate-400"><i class="fa-solid fa-circle tw-mr-1"></i>Disabled</span>'; ?>)
                                </span>
                            </h4>
                        </div>
                        <div class="panel-body tw-space-y-5">
                            <!-- Auto-Generate Routes -->
                            <div class="tw-relative tw-p-4 tw-bg-slate-50 tw-rounded-lg tw-border tw-border-slate-200 <?php echo $can_edit ? 'hover:tw-bg-slate-75 tw-cursor-pointer tw-transition-colors' : ''; ?>">
                                <label class="tw-flex tw-items-center tw-gap-4 tw-cursor-pointer <?php echo !$can_edit ? 'tw-opacity-75' : ''; ?>">
                                    <div class="tw-relative tw-flex tw-items-center">
                                        <input 
                                            type="checkbox" 
                                            name="route_generation_auto" 
                                            id="route_generation_auto"
                                            <?php echo $route_generation_auto ? 'checked' : ''; ?>
                                            <?php echo !$can_edit ? 'disabled' : ''; ?>
                                            class="tw-rounded tw-w-5 tw-h-5 tw-cursor-pointer tw-accent-purple-600"
                                        />
                                    </div>
                                    <div>
                                        <span class="tw-text-slate-900 tw-font-semibold tw-block">
                                            <?php echo _l('ramos_settings_auto_generate_routes'); ?>
                                        </span>
                                        <small class="tw-text-slate-500 tw-block tw-mt-1">
                                            <?php echo _l('ramos_settings_auto_generate_routes_description'); ?>
                                        </small>
                                    </div>
                                </label>
                            </div>

                            <!-- Route Configuration Grid -->
                            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                                <!-- Max Stops -->
                                <div class="tw-space-y-2">
                                    <label for="default_max_stops" class="tw-text-slate-900 tw-font-semibold tw-block tw-text-sm">
                                        <i class="fa-solid fa-square-list tw-mr-2 tw-text-purple-600"></i><?php echo _l('ramos_settings_default_max_stops'); ?>
                                    </label>
                                    <input 
                                        type="number" 
                                        name="default_max_stops" 
                                        id="default_max_stops"
                                        value="<?php echo $default_max_stops; ?>"
                                        min="1" 
                                        max="100"
                                        class="form-control tw-py-2 tw-px-3 tw-border tw-border-slate-300 tw-rounded-lg focus:tw-ring-2 focus:tw-ring-purple-500 focus:tw-border-transparent tw-transition-all"
                                        <?php echo !$can_edit ? 'disabled' : ''; ?>
                                    />
                                    <small class="tw-text-slate-500 tw-text-xs tw-block">
                                        <?php echo _l('ramos_settings_default_max_stops_description'); ?>
                                    </small>
                                </div>

                                <!-- Route Prefix -->
                                <div class="tw-space-y-2">
                                    <label for="default_route_prefix" class="tw-text-slate-900 tw-font-semibold tw-block tw-text-sm">
                                        <i class="fa-solid fa-tag tw-mr-2 tw-text-purple-600"></i><?php echo _l('ramos_settings_default_route_prefix'); ?>
                                    </label>
                                    <input 
                                        type="text" 
                                        name="default_route_prefix" 
                                        id="default_route_prefix"
                                        value="<?php echo html_escape($default_route_prefix); ?>"
                                        class="form-control tw-py-2 tw-px-3 tw-border tw-border-slate-300 tw-rounded-lg focus:tw-ring-2 focus:tw-ring-purple-500 focus:tw-border-transparent tw-transition-all"
                                        placeholder="e.g., Route, Delivery, R-"
                                        <?php echo !$can_edit ? 'disabled' : ''; ?>
                                    />
                                    <small class="tw-text-slate-500 tw-text-xs tw-block">
                                        <?php echo _l('ramos_settings_default_route_prefix_description'); ?>
                                    </small>
                                </div>

                                <!-- Start Time -->
                                <div class="tw-space-y-2">
                                    <label for="default_route_start_time" class="tw-text-slate-900 tw-font-semibold tw-block tw-text-sm">
                                        <i class="fa-solid fa-clock tw-mr-2 tw-text-purple-600"></i><?php echo _l('ramos_settings_default_route_start_time'); ?>
                                    </label>
                                    <input 
                                        type="time" 
                                        name="default_route_start_time" 
                                        id="default_route_start_time"
                                        value="<?php echo html_escape(substr($default_route_start_time, 0, 5)); ?>"
                                        class="form-control tw-py-2 tw-px-3 tw-border tw-border-slate-300 tw-rounded-lg focus:tw-ring-2 focus:tw-ring-purple-500 focus:tw-border-transparent tw-transition-all"
                                        <?php echo !$can_edit ? 'disabled' : ''; ?>
                                    />
                                    <small class="tw-text-slate-500 tw-text-xs tw-block">
                                        <?php echo _l('ramos_settings_default_route_start_time_description'); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar - Quick Stats & Info -->
                <div class="col-lg-4">
                    <!-- Status Card -->
                    <div class="panel_s tw-mb-4 tw-shadow-sm">
                        <div class="panel-heading tw-bg-gradient-to-r tw-from-slate-50 tw-to-slate-25 tw-border-b tw-border-slate-200">
                            <h4 class="tw-mb-0 tw-text-slate-900">
                                <i class="fa-solid fa-info-circle tw-mr-2 tw-text-slate-600"></i>
                                <span class="tw-font-semibold"><?php echo _l('status'); ?></span>
                            </h4>
                        </div>
                        <div class="panel-body tw-space-y-3">
                            <div class="tw-flex tw-items-center tw-justify-between tw-p-3 tw-bg-blue-50 tw-rounded-lg tw-border tw-border-blue-100">
                                <span class="tw-text-slate-700 tw-font-medium tw-text-sm">Automation</span>
                                <span class="tw-px-3 tw-py-1 tw-rounded-full tw-text-xs tw-font-semibold <?php echo $automation_enabled ? 'tw-bg-green-100 tw-text-green-800' : 'tw-bg-slate-100 tw-text-slate-700'; ?>">
                                    <?php echo $automation_enabled ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            <div class="tw-flex tw-items-center tw-justify-between tw-p-3 tw-bg-purple-50 tw-rounded-lg tw-border tw-border-purple-100">
                                <span class="tw-text-slate-700 tw-font-medium tw-text-sm">Routes</span>
                                <span class="tw-px-3 tw-py-1 tw-rounded-full tw-text-xs tw-font-semibold <?php echo $route_generation_auto ? 'tw-bg-green-100 tw-text-green-800' : 'tw-bg-slate-100 tw-text-slate-700'; ?>">
                                    <?php echo $route_generation_auto ? 'Auto' : 'Manual'; ?>
                                </span>
                            </div>
                            <div class="tw-flex tw-items-center tw-justify-between tw-p-3 tw-bg-emerald-50 tw-rounded-lg tw-border tw-border-emerald-100">
                                <span class="tw-text-slate-700 tw-font-medium tw-text-sm">Max Stops</span>
                                <span class="tw-px-3 tw-py-1 tw-rounded-full tw-text-xs tw-font-semibold tw-bg-slate-100 tw-text-slate-700">
                                    <?php echo (int)$default_max_stops; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Help Card -->
                    <div class="panel_s tw-shadow-sm">
                        <div class="panel-heading tw-bg-gradient-to-r tw-from-slate-50 tw-to-slate-25 tw-border-b tw-border-slate-200">
                            <h4 class="tw-mb-0 tw-text-slate-900">
                                <i class="fa-solid fa-lightbulb tw-mr-2 tw-text-amber-500"></i>
                                <span class="tw-font-semibold"><?php echo _l('help'); ?></span>
                            </h4>
                        </div>
                        <div class="panel-body">
                            <div class="tw-space-y-4 tw-text-sm tw-text-slate-600">
                                <div>
                                    <p class="tw-font-semibold tw-text-slate-700 tw-mb-1">
                                        <i class="fa-solid fa-clock tw-mr-2 tw-text-blue-600"></i>Schedule Hours
                                    </p>
                                    <p>Select hours when automation should run automatically.</p>
                                </div>
                                <hr class="tw-border-slate-200" />
                                <div>
                                    <p class="tw-font-semibold tw-text-slate-700 tw-mb-1">
                                        <i class="fa-solid fa-map tw-mr-2 tw-text-purple-600"></i>Route Generation
                                    </p>
                                    <p>Automatically create optimized delivery routes after processing orders.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button Section -->
            <?php if ($can_edit) : ?>
                <div class="row tw-mt-6">
                    <div class="col-lg-8">
                        <div class="tw-flex tw-items-center tw-gap-3 tw-p-4 tw-bg-slate-50 tw-rounded-lg tw-border tw-border-slate-200">
                            <button type="submit" class="btn btn-primary btn-lg tw-flex tw-items-center tw-gap-2">
                                <i class="fa-solid fa-check-circle"></i>
                                <span><?php echo _l('save'); ?></span>
                            </button>
                            <span id="save-status" class="tw-text-sm tw-font-medium tw-text-slate-500" style="display: none;"></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php init_tail(); ?>

<script>
    $(function() {
        var $automationToggle = $('#automation_enabled');
        var $scheduleContainer = $('#schedule-hours-container');
        var $scheduleTimeContainer = $('#schedule-time-picker');
        var $runDailyCheckbox = $('#schedule_run_daily');
        var $dateDropdown = $('#schedule_date');
        var $hourDropdown = $('#schedule_hour');
        var $minutesDropdown = $('#schedule_minutes');
        var $saveBtn = $('#automation-settings-form').find('button[type="submit"]');
        var $statusEl = $('#save-status');
        var formChanged = false;

        // Track form changes
        $('#automation-settings-form').on('change input', function() {
            formChanged = true;
            if ($saveBtn.length) {
                $saveBtn.addClass('tw-ring-2 tw-ring-offset-2 tw-ring-blue-400');
            }
        });

        // Toggle schedule visibility
        $automationToggle.on('change', function() {
            if (this.checked) {
                $scheduleContainer.slideDown(300);
                $scheduleTimeContainer.slideDown(400);
            } else {
                $scheduleContainer.slideUp(300);
                $scheduleTimeContainer.slideUp(300);
            }
        });

        // Handle "Run Daily" checkbox
        $runDailyCheckbox.on('change', function() {
            if (this.checked) {
                // Disable date dropdown when run daily is enabled
                $dateDropdown.prop('disabled', true).val('').css('opacity', '0.6');
                // Keep hour and minutes enabled
                $hourDropdown.prop('disabled', false).css('opacity', '1');
                $minutesDropdown.prop('disabled', false).css('opacity', '1');
            } else {
                // Enable all dropdowns
                $dateDropdown.prop('disabled', false).css('opacity', '1');
                $hourDropdown.prop('disabled', false).css('opacity', '1');
                $minutesDropdown.prop('disabled', false).css('opacity', '1');
            }
            updateScheduleSummary();
        });

        // Update summary on dropdown changes
        $dateDropdown.on('change', updateScheduleSummary);
        $hourDropdown.on('change', updateScheduleSummary);
        $minutesDropdown.on('change', updateScheduleSummary);
        $runDailyCheckbox.on('change', updateScheduleSummary);

        function updateScheduleSummary() {
            var runDaily = $runDailyCheckbox.is(':checked');
            var selectedDay = $dateDropdown.val();
            var selectedHour = $hourDropdown.val();
            var selectedMinutes = $minutesDropdown.val();

            var summary = '';

            if (!selectedHour) {
                summary = 'Select hour to schedule';
            } else {
                var hour = parseInt(selectedHour);
                var ampm = hour < 12 ? 'AM' : 'PM';
                var displayHour = hour === 0 ? 12 : (hour > 12 ? hour - 12 : hour);

                if (runDaily) {
                    summary = 'Every day at ' + 
                        str_pad(displayHour) + ':' + str_pad(selectedMinutes) + ' ' + ampm;
                } else if (selectedDay) {
                    var dayName = capitalizeDay(selectedDay);
                    summary = dayName + 's at ' + 
                        str_pad(displayHour) + ':' + str_pad(selectedMinutes) + ' ' + ampm;
                } else {
                    summary = 'Select a day or enable "Run Daily"';
                }
            }

            $('#schedule-summary').text(summary);
        }

        function str_pad(num) {
            return ('0' + num).slice(-2);
        }

        function capitalizeDay(day) {
            return day.charAt(0).toUpperCase() + day.slice(1);
        }

        // Handle form submission
        $('#automation-settings-form').on('submit', function(e) {
            e.preventDefault();
            console.log('Form submit handler triggered');

            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var originalBtnText = $submitBtn.html();

            // Validate
            if ($('#automation_enabled').is(':checked')) {
                var hour = $hourDropdown.val();
                var runDaily = $runDailyCheckbox.is(':checked');
                var selectedDay = $dateDropdown.val();

                if (!hour) {
                    alert_float('warning', 'Please select an hour for automation to run');
                    return;
                }

                if (!runDaily && !selectedDay) {
                    alert_float('warning', 'Please select a day or enable "Run Daily"');
                    return;
                }
            }

            // Disable button and show loading state
            $submitBtn.prop('disabled', true)
                      .html('<i class="fa-solid fa-spinner fa-spin tw-mr-2"></i><?php echo _l("saving"); ?>');

            console.log('Form data:', $form.serialize());
            
            $.ajax({
                url: $form.attr('action') || '',
                type: 'POST',
                data: $form.serialize(),
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    '<?php echo $this->security->get_csrf_token_name(); ?>': '<?php echo $this->security->get_csrf_hash(); ?>'
                },
                success: function(response) {
                    console.log('AJAX Success Response:', response);
                    if (response.success) {
                        alert_float('success', response.message || '<?php echo _l("settings_updated_successfully"); ?>');
                        
                        formChanged = false;
                        $submitBtn.removeClass('tw-ring-2 tw-ring-offset-2 tw-ring-blue-400');
                        
                        $statusEl.html('<i class="fa-solid fa-check-circle tw-mr-2 tw-text-green-600"></i><?php echo _l("saved"); ?>')
                                .show()
                                .delay(3000)
                                .fadeOut(300);
                    } else {
                        alert_float('danger', response.message || '<?php echo _l("problem_updating_settings"); ?>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    console.error('Status:', status);
                    console.error('XHR:', xhr);
                    alert_float('danger', '<?php echo _l("problem_updating_settings"); ?>: ' + error);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).html(originalBtnText);
                }
            });
        });

        // Add fade-in animation
        $('.panel_s').each(function() {
            $(this).addClass('tw-fade-in');
        });

        // Initialize summary on page load
        updateScheduleSummary();
    });
</script>

<style>
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .tw-fade-in {
        animation: fadeIn 0.3s ease-in-out;
    }

    /* Improved focus states for inputs */
    .form-control:focus {
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        border-color: #3b82f6;
    }

    /* Better checkbox styling */
    input[type="checkbox"]:focus {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
    }

    /* Smooth transitions for all interactive elements */
    button, input, select, textarea {
        transition: all 0.2s ease-in-out;
    }

    /* Hour selection button hover effect */
    label .peer:checked ~ div {
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.25);
    }
</style>
