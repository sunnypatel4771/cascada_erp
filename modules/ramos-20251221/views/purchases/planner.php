<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
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

                <div class="row tw-gap-4">
                    <div class="col-md-8">
                        <?php if (empty($deficit_groups)) : ?>
                            <div class="alert alert-success tw-text-sm">
                                <?php echo _l('ramos_purchases_no_deficits'); ?>
                            </div>
                        <?php else : ?>
                            <?php foreach ($deficit_groups as $supplierId => $group) :
                                $supplier = $group['supplier'] ?? null;
                                $supplierName = $supplier ? $supplier['supplier_name'] : _l('ramos_purchases_unassigned_supplier');
                                $items = isset($group['items']) ? $group['items'] : [];
                                if (empty($items)) {
                                    continue;
                                }
                                ?>
                                <div class="panel_s tw-mb-4">
                                    <div class="panel-heading">
                                        <h5 class="panel-title tw-text-base tw-font-semibold">
                                            <?php echo html_escape($supplierName); ?>
                                        </h5>
                                    </div>
                                    <div class="panel-body">
                                        <?php echo form_open(admin_url('ramos/purchases/approve')); ?>
                                        <input type="hidden" name="supplier_id" value="<?php echo html_escape($supplierId); ?>">
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover">
                                                <thead>
                                                    <tr>
                                                        <th><?php echo _l('ramos_purchases_table_item'); ?></th>
                                                        <th><?php echo _l('ramos_purchases_table_required'); ?></th>
                                                        <th><?php echo _l('ramos_purchases_table_on_hand'); ?></th>
                                                        <th><?php echo _l('ramos_purchases_table_safety'); ?></th>
                                                        <th><?php echo _l('ramos_purchases_table_status'); ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($items as $index => $item) :
                                                        $statusClass = ramos_inventory_status_badge_class($item['status']);
                                                        $statusLabel = ramos_inventory_status_label($item['status']);
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo html_escape($item['item_name']); ?></strong>
                                                                <div class="tw-text-2xs tw-text-slate-400"><?php echo html_escape($item['unit']); ?></div>
                                                            </td>
                                                            <td><?php echo app_format_number($item['required_qty']); ?></td>
                                                            <td><?php echo app_format_number($item['current_stock']); ?></td>
                                                            <td><?php echo app_format_number($item['safety_stock']); ?></td>
                                                            <td><span class="label <?php echo $statusClass; ?>"><?php echo html_escape($statusLabel); ?></span></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php foreach ($items as $index => $item) : ?>
                                            <input type="hidden" name="items[<?php echo $index; ?>][inventory_item_id]" value="<?php echo (int) $item['inventory_item_id']; ?>">
                                            <input type="hidden" name="items[<?php echo $index; ?>][requested_qty]" value="<?php echo (float) $item['required_qty']; ?>">
                                            <input type="hidden" name="items[<?php echo $index; ?>][current_stock]" value="<?php echo (float) $item['current_stock']; ?>">
                                            <input type="hidden" name="items[<?php echo $index; ?>][safety_stock]" value="<?php echo (float) $item['safety_stock']; ?>">
                                        <?php endforeach; ?>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fa-regular fa-paper-plane tw-mr-1"></i><?php echo _l('ramos_purchases_approve_button'); ?>
                                        </button>
                                        <?php echo form_close(); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <div class="panel_s">
                            <div class="panel-heading">
                                <h5 class="panel-title tw-text-base tw-font-semibold">
                                    <?php echo _l('ramos_purchases_recent_batches'); ?>
                                </h5>
                            </div>
                            <div class="panel-body">
                                <?php if (empty($recent_batches)) : ?>
                                    <p class="tw-text-sm tw-text-slate-500 tw-mb-0"><?php echo _l('ramos_purchases_no_batches'); ?></p>
                                <?php else : ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead>
                                                <tr>
                                                    <th><?php echo _l('ramos_purchases_batch_code'); ?></th>
                                                    <th><?php echo _l('ramos_purchases_batch_supplier'); ?></th>
                                                    <th><?php echo _l('ramos_purchases_batch_created_at'); ?></th>
                                                    <th><?php echo _l('ramos_purchases_table_status'); ?></th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_batches as $batch) : ?>
                                                    <?php $badge = ramos_purchase_status_badge_class($batch['status']); ?>
                                                    <?php $label = ramos_purchase_statuses()[$batch['status']] ?? $batch['status']; ?>
                                                    <tr>
                                                        <td><?php echo html_escape($batch['batch_code']); ?></td>
                                                        <td><?php echo html_escape($batch['supplier_name'] ?: _l('ramos_purchases_unassigned_supplier')); ?></td>
                                                        <td><?php echo _dt($batch['created_at']); ?></td>
                                                        <td><span class="label <?php echo $badge; ?>"><?php echo html_escape($label); ?></span></td>
                                                        <td class="tw-text-right">
                                                            <a href="<?php echo admin_url('ramos/purchases/batch/' . $batch['id']); ?>" class="btn btn-default btn-icon" title="<?php echo _l('view'); ?>">
                                                                <i class="fa-regular fa-eye"></i>
                                                            </a>
                                                        </td>
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
    </div>
</div>
<?php init_tail(); ?>
