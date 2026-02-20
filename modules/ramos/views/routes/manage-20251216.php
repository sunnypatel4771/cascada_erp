<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-col md:tw-flex-row tw-justify-between tw-items-start md:tw-items-center tw-gap-4 tw-mb-4">
                    <div>
                        <h4 class="tw-text-2xl tw-font-semibold tw-text-slate-900 tw-mb-1"><?php echo _l('ramos_routes_title'); ?></h4>
                        <p class="tw-text-slate-500 tw-mb-0"><?php echo _l('ramos_routes_subtitle'); ?></p>
                    </div>
                    <div class="tw-flex tw-gap-2">
                        <a href="<?php echo admin_url('ramos/routes/board?date=' . $date); ?>" class="btn btn-primary">
                            <i class="fa-solid fa-route tw-mr-1"></i><?php echo _l('ramos_routes_board_button'); ?>
                        </a>
                        <a href="<?php echo admin_url('ramos/picking/console'); ?>" class="btn btn-default">
                            <i class="fa-regular fa-clipboard-list tw-mr-1"></i><?php echo _l('ramos_picking_console_menu_label'); ?>
                        </a>
                    </div>
                </div>

                <?php if (!empty($can_generate)) : ?>
                    <div class="panel_s tw-mb-4">
                        <div class="panel-heading">
                            <h5 class="panel-title tw-text-base tw-font-semibold"><?php echo _l('ramos_routes_generate_heading'); ?></h5>
                        </div>
                        <div class="panel-body">
                            <?php echo form_open(admin_url('ramos/routes'), ['class' => 'tw-flex tw-flex-col md:tw-flex-row tw-gap-4']); ?>
                                <input type="hidden" name="generate" value="1">
                                <div class="form-group">
                                    <label for="route_date" class="control-label"><?php echo _l('ramos_routes_date_label'); ?></label>
                                    <input type="date" name="route_date" id="route_date" class="form-control" value="<?php echo html_escape($form_defaults['route_date'] ?? $date); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="start_time" class="control-label"><?php echo _l('ramos_routes_start_time_label'); ?></label>
                                    <input type="time" name="start_time" id="start_time" class="form-control" value="<?php echo html_escape($form_defaults['start_time'] ?? '08:00'); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="max_stops" class="control-label"><?php echo _l('ramos_routes_max_stops_label'); ?></label>
                                    <input type="number" min="1" name="max_stops" id="max_stops" class="form-control" value="<?php echo (int) ($form_defaults['max_stops'] ?? 10); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="route_prefix" class="control-label"><?php echo _l('ramos_routes_prefix_label'); ?></label>
                                    <input type="text" name="route_prefix" id="route_prefix" class="form-control" value="<?php echo html_escape($form_defaults['route_prefix'] ?? 'Route'); ?>">
                                </div>
                                <div class="form-group tw-flex tw-items-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa-regular fa-magic-wand-sparkles tw-mr-1"></i><?php echo _l('ramos_routes_generate_button'); ?>
                                    </button>
                                </div>
                            <?php echo form_close(); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="panel_s">
                    <div class="panel-heading">
                        <h5 class="panel-title tw-text-base tw-font-semibold"><?php echo _l('ramos_routes_list_heading', _d($date)); ?></h5>
                    </div>
                    <div class="panel-body">
                        <?php echo form_open(admin_url('ramos/routes'), ['method' => 'get', 'class' => 'tw-flex tw-gap-2 tw-items-center tw-mb-3']); ?>
                            <input type="date" name="date" value="<?php echo html_escape($date); ?>" class="form-control" style="max-width: 200px;">
                            <button type="submit" class="btn btn-default btn-sm"><?php echo _l('submit'); ?></button>
                        <?php echo form_close(); ?>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="ramos-routes-table">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('ramos_routes_table_vehicle'); ?></th>
                                        <th><?php echo _l('ramos_routes_table_time'); ?></th>
                                        <th><?php echo _l('ramos_routes_table_capacity'); ?></th>
                                        <th><?php echo _l('ramos_routes_table_stops'); ?></th>
                                        <th><?php echo _l('ramos_routes_table_progress'); ?></th>
                                        <th><?php echo _l('ramos_routes_table_status'); ?></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="ramos-routes-table-body">
                                    <?php $this->load->view('ramos/routes/partials/table_rows', ['routes' => $routes]); ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
(function($) {
    "use strict";

    var refreshInterval = 20000;
    var currentDate = <?php echo json_encode($date); ?>;
    var timerId = null;

    function schedule() {
        timerId = setTimeout(fetchData, refreshInterval);
    }

    function fetchData() {
        $.get(admin_url + 'ramos/routes/list_refresh', { date: currentDate })
            .done(function(response) {
                if (response && response.success && typeof response.html === 'string') {
                    $('#ramos-routes-table-body').html(response.html);
                }
            })
            .always(function() {
                schedule();
            });
    }

    schedule();
})(jQuery);
</script>
