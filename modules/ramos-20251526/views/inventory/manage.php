<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$suppliers = isset($suppliers) ? $suppliers : [];
$supplierOptions = [];
$supplierMap = [];
foreach ($suppliers as $supplier) {
    $supplierOptions[] = [
        'id'   => $supplier['id'],
        'name' => $supplier['supplier_name'],
    ];
    $supplierMap[$supplier['id']] = $supplier['supplier_name'];
}
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
                        <p class="tw-text-slate-500 tw-mb-0">
                            <?php echo html_escape($subtitle); ?>
                        </p>
                    </div>
                    <div class="tw-flex tw-gap-2">
                        <?php if (staff_can('create', RAMOS_MODULE_NAME)) : ?>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#ramosInventoryModal">
                                <i class="fa-regular fa-plus tw-mr-1"></i><?php echo _l('ramos_inventory_add_item'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <form method="get" class="tw-flex tw-flex-col md:tw-flex-row tw-gap-2 tw-mb-3 tw-justify-between tw-items-start md:tw-items-center">
                            <div class="tw-flex tw-gap-2 tw-items-center">
                                <select name="status" class="selectpicker" data-width="200" onchange="this.form.submit()">
                                    <option value=""><?php echo _l('ramos_inventory_filter_all'); ?></option>
                                    <option value="1" <?php echo ($status === '1') ? 'selected' : ''; ?>><?php echo _l('ramos_inventory_filter_active'); ?></option>
                                    <option value="0" <?php echo ($status === '0') ? 'selected' : ''; ?>><?php echo _l('ramos_inventory_filter_inactive'); ?></option>
                                </select>
                            </div>
                            <div class="tw-flex tw-gap-2">
                                <input type="text" name="search" value="<?php echo html_escape($search_term); ?>" class="form-control" placeholder="<?php echo _l('ramos_inventory_search_placeholder'); ?>">
                                <button class="btn btn-default" type="submit">
                                    <i class="fa-regular fa-filter"></i>
                                </button>
                                <?php if ($search_term !== '' || $status !== null && $status !== '') : ?>
                                    <a href="<?php echo admin_url('ramos/inventory'); ?>" class="btn btn-default">
                                        <i class="fa-regular fa-rotate-left"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('ramos_inventory_table_item'); ?></th>
                                        <th><?php echo _l('ramos_inventory_table_sku'); ?></th>
                                        <th><?php echo _l('ramos_inventory_table_supplier'); ?></th>
                                        <th><?php echo _l('ramos_inventory_table_quantity'); ?></th>
                                        <th><?php echo _l('ramos_inventory_table_safety'); ?></th>
                                        <th><?php echo _l('ramos_inventory_table_status'); ?></th>
                                        <th><?php echo _l('ramos_inventory_table_notes'); ?></th>
                                        <th class="tw-text-right"><?php echo _l('ramos_inventory_table_actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($items)) : ?>
                                        <tr>
                                            <td colspan="8" class="text-center tw-text-slate-500">
                                                <?php echo _l('ramos_inventory_empty_state'); ?>
                                            </td>
                                        </tr>
                                    <?php else : ?>
                                        <?php foreach ($items as $item) :
                                            $statusKey  = ramos_inventory_status((float) $item['quantity'], (float) $item['safety_stock'], (float) $item['buffer_percent']);
                                            $statusText = ramos_inventory_status_label($statusKey);
                                            $statusClass = ramos_inventory_status_badge_class($statusKey);
                                            ?>
                                            <tr data-item='<?php echo json_encode([
                                                    'id'            => (int) $item['id'],
                                                    'item_name'     => $item['item_name'],
                                                    'sku'           => $item['sku'],
                                                    'unit'          => $item['unit'],
                                                    'quantity'      => (float) $item['quantity'],
                                                    'safety_stock'  => (float) $item['safety_stock'],
                                                    'buffer_percent'=> (float) $item['buffer_percent'],
                                                    'supplier_id'   => isset($item['supplier_id']) ? (int) $item['supplier_id'] : null,
                                                    'notes'         => $item['notes'],
                                                    'active'        => (int) $item['active'],
                                                ], JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>'>
                                                <td>
                                                    <strong><?php echo html_escape($item['item_name']); ?></strong>
                                                    <div class="tw-text-2xs tw-text-slate-400"><?php echo html_escape($item['unit']); ?></div>
                                                </td>
                                                <td><?php echo html_escape($item['sku'] ?: _l('ramos_inventory_no_sku')); ?></td>
                                                <td><?php echo html_escape(isset($item['supplier_id']) && isset($supplierMap[$item['supplier_id']]) ? $supplierMap[$item['supplier_id']] : _l('ramos_inventory_no_supplier')); ?></td>
                                                <td>
                                                    <div class="tw-font-medium"><?php echo app_format_number($item['quantity']); ?></div>
                                                    <div class="tw-text-2xs tw-text-slate-400"><?php echo _l('ramos_inventory_unit_label', html_escape($item['unit'])); ?></div>
                                                </td>
                                                <td><?php echo app_format_number($item['safety_stock']); ?></td>
                                                <td>
                                                    <span class="label <?php echo $statusClass; ?>">
                                                        <?php echo html_escape($statusText); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($item['notes'])) : ?>
                                                        <span class="tw-text-slate-600"><?php echo html_escape($item['notes']); ?></span>
                                                    <?php else : ?>
                                                        <span class="tw-text-slate-400"><?php echo _l('ramos_inventory_no_notes'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="tw-text-right tw-space-x-2">
                                                    <?php if (staff_can('edit', RAMOS_MODULE_NAME)) : ?>
                                                        <button class="btn btn-default btn-icon ramos-edit-item">
                                                            <i class="fa-regular fa-pen-to-square"></i>
                                                        </button>
                                                        <button class="btn btn-info btn-icon ramos-adjust-item">
                                                            <i class="fa-regular fa-scale-balanced"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if (staff_can('delete', RAMOS_MODULE_NAME)) : ?>
                                                        <a href="<?php echo admin_url('ramos/inventory/delete/' . $item['id']); ?>" class="btn btn-danger btn-icon" onclick="return confirm('<?php echo _l('ramos_inventory_delete_confirm'); ?>');">
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
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (staff_can('create', RAMOS_MODULE_NAME)) : ?>
    <div class="modal fade" id="ramosInventoryModal" tabindex="-1" role="dialog" aria-labelledby="ramosInventoryModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <?php echo form_open(admin_url('ramos/inventory/store'), ['id' => 'ramos-inventory-form']); ?>
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="ramosInventoryModalLabel"><?php echo _l('ramos_inventory_add_item'); ?></h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <?php echo render_input('item_name', _l('ramos_inventory_form_name'), '', 'text', ['required' => 'required']); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo render_input('sku', _l('ramos_inventory_form_sku')); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <?php echo render_input('unit', _l('ramos_inventory_form_unit'), 'kg'); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo render_input('quantity', _l('ramos_inventory_form_quantity'), '0', 'number', ['step' => '0.01']); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo render_input('safety_stock', _l('ramos_inventory_form_safety_stock'), '0', 'number', ['step' => '0.01']); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <?php echo render_input('buffer_percent', _l('ramos_inventory_form_buffer_percent'), '25', 'number', ['step' => '0.01']); ?>
                        </div>
                        <div class="col-md-8">
                            <?php echo render_select('supplier_id', $supplierOptions, ['id', 'name'], _l('ramos_inventory_form_supplier'), '', ['data-width' => '100%', 'data-live-search' => 'true', 'data-none-selected-text' => _l('dropdown_non_selected_tex')]); ?>
                        </div>
                    </div>
                    <?php echo render_textarea('notes', _l('ramos_inventory_form_notes'), '', ['rows' => 3]); ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
                </div>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
<?php endif; ?>

<?php if (staff_can('edit', RAMOS_MODULE_NAME)) : ?>
    <div class="modal fade" id="ramosInventoryEditModal" tabindex="-1" role="dialog" aria-labelledby="ramosInventoryEditModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <?php echo form_open(admin_url('ramos/inventory/update'), ['id' => 'ramos-inventory-edit-form']); ?>
            <input type="hidden" name="id" value="">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="ramosInventoryEditModalLabel"><?php echo _l('ramos_inventory_edit_item'); ?></h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <?php echo render_input('item_name', _l('ramos_inventory_form_name'), '', 'text', ['required' => 'required']); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo render_input('sku', _l('ramos_inventory_form_sku')); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <?php echo render_input('unit', _l('ramos_inventory_form_unit'), 'kg'); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo render_input('quantity', _l('ramos_inventory_form_quantity'), '0', 'number', ['step' => '0.01']); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo render_input('safety_stock', _l('ramos_inventory_form_safety_stock'), '0', 'number', ['step' => '0.01']); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <?php echo render_input('buffer_percent', _l('ramos_inventory_form_buffer_percent'), '25', 'number', ['step' => '0.01']); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo render_select('supplier_id', $supplierOptions, ['id', 'name'], _l('ramos_inventory_form_supplier'), '', ['data-width' => '100%', 'data-live-search' => 'true', 'data-none-selected-text' => _l('dropdown_non_selected_tex')]); ?>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="active" class="control-label"><?php echo _l('ramos_inventory_form_active'); ?></label>
                                <div class="checkbox checkbox-primary">
                                    <input type="checkbox" name="active" id="ramos_inventory_active" value="1" checked>
                                    <label for="ramos_inventory_active"></label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php echo render_textarea('notes', _l('ramos_inventory_form_notes'), '', ['rows' => 3]); ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo _l('save'); ?></button>
                </div>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>

    <div class="modal fade" id="ramosInventoryAdjustModal" tabindex="-1" role="dialog" aria-labelledby="ramosInventoryAdjustModalLabel">
        <div class="modal-dialog" role="document">
            <?php echo form_open(admin_url('ramos/inventory/adjust'), ['id' => 'ramos-inventory-adjust-form']); ?>
            <input type="hidden" name="id" value="">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="ramosInventoryAdjustModalLabel"><?php echo _l('ramos_inventory_adjust_item'); ?></h4>
                </div>
                <div class="modal-body">
                    <p class="tw-text-sm tw-text-slate-600 tw-mb-3"><?php echo _l('ramos_inventory_adjust_help_text'); ?></p>
                    <?php echo render_input('adjustment', _l('ramos_inventory_adjust_amount'), '0', 'number', ['step' => '0.01']); ?>
                    <div class="alert alert-info tw-text-xs tw-mb-0">
                        <?php echo _l('ramos_inventory_adjust_hint'); ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo _l('save'); ?></button>
                </div>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
<?php endif; ?>

<?php init_tail(); ?>
<script>
    (function() {
        "use strict";

        var baseUrl = <?php echo json_encode(admin_url()); ?>;
        var $editModal = $('#ramosInventoryEditModal');
        var $editForm = $('#ramos-inventory-edit-form');
        var $adjustModal = $('#ramosInventoryAdjustModal');
        var $adjustForm = $('#ramos-inventory-adjust-form');

        $('.ramos-edit-item').on('click', function() {
            var $row = $(this).closest('tr');
            var item = $row.data('item') || {};

            $editForm.attr('action', baseUrl + 'ramos/inventory/update/' + item.id);
            $editForm.find('input[name="id"]').val(item.id);
            $editForm.find('input[name="item_name"]').val(item.item_name);
            $editForm.find('input[name="sku"]').val(item.sku);
            $editForm.find('input[name="unit"]').val(item.unit);
            $editForm.find('input[name="quantity"]').val(item.quantity);
            $editForm.find('input[name="safety_stock"]').val(item.safety_stock);
            $editForm.find('input[name="buffer_percent"]').val(item.buffer_percent);
            $editForm.find('textarea[name="notes"]').val(item.notes);
            var $supplierSelect = $editForm.find('select[name="supplier_id"]');
            $supplierSelect.selectpicker('val', item.supplier_id ? item.supplier_id.toString() : '');
            $('#ramos_inventory_active').prop('checked', item.active === 1);

            $editModal.modal('show');
        });

        $('.ramos-adjust-item').on('click', function() {
            var $row = $(this).closest('tr');
            var item = $row.data('item') || {};

            $adjustForm.attr('action', baseUrl + 'ramos/inventory/adjust/' + item.id);
            $adjustForm.find('input[name="id"]').val(item.id);
            $adjustForm.find('input[name="adjustment"]').val('0');

            $adjustModal.modal('show');
        });
    })();
</script>
