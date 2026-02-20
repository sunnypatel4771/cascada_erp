<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$inventoryOptions = isset($inventory_options) ? $inventory_options : [];
$inventoryMap     = isset($inventory_map) ? $inventory_map : [];
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-col md:tw-flex-row tw-justify-between tw-items-start md:tw-items-center tw-gap-4 tw-mb-4">
                    <div>
                        <h4 class="tw-text-2xl tw-font-semibold tw-text-slate-900 tw-mb-1">
                            <?php echo _l('ramos_orders_items_title'); ?>
                        </h4>
                        <p class="tw-text-slate-500 tw-mb-0">
                            <?php echo _l('ramos_orders_items_subtitle', html_escape($order['order_number'])); ?>
                        </p>
                    </div>
                    <div class="tw-flex tw-gap-2">
                        <a href="<?php echo admin_url('ramos/orders'); ?>" class="btn btn-default">
                            <i class="fa-regular fa-arrow-left-long tw-mr-1"></i><?php echo _l('back'); ?>
                        </a>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <div class="tw-mb-3 tw-text-sm tw-text-slate-600">
                            <strong><?php echo _l('ramos_orders_items_customer'); ?>:</strong> <?php echo html_escape($order['customer_name']); ?><br>
                            <strong><?php echo _l('ramos_orders_items_address'); ?>:</strong> <?php echo html_escape($order['delivery_address']); ?><br>
                            <strong><?php echo _l('ramos_orders_items_status'); ?>:</strong> <?php echo html_escape(ramos_order_statuses()[$order['status']] ?? $order['status']); ?>
                        </div>

                        <div class="tw-flex tw-flex-col md:tw-flex-row md:tw-items-start tw-gap-4">
                            <div class="tw-flex-1">
                                <h5 class="tw-text-lg tw-font-semibold tw-text-slate-900 tw-mb-3">
                                    <?php echo _l('ramos_orders_items_list_heading'); ?>
                                </h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th><?php echo _l('ramos_orders_items_table_item'); ?></th>
                                                <th><?php echo _l('ramos_orders_items_table_quantity'); ?></th>
                                                <th><?php echo _l('ramos_orders_items_table_unit'); ?></th>
                                                <th class="tw-text-right"><?php echo _l('ramos_orders_items_table_actions'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($order_items)) : ?>
                                                <tr>
                                                    <td colspan="4" class="text-center tw-text-slate-500">
                                                        <?php echo _l('ramos_orders_items_empty_state'); ?>
                                                    </td>
                                                </tr>
                                            <?php else : ?>
                                                <?php foreach ($order_items as $item) : ?>
                                                    <tr>
                                                        <td><?php echo html_escape($item['item_name']); ?></td>
                                                <td><?php echo app_format_number($item['quantity']); ?></td>
                                                <td>
                                                    <?php if ($item['inventory_item_id'] && isset($inventoryMap[$item['inventory_item_id']])) : ?>
                                                        <?php echo html_escape($inventoryMap[$item['inventory_item_id']]['unit']); ?>
                                                    <?php elseif ($item['inventory_item_id']) : ?>
                                                        <span class="tw-text-slate-400"><?php echo _l('ramos_orders_items_unit_unknown'); ?></span>
                                                    <?php else : ?>
                                                        <span class="tw-text-slate-400"><?php echo _l('ramos_orders_items_unit_custom'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                        <td class="tw-text-right">
                                                            <?php if (staff_can('delete', RAMOS_MODULE_NAME)) : ?>
                                                                <a href="<?php echo admin_url('ramos/orders/delete_item/' . $order['id'] . '/' . $item['id']); ?>" class="btn btn-danger btn-icon" onclick="return confirm('<?php echo _l('ramos_orders_items_delete_confirm'); ?>');">
                                                                    <i class="fa-regular fa-trash"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <?php if (staff_can('edit', RAMOS_MODULE_NAME)) : ?>
                                <div class="tw-w-full md:tw-w-96">
                                    <div class="panel panel-default">
                                        <div class="panel-heading">
                                            <h5 class="panel-title tw-text-base tw-font-semibold">
                                                <?php echo _l('ramos_orders_items_add_heading'); ?>
                                            </h5>
                                        </div>
                                        <div class="panel-body">
                                            <?php echo form_open(admin_url('ramos/orders/store_item/' . $order['id']), ['id' => 'ramos-order-item-form']); ?>
                                                <?php echo render_select(
                                                    'inventory_item_id',
                                                    $inventoryOptions,
                                                    ['id', 'name'],
                                                    _l('ramos_orders_items_form_inventory'),
                                                    '',
                                                    ['data-width' => '100%', 'data-live-search' => 'true', 'data-none-selected-text' => _l('dropdown_non_selected_tex')]
                                                ); ?>
                                                <?php echo render_input('item_name', _l('ramos_orders_items_form_name')); ?>
                                                <?php echo render_input('quantity', _l('ramos_orders_items_form_quantity'), '', 'number', ['step' => '0.01', 'required' => 'required']); ?>
                                                <div class="alert alert-info tw-text-xs tw-mt-2">
                                                    <?php echo _l('ramos_orders_items_form_hint'); ?>
                                                </div>
                                                <button type="submit" class="btn btn-primary tw-mt-3">
                                                    <?php echo _l('submit'); ?>
                                                </button>
                                            <?php echo form_close(); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    (function() {
        "use strict";
        var $inventorySelect = $('select[name=\"inventory_item_id\"]');
        var $itemNameInput = $('input[name=\"item_name\"]');

        $inventorySelect.on('changed.bs.select', function() {
            var selected = $(this).find('option:selected').text();
            if (selected && !$itemNameInput.val()) {
                $itemNameInput.val(selected);
            }
        });
    })();
</script>
