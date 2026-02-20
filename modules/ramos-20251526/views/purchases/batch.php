<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$statusBadge = ramos_purchase_status_badge_class($batch['status']);
$statusLabel = ramos_purchase_statuses()[$batch['status']] ?? $batch['status'];
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-col md:tw-flex-row tw-justify-between tw-items-start md:tw-items-center tw-gap-4 tw-mb-4">
                    <div>
                        <h4 class="tw-text-2xl tw-font-semibold tw-text-slate-900 tw-mb-1">
                            <?php echo html_escape($title); ?>
                        </h4>
                        <div class="tw-text-sm tw-text-slate-500 tw-space-y-1">
                            <div>
                                <strong><?php echo _l('ramos_purchases_batch_supplier'); ?>:</strong>
                                <?php echo html_escape($batch['supplier_name'] ?: _l('ramos_purchases_unassigned_supplier')); ?>
                            </div>
                            <div>
                                <strong><?php echo _l('ramos_purchases_batch_created_at'); ?>:</strong>
                                <?php echo _dt($batch['created_at']); ?>
                            </div>
                            <div>
                                <span class="label <?php echo $statusBadge; ?>"><?php echo html_escape($statusLabel); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="tw-flex tw-gap-2">
                        <a href="<?php echo admin_url('ramos/purchases'); ?>" class="btn btn-default">
                            <i class="fa-regular fa-arrow-left-long tw-mr-1"></i><?php echo _l('back'); ?>
                        </a>
                    </div>
                </div>

                <div class="row tw-gap-4">
                    <div class="col-md-8">
                        <div class="panel_s">
                            <div class="panel-heading">
                                <h5 class="panel-title tw-text-base tw-font-semibold">
                                    <?php echo _l('ramos_purchases_batch_lines'); ?>
                                </h5>
                            </div>
                            <div class="panel-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th><?php echo _l('ramos_purchases_table_item'); ?></th>
                                                <th><?php echo _l('ramos_purchases_table_required'); ?></th>
                                                <th><?php echo _l('ramos_purchases_table_received'); ?></th>
                                                <th><?php echo _l('ramos_purchases_table_remaining'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($items as $item) :
                                                $received  = (float) $item['received_qty'];
                                                $required  = (float) $item['requested_qty'];
                                                $remaining = max($required - $received, 0);
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo html_escape($item['item_name']); ?></strong>
                                                        <?php if ($item['unit']) : ?>
                                                            <div class="tw-text-2xs tw-text-slate-400"><?php echo html_escape($item['unit']); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo app_format_number($required); ?></td>
                                                    <td><?php echo app_format_number($received); ?></td>
                                                    <td><?php echo app_format_number($remaining); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 tw-space-y-4">
                        <?php if (in_array($batch['status'], ['draft'], true) && staff_can('edit', RAMOS_MODULE_NAME)) : ?>
                            <div class="panel_s">
                                <div class="panel-heading">
                                    <h5 class="panel-title tw-text-base tw-font-semibold">
                                        <?php echo _l('ramos_purchases_send_panel_title'); ?>
                                    </h5>
                                </div>
                                <div class="panel-body">
                                    <?php echo form_open(admin_url('ramos/purchases/send/' . $batch['id'])); ?>
                                        <?php echo render_textarea('notes', _l('ramos_purchases_send_notes'), $batch['notes'], ['rows' => 3]); ?>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fa-regular fa-paper-plane tw-mr-1"></i><?php echo _l('ramos_purchases_mark_sent_button'); ?>
                                        </button>
                                    <?php echo form_close(); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (in_array($batch['status'], ['draft', 'sent', 'partial'], true) && staff_can('edit', RAMOS_MODULE_NAME)) : ?>
                            <div class="panel_s">
                                <div class="panel-heading">
                                    <h5 class="panel-title tw-text-base tw-font-semibold">
                                        <?php echo _l('ramos_purchases_receive_panel_title'); ?>
                                    </h5>
                                </div>
                                <div class="panel-body">
                                    <?php echo form_open(admin_url('ramos/purchases/receive/' . $batch['id'])); ?>
                                        <div class="tw-text-xs tw-text-slate-500 tw-mb-2">
                                            <?php echo _l('ramos_purchases_receive_hint'); ?>
                                        </div>
                                        <?php $hasReceivable = false; ?>
                                        <?php foreach ($items as $index => $item) :
                                            $remaining = max((float) $item['requested_qty'] - (float) $item['received_qty'], 0);
                                            if ($remaining <= 0) {
                                                continue;
                                            }
                                            $hasReceivable = true;
                                            ?>
                                            <div class="form-group tw-mb-3">
                                                <label class="control-label">
                                                    <?php echo html_escape($item['item_name']); ?>
                                                    <span class="tw-text-2xs tw-text-slate-500"><?php echo _l('ramos_purchases_receive_remaining', app_format_number($remaining)); ?></span>
                                                </label>
                                                <input type="hidden" name="item_id[]" value="<?php echo (int) $item['id']; ?>">
                                                <input type="number" name="receive_qty[]" class="form-control" step="0.01" max="<?php echo $remaining; ?>" placeholder="0">
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($hasReceivable) : ?>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fa-regular fa-box-open tw-mr-1"></i><?php echo _l('ramos_purchases_receive_submit'); ?>
                                            </button>
                                        <?php else : ?>
                                            <p class="tw-text-xs tw-text-slate-500 tw-mb-0"><?php echo _l('ramos_purchases_available_received'); ?></p>
                                        <?php endif; ?>
                                    <?php echo form_close(); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($batch['notes'])) : ?>
                            <div class="panel_s">
                                <div class="panel-heading">
                                    <h5 class="panel-title tw-text-base tw-font-semibold">
                                        <?php echo _l('ramos_purchases_notes_heading'); ?>
                                    </h5>
                                </div>
                                <div class="panel-body">
                                    <p class="tw-text-sm tw-text-slate-600 tw-mb-0"><?php echo nl2br(html_escape($batch['notes'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
