<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$module      = $module ?? [];
$orders      = $orders ?? [];
$statusMap   = $statusLabels ?? [];
$canEdit     = $can_edit ?? true;
?>
<div class="col-md-6 ramos-console-module" data-module-id="<?php echo (int) ($module['id'] ?? 0); ?>">
    <div class="panel_s tw-mb-4">
        <div class="panel-heading">
            <h5 class="panel-title tw-text-base tw-font-semibold">
                <?php echo html_escape($module['display_name'] ?? ''); ?>
                <?php if (isset($module['is_active']) && !(int) $module['is_active']) : ?>
                    <span class="label label-default"><?php echo _l('ramos_picking_inactive'); ?></span>
                <?php endif; ?>
            </h5>
        </div>
        <div class="panel-body tw-space-y-3">
            <?php if (empty($orders)) : ?>
                <p class="tw-text-xs tw-text-slate-500 tw-mb-0"><?php echo _l('ramos_picking_console_no_orders'); ?></p>
            <?php else : ?>
                <?php foreach ($orders as $order) :
                    $label = $statusMap[$order['status']] ?? 'label-default';
                    ?>
                    <div class="tw-border tw-border-slate-200 tw-rounded tw-p-3 ramos-console-order" data-order-id="<?php echo (int) $order['order_id']; ?>">
                        <div class="tw-flex tw-justify-between tw-items-start tw-mb-2">
                            <div class="tw-flex tw-items-start tw-gap-2">
                                <?php if (!empty($order['route_priority'])) : ?>
                                    <div class="tw-flex-shrink-0" style="width: 32px; height: 32px; border-radius: 50%; background-color: #3b82f6; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px;" title="<?php echo _l('ramos_picking_route_stop_label'); ?>">
                                        <?php echo (int) $order['route_priority']; ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h6 class="tw-text-sm tw-font-semibold tw-mb-1">
                                        <?php echo sprintf(_l('ramos_picking_order_label'), html_escape($order['order_number'])); ?>
                                        <span class="label <?php echo $label; ?> tw-ml-2"><?php echo _l('ramos_picking_status_' . $order['status']); ?></span>
                                    </h6>
                                    <div class="tw-text-xs tw-text-slate-500">
                                        <?php echo html_escape($order['customer_name']); ?>
                                        <div><?php echo html_escape($order['delivery_address']); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="tw-text-xs tw-text-slate-500">
                                <?php echo _l('ramos_picking_progress_label', $order['progress']); ?>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-condensed">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('ramos_picking_item_column'); ?></th>
                                        <th class="tw-text-right"><?php echo _l('ramos_picking_required_column'); ?></th>
                                        <th class="tw-text-right"><?php echo _l('ramos_picking_picked_column'); ?></th>
                                        <th class="tw-text-right"><?php echo _l('ramos_picking_weight_column'); ?></th>
                                        <th class="tw-text-right"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order['items'] as $item) : ?>
                                        <tr>
                                            <td>
                                                <?php echo html_escape($item['item_name']); ?>
                                                <?php if (!empty($item['ripeness'])) : ?>
                                                    <span class="label label-info tw-ml-1" style="font-size:10px;"><?php echo html_escape($item['ripeness']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($item['unit'])) : ?>
                                                    <div class="tw-text-[10px] tw-text-slate-400"><?php echo html_escape($item['unit']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="tw-text-right"><?php echo app_format_number($item['required_qty']); ?></td>
                                            <td class="tw-text-right"><?php echo app_format_number($item['picked_qty']); ?></td>
                                            <td class="tw-text-right"><?php echo app_format_number($item['weight']); ?></td>
                                            <td class="tw-text-right" style="width: 140px;">
                                                <?php if ($canEdit) : ?>
                                                    <?php echo form_open(admin_url('ramos/picking/update_item/' . $item['pick_id']), ['class' => 'form-inline tw-flex tw-gap-2 tw-justify-end']); ?>
                                                        <input type="number" step="0.01" name="picked_qty" class="form-control input-sm" value="<?php echo html_escape($item['picked_qty']); ?>" placeholder="<?php echo _l('ramos_picking_picked_column'); ?>">
                                                        <input type="number" step="0.01" name="weight" class="form-control input-sm" value="<?php echo html_escape($item['weight']); ?>" placeholder="<?php echo _l('ramos_picking_weight_column'); ?>">
                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                            <i class="fa-regular fa-floppy-disk"></i>
                                                        </button>
                                                    <?php echo form_close(); ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
