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

                            <!-- Schedule Settings -->
                            <div id="schedule-time-picker" style="<?php echo !$automation_enabled ? 'display: none;' : ''; ?>" class="tw-mt-4 tw-transition-all tw-duration-300">
                                <div class="tw-bg-blue-50 tw-border tw-border-blue-200 tw-rounded-lg tw-p-5 tw-space-y-5">
                                    <label class="tw-text-slate-900 tw-font-semibold tw-block">
                                        <i class="fa-solid fa-calendar-clock tw-mr-2 tw-text-blue-600"></i><?php echo _l('ramos_settings_schedule_settings_heading'); ?>
                                    </label>

                                    <!-- Frequency -->
                                    <div class="tw-p-4 tw-bg-white tw-rounded-lg tw-border tw-border-blue-100">
                                        <label class="tw-text-slate-700 tw-font-semibold tw-block tw-text-sm tw-mb-3">
                                            <i class="fa-solid fa-repeat tw-mr-2 tw-text-blue-600"></i><?php echo _l('ramos_settings_frequency'); ?>
                                        </label>
                                        <div class="tw-space-y-2">
                                            <?php
                                            $modes = [
                                                'daily_once'  => _l('ramos_settings_schedule_mode_daily_once'),
                                                'weekly_once' => _l('ramos_settings_schedule_mode_weekly_once'),
                                                'multi_daily' => _l('ramos_settings_schedule_mode_multi_daily'),
                                            ];
                                            foreach ($modes as $modeKey => $modeLabel) :
                                            ?>
                                            <label class="tw-flex tw-items-center tw-gap-3 tw-cursor-pointer <?php echo !$can_edit ? 'tw-opacity-60' : ''; ?>">
                                                <input
                                                    type="radio"
                                                    name="schedule_mode"
                                                    value="<?php echo $modeKey; ?>"
                                                    class="schedule-mode-radio tw-accent-blue-600"
                                                    <?php echo $schedule_mode === $modeKey ? 'checked' : ''; ?>
                                                    <?php echo !$can_edit ? 'disabled' : ''; ?>
                                                />
                                                <span class="tw-text-slate-800 tw-text-sm"><?php echo html_escape($modeLabel); ?></span>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Weekday (weekly_once only) -->
                                    <div id="schedule-weekday-row" class="tw-p-4 tw-bg-white tw-rounded-lg tw-border tw-border-blue-100" style="<?php echo $schedule_mode !== 'weekly_once' ? 'display:none;' : ''; ?>">
                                        <label for="schedule_date" class="tw-text-slate-700 tw-font-semibold tw-block tw-text-sm tw-mb-2">
                                            <i class="fa-solid fa-calendar-day tw-mr-2 tw-text-blue-600"></i><?php echo _l('ramos_settings_run_on_day'); ?>
                                        </label>
                                        <select name="schedule_date" id="schedule_date"
                                                class="form-control"
                                                <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                            <option value=""><?php echo _l('ramos_settings_select_day'); ?></option>
                                            <?php
                                            $days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
                                            foreach ($days as $d) :
                                            ?>
                                            <option value="<?php echo $d; ?>" <?php echo $schedule_date === $d ? 'selected' : ''; ?>>
                                                <?php echo ucfirst($d); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Run at time (daily_once / weekly_once) -->
                                    <div id="schedule-run-at-row" class="tw-p-4 tw-bg-white tw-rounded-lg tw-border tw-border-blue-100" style="<?php echo $schedule_mode === 'multi_daily' ? 'display:none;' : ''; ?>">
                                        <label class="tw-text-slate-700 tw-font-semibold tw-block tw-text-sm tw-mb-3">
                                            <i class="fa-solid fa-clock tw-mr-2 tw-text-green-600"></i><?php echo _l('ramos_settings_run_at'); ?>
                                        </label>
                                        <div class="tw-grid tw-grid-cols-2 tw-gap-4">
                                            <div>
                                                <label class="tw-text-slate-500 tw-text-xs tw-block tw-mb-1"><?php echo _l('ramos_settings_hour'); ?></label>
                                                <select name="schedule_hour" id="schedule_hour"
                                                        class="form-control"
                                                        <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                                    <?php for ($h = 0; $h < 24; $h++) : ?>
                                                    <option value="<?php echo $h; ?>" <?php echo $schedule_hour === $h ? 'selected' : ''; ?>>
                                                        <?php echo str_pad($h, 2, '0', STR_PAD_LEFT); ?>:00
                                                    </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="tw-text-slate-500 tw-text-xs tw-block tw-mb-1"><?php echo _l('ramos_settings_minutes'); ?></label>
                                                <select name="schedule_minutes" id="schedule_minutes"
                                                        class="form-control"
                                                        <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                                    <?php foreach ([0,5,10,15,20,25,30,35,40,45,50,55] as $m) : ?>
                                                    <option value="<?php echo $m; ?>" <?php echo $schedule_minutes === $m ? 'selected' : ''; ?>>
                                                        :<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Multi-daily hours (multi_daily only) -->
                                    <div id="schedule-multi-hours-row" class="tw-p-4 tw-bg-white tw-rounded-lg tw-border tw-border-blue-100" style="<?php echo $schedule_mode !== 'multi_daily' ? 'display:none;' : ''; ?>">
                                        <label class="tw-text-slate-700 tw-font-semibold tw-block tw-text-sm tw-mb-2">
                                            <i class="fa-solid fa-list-check tw-mr-2 tw-text-blue-600"></i><?php echo _l('ramos_settings_multi_hours_label'); ?>
                                        </label>
                                        <div class="tw-grid tw-grid-cols-4 sm:tw-grid-cols-6 tw-gap-2">
                                            <?php for ($h = 0; $h < 24; $h++) : ?>
                                            <label class="tw-flex tw-items-center tw-gap-1 tw-cursor-pointer tw-text-sm <?php echo !$can_edit ? 'tw-opacity-60' : ''; ?>">
                                                <input
                                                    type="checkbox"
                                                    name="schedule_multi_hours[]"
                                                    value="<?php echo $h; ?>"
                                                    class="schedule-multi-hour tw-accent-blue-600"
                                                    <?php echo in_array($h, $schedule_hours) ? 'checked' : ''; ?>
                                                    <?php echo !$can_edit ? 'disabled' : ''; ?>
                                                />
                                                <?php echo str_pad($h, 2, '0', STR_PAD_LEFT); ?>:00
                                            </label>
                                            <?php endfor; ?>
                                        </div>
                                        <small class="tw-text-slate-500 tw-block tw-mt-2">
                                            <?php echo _l('ramos_settings_multi_hours_hint'); ?>
                                        </small>
                                        <!-- Shared minute for multi_daily -->
                                        <div class="tw-mt-3">
                                            <label class="tw-text-slate-500 tw-text-xs tw-block tw-mb-1"><?php echo _l('ramos_settings_at_minute'); ?></label>
                                            <select name="schedule_multi_minutes" id="schedule_minutes_multi"
                                                    class="form-control tw-w-auto"
                                                    <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                                <?php foreach ([0,5,10,15,20,25,30,35,40,45,50,55] as $m) : ?>
                                                <option value="<?php echo $m; ?>" <?php echo $schedule_minutes === $m ? 'selected' : ''; ?>>
                                                    :<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Next run summary -->
                                    <div class="tw-p-3 tw-bg-white tw-rounded-lg tw-border tw-border-blue-100">
                                        <p class="tw-text-sm tw-text-slate-700 tw-mb-0">
                                            <i class="fa-solid fa-circle-info tw-mr-2 tw-text-blue-600"></i>
                                            <strong><?php echo _l('ramos_settings_next_run_label'); ?>:</strong>
                                            <span id="schedule-summary" class="tw-font-mono tw-text-blue-700 tw-ml-1">—</span>
                                        </p>
                                        <p class="tw-text-xs tw-text-slate-400 tw-mb-0 tw-mt-1">
                                            <?php echo _l('ramos_settings_timezone_note'); ?>
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

                <!-- Portal invoice merge card -->
                <div class="col-lg-8 tw-mt-6">
                    <div class="panel_s tw-shadow-sm hover:tw-shadow-md tw-transition-shadow">
                        <div class="panel-heading tw-bg-gradient-to-r tw-from-indigo-50 tw-to-indigo-25 tw-border-b tw-border-indigo-100">
                            <h4 class="tw-mb-0 tw-text-slate-900">
                                <i class="fa-solid fa-file-invoice tw-mr-2 tw-text-indigo-600"></i>
                                <span class="tw-font-semibold">Portal invoices</span>
                                <span class="tw-text-xs tw-font-normal tw-text-slate-500 tw-ml-2">
                                    (<?php echo $portal_invoice_auto_merge_enabled ? '<span class="tw-text-green-600"><i class="fa-solid fa-check-circle tw-mr-1"></i>Auto-merge ON</span>' : '<span class="tw-text-slate-400"><i class="fa-solid fa-circle tw-mr-1"></i>Auto-merge OFF</span>'; ?>)
                                </span>
                            </h4>
                        </div>
                        <div class="panel-body tw-space-y-5">
                            <div class="tw-relative tw-p-4 tw-bg-slate-50 tw-rounded-lg tw-border tw-border-slate-200 <?php echo $can_edit ? 'hover:tw-bg-slate-75 tw-cursor-pointer tw-transition-colors' : ''; ?>">
                                <label class="tw-flex tw-items-center tw-gap-4 tw-cursor-pointer <?php echo !$can_edit ? 'tw-opacity-75' : ''; ?>">
                                    <div class="tw-relative tw-flex tw-items-center">
                                        <input
                                            type="checkbox"
                                            name="portal_invoice_auto_merge_enabled"
                                            id="portal_invoice_auto_merge_enabled"
                                            <?php echo $portal_invoice_auto_merge_enabled ? 'checked' : ''; ?>
                                            <?php echo !$can_edit ? 'disabled' : ''; ?>
                                            class="tw-rounded tw-w-5 tw-h-5 tw-cursor-pointer tw-accent-indigo-600"
                                        />
                                    </div>
                                    <div>
                                        <span class="tw-text-slate-900 tw-font-semibold tw-block">Auto-merge invoices for same customer</span>
                                        <small class="tw-text-slate-500 tw-block tw-mt-1">
                                            When enabled, each new portal order invoice will merge other eligible invoices for the same customer (same currency).
                                        </small>
                                    </div>
                                </label>
                            </div>

                            <div class="tw-relative tw-p-4 tw-bg-white tw-rounded-lg tw-border tw-border-indigo-100">
                                <label class="tw-flex tw-items-center tw-gap-3 tw-cursor-pointer <?php echo !$can_edit ? 'tw-opacity-60' : ''; ?>">
                                    <input
                                        type="checkbox"
                                        name="portal_invoice_auto_merge_cancel"
                                        id="portal_invoice_auto_merge_cancel"
                                        <?php echo $portal_invoice_auto_merge_cancel ? 'checked' : ''; ?>
                                        <?php echo !$can_edit ? 'disabled' : ''; ?>
                                        class="tw-rounded tw-w-4 tw-h-4 tw-cursor-pointer tw-accent-indigo-600"
                                    />
                                    <span class="tw-text-slate-800 tw-text-sm">
                                        Cancel merged invoices (recommended). If unchecked, merged invoices will be deleted.
                                    </span>
                                </label>
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
                                <span class="tw-text-slate-700 tw-font-medium tw-text-sm"><?php echo _l('ramos_settings_frequency'); ?></span>
                                <span class="tw-px-3 tw-py-1 tw-rounded-full tw-text-xs tw-font-semibold tw-bg-slate-100 tw-text-slate-700">
                                    <?php echo html_escape(_l('ramos_settings_schedule_mode_' . $schedule_mode)); ?>
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
                                        <i class="fa-solid fa-repeat tw-mr-2 tw-text-blue-600"></i><?php echo _l('ramos_settings_frequency'); ?>
                                    </p>
                                    <p><?php echo _l('ramos_settings_frequency_help'); ?></p>
                                </div>
                                <hr class="tw-border-slate-200" />
                                <div>
                                    <p class="tw-font-semibold tw-text-slate-700 tw-mb-1">
                                        <i class="fa-solid fa-clock tw-mr-2 tw-text-green-600"></i><?php echo _l('ramos_settings_run_at'); ?>
                                    </p>
                                    <p><?php echo _l('ramos_settings_run_at_help'); ?></p>
                                </div>
                                <hr class="tw-border-slate-200" />
                                <div>
                                    <p class="tw-font-semibold tw-text-slate-700 tw-mb-1">
                                        <i class="fa-solid fa-map tw-mr-2 tw-text-purple-600"></i><?php echo _l('ramos_settings_route_generation'); ?>
                                    </p>
                                    <p><?php echo _l('ramos_settings_route_generation_help'); ?></p>
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
        var $automationToggle  = $('#automation_enabled');
        var $scheduleContainer = $('#schedule-time-picker');
        var $saveBtn           = $('#automation-settings-form').find('button[type="submit"]');
        var $statusEl          = $('#save-status');

        // Track unsaved changes
        $('#automation-settings-form').on('change input', function() {
            $saveBtn.addClass('tw-ring-2 tw-ring-offset-2 tw-ring-blue-400');
        });

        // Show/hide schedule block when automation is toggled
        $automationToggle.on('change', function() {
            $(this).is(':checked') ? $scheduleContainer.slideDown(350) : $scheduleContainer.slideUp(300);
        });

        // Show/hide mode-specific sub-rows
        function applyModeVisibility(mode) {
            if (mode === 'weekly_once') {
                $('#schedule-weekday-row').slideDown(200);
            } else {
                $('#schedule-weekday-row').slideUp(200);
            }

            if (mode === 'multi_daily') {
                $('#schedule-run-at-row').slideUp(200);
                $('#schedule-multi-hours-row').slideDown(200);
            } else {
                $('#schedule-run-at-row').slideDown(200);
                $('#schedule-multi-hours-row').slideUp(200);
            }

            updateScheduleSummary();
        }

        $('input[name="schedule_mode"]').on('change', function() {
            applyModeVisibility(this.value);
        });

        // Update the Next Run summary
        function pad(n) { return ('0' + n).slice(-2); }

        function fmt24(h, m) {
            return pad(h) + ':' + pad(m);
        }

        function updateScheduleSummary() {
            var mode = $('input[name="schedule_mode"]:checked').val();
            var $summary = $('#schedule-summary');

            if (mode === 'multi_daily') {
                var hours = [];
                $('.schedule-multi-hour:checked').each(function() {
                    hours.push(parseInt(this.value));
                });
                hours.sort(function(a,b){ return a-b; });

                if (hours.length === 0) {
                    $summary.text('<?php echo _l("ramos_settings_summary_select_hours"); ?>');
                    return;
                }

                var min = parseInt($('#schedule_minutes_multi').val()) || 0;
                var times = hours.map(function(h){ return fmt24(h, min); });
                $summary.text('<?php echo _l("ramos_settings_summary_every_day"); ?>: ' + times.join(', '));
                return;
            }

            var h   = parseInt($('#schedule_hour').val());
            var m   = parseInt($('#schedule_minutes').val());
            var day = $('#schedule_date').val();

            if (isNaN(h)) {
                $summary.text('—');
                return;
            }

            var timeStr = fmt24(h, m);

            if (mode === 'daily_once') {
                $summary.text('<?php echo _l("ramos_settings_summary_every_day"); ?> ' + timeStr);
            } else if (mode === 'weekly_once') {
                if (!day) {
                    $summary.text('<?php echo _l("ramos_settings_summary_select_day"); ?>');
                    return;
                }
                var dayLabel = day.charAt(0).toUpperCase() + day.slice(1);
                $summary.text('<?php echo _l("ramos_settings_summary_every"); ?> ' + dayLabel + ' ' + timeStr);
            }
        }

        // Re-run summary when any relevant field changes
        $('#schedule_hour, #schedule_minutes, #schedule_minutes_multi, #schedule_date').on('change', updateScheduleSummary);
        $('.schedule-multi-hour').on('change', updateScheduleSummary);

        // Form submission
        $('#automation-settings-form').on('submit', function(e) {
            e.preventDefault();

            var $form      = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var origHtml   = $submitBtn.html();

            // Client-side validation when automation is enabled
            if ($automationToggle.is(':checked')) {
                var mode = $('input[name="schedule_mode"]:checked').val();

                if (mode === 'weekly_once' && !$('#schedule_date').val()) {
                    alert_float('warning', '<?php echo _l("ramos_settings_select_day_required"); ?>');
                    return;
                }

                if (mode === 'multi_daily') {
                    if ($('.schedule-multi-hour:checked').length === 0) {
                        alert_float('warning', '<?php echo _l("ramos_settings_select_hours_required"); ?>');
                        return;
                    }
                }
            }

            $submitBtn.prop('disabled', true)
                .html('<i class="fa-solid fa-spinner fa-spin tw-mr-2"></i><?php echo _l("saving"); ?>');

            $.ajax({
                url:      $form.attr('action') || '',
                type:     'POST',
                data:     $form.serialize(),
                dataType: 'json',
                headers:  {
                    'X-Requested-With': 'XMLHttpRequest',
                    '<?php echo $this->security->get_csrf_token_name(); ?>': '<?php echo $this->security->get_csrf_hash(); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert_float('success', response.message || '<?php echo _l("settings_updated_successfully"); ?>');
                        $saveBtn.removeClass('tw-ring-2 tw-ring-offset-2 tw-ring-blue-400');
                        $statusEl.html('<i class="fa-solid fa-check-circle tw-mr-2 tw-text-green-600"></i><?php echo _l("saved"); ?>')
                                 .show().delay(3000).fadeOut(300);
                    } else {
                        alert_float('danger', response.message || '<?php echo _l("problem_updating_settings"); ?>');
                    }
                },
                error: function(xhr, status, error) {
                    alert_float('danger', '<?php echo _l("problem_updating_settings"); ?>: ' + error);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).html(origHtml);
                }
            });
        });

        // Fade-in panels
        $('.panel_s').addClass('tw-fade-in');

        // Initialise on page load
        applyModeVisibility($('input[name="schedule_mode"]:checked').val() || 'daily_once');
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
