<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$statusBadge = ramos_route_status_badge_class($route['status']);
$statusLabel = ramos_route_statuses()[$route['status']] ?? $route['status'];
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-col md:tw-flex-row tw-justify-between tw-items-start md:tw-items-center tw-gap-4 tw-mb-4">
                    <div>
                        <h4 class="tw-text-2xl tw-font-semibold tw-text-slate-900 tw-mb-1"><?php echo html_escape($title); ?></h4>
                        <p class="tw-text-slate-500 tw-mb-0"><?php echo _l('ramos_routes_view_subtitle', _d($route['route_date'])); ?></p>
                    </div>
                    <div>
                        <a href="<?php echo admin_url('ramos/routes?date=' . $route['route_date']); ?>" class="btn btn-default">
                            <i class="fa-regular fa-arrow-left-long tw-mr-1"></i><?php echo _l('back'); ?>
                        </a>
                    </div>
                </div>

                <div class="panel_s tw-mb-4">
                    <div class="panel-heading">
                        <h5 class="panel-title tw-text-base tw-font-semibold"><?php echo _l('ramos_routes_details_heading'); ?></h5>
                    </div>
                    <div class="panel-body tw-space-y-2">
                        <div><?php echo _l('ramos_routes_vehicle_label', html_escape($route['vehicle_label'] ?: _l('ramos_routes_default_vehicle', $route['id']))); ?></div>
                        <div><?php echo _l('ramos_routes_start_time_display', html_escape($route['start_time'] ?: '--')); ?></div>
                        <div><?php echo _l('ramos_routes_capacity_display', (int) $route['capacity']); ?></div>
                        <div><span class="label <?php echo $statusBadge; ?>"><?php echo html_escape($statusLabel); ?></span></div>
                        <?php if (!empty($route['notes'])) : ?>
                            <div class="tw-text-xs tw-text-slate-500 tw-whitespace-pre-line"><?php echo html_escape($route['notes']); ?></div>
                        <?php endif; ?>
                        <?php if (staff_can('edit', RAMOS_MODULE_NAME)) : ?>
                            <?php echo form_open(admin_url('ramos/routes/update_status/' . $route['id']), ['class' => 'tw-flex tw-gap-2 tw-items-center tw-mt-2']); ?>
                                <select name="status" class="form-control selectpicker">
                                    <?php foreach (ramos_route_statuses() as $key => $label) : ?>
                                        <option value="<?php echo html_escape($key); ?>" <?php echo $route['status'] === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm"><?php echo _l('ramos_routes_update_status_button'); ?></button>
                            <?php echo form_close(); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-heading">
                        <h5 class="panel-title tw-text-base tw-font-semibold"><?php echo _l('ramos_routes_stops_heading'); ?></h5>
                    </div>
                    <div class="panel-body">
                        <?php if (empty($stops)) : ?>
                            <p class="tw-text-sm tw-text-slate-500 tw-mb-0"><?php echo _l('ramos_routes_no_stops'); ?></p>
                        <?php else : ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th><?php echo _l('ramos_routes_stop_order'); ?></th>
                                            <th><?php echo _l('ramos_routes_stop_address'); ?></th>
                                            <th><?php echo _l('ramos_routes_stop_eta'); ?></th>
                                            <th><?php echo _l('ramos_routes_stop_status'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stops as $stop) : ?>
                                            <tr>
                                                <td><?php echo (int) $stop['stop_number']; ?></td>
                                                <td><?php echo html_escape($stop['order_number']); ?> &mdash; <?php echo html_escape($stop['customer_name']); ?></td>
                                                <td><?php echo html_escape($stop['delivery_address']); ?></td>
                                                <td><?php echo html_escape($stop['eta'] ? _dt($stop['eta']) : '--'); ?></td>
                                                <td><?php echo html_escape(ucfirst(str_replace('_', ' ', $stop['status']))); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
