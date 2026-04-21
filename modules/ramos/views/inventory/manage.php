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
                                        <th style="width:56px"><?php echo _l('ramos_inventory_table_image'); ?></th>
                                        <th><?php echo _l('ramos_inventory_table_item'); ?></th>
                                        <th><?php echo _l('ramos_inventory_table_sku'); ?></th>
                                        <th><?php echo _l('ramos_inventory_table_supplier'); ?></th>
                                        <th><?php echo _l('ramos_inventory_table_quantity'); ?></th>
                                        <th><?php echo _l('ramos_inventory_table_safety'); ?></th>
                                        <th><?php echo _l('ramos_inventory_table_maduracion'); ?></th>
                                        <th><?php echo _l('ramos_inventory_table_status'); ?></th>
                                        <th><?php echo _l('ramos_inventory_table_notes'); ?></th>
                                        <th class="tw-text-right"><?php echo _l('ramos_inventory_table_actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($items)) : ?>
                                        <tr>
                                            <td colspan="10" class="text-center tw-text-slate-500">
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
                                                    'id'             => (int) $item['id'],
                                                    'item_name'      => $item['item_name'],
                                                    'sku'            => $item['sku'],
                                                    'unit'           => $item['unit'],
                                                    'quantity'       => (float) $item['quantity'],
                                                    'safety_stock'   => (float) $item['safety_stock'],
                                                    'buffer_percent' => (float) $item['buffer_percent'],
                                                    'purchase_price' => isset($item['purchase_price']) && $item['purchase_price'] !== null ? (float) $item['purchase_price'] : '',
                                                    'has_maduracion' => (int) ($item['has_maduracion'] ?? 0),
                                                    'supplier_id'    => isset($item['supplier_id']) ? (int) $item['supplier_id'] : null,
                                                    'notes'          => $item['notes'],
                                                    'active'         => (int) $item['active'],
                                                    'image_path'     => $item['image_path'] ?? null,
                                                ], JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>'>
                                                <td style="width:56px">
                                                    <?php if (!empty($item['image_path'])) : ?>
                                                        <img src="<?php echo site_url(html_escape($item['image_path'])); ?>" alt="" class="tw-w-10 tw-h-10 tw-object-cover tw-rounded tw-border tw-border-slate-200" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">
                                                    <?php else : ?>
                                                        <span class="tw-flex tw-items-center tw-justify-center tw-w-10 tw-h-10 tw-rounded tw-border tw-border-dashed tw-border-slate-300 tw-bg-slate-50 tw-text-slate-300" style="width:40px;height:40px;border-radius:4px;border:1px dashed #cbd5e1;display:inline-flex;align-items:center;justify-content:center;">
                                                            <i class="fa-regular fa-image"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
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
                                                    <?php if (!empty($item['has_maduracion'])) : ?>
                                                        <span class="label label-success"><?php echo _l('ramos_inventory_maduracion_on'); ?></span>
                                                    <?php else : ?>
                                                        <span class="label label-default"><?php echo _l('ramos_inventory_maduracion_off'); ?></span>
                                                    <?php endif; ?>
                                                </td>
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
                                                        <button class="btn btn-default btn-icon ramos-upload-image" title="<?php echo _l('ramos_inventory_image_upload_btn'); ?>">
                                                            <i class="fa-regular fa-image"></i>
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
                        <div class="col-md-4">
                            <?php echo render_input('purchase_price', _l('ramos_inventory_form_purchase_price'), '', 'number', ['step' => '0.0001', 'min' => '0']); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo render_select('supplier_id', $supplierOptions, ['id', 'name'], _l('ramos_inventory_form_supplier'), '', ['data-width' => '100%', 'data-live-search' => 'true', 'data-none-selected-text' => _l('dropdown_non_selected_tex')]); ?>
                            <p class="tw-text-xs tw-text-slate-500 tw-mt-1 tw-mb-0"><?php echo _l('ramos_inventory_supplier_active_only_hint'); ?></p>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="has_maduracion" id="ramos_inventory_has_maduracion_add" value="1">
                            <label for="ramos_inventory_has_maduracion_add"><?php echo _l('ramos_inventory_form_has_maduracion'); ?></label>
                        </div>
                        <p class="tw-text-xs tw-text-slate-500 tw-mb-0"><?php echo _l('ramos_inventory_form_has_maduracion_hint'); ?></p>
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
                        <div class="col-md-3">
                            <?php echo render_input('buffer_percent', _l('ramos_inventory_form_buffer_percent'), '25', 'number', ['step' => '0.01']); ?>
                        </div>
                        <div class="col-md-3">
                            <?php echo render_input('purchase_price', _l('ramos_inventory_form_purchase_price'), '', 'number', ['step' => '0.0001', 'min' => '0']); ?>
                        </div>
                        <div class="col-md-3">
                            <?php echo render_select('supplier_id', $supplierOptions, ['id', 'name'], _l('ramos_inventory_form_supplier'), '', ['data-width' => '100%', 'data-live-search' => 'true', 'data-none-selected-text' => _l('dropdown_non_selected_tex')]); ?>
                            <p class="tw-text-xs tw-text-slate-500 tw-mt-1 tw-mb-0"><?php echo _l('ramos_inventory_supplier_active_only_hint'); ?></p>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="active" class="control-label"><?php echo _l('ramos_inventory_form_active'); ?></label>
                                <div class="checkbox checkbox-primary">
                                    <input type="checkbox" name="active" id="ramos_inventory_active" value="1" checked>
                                    <label for="ramos_inventory_active"></label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="has_maduracion" id="ramos_inventory_has_maduracion_edit" value="1">
                            <label for="ramos_inventory_has_maduracion_edit"><?php echo _l('ramos_inventory_form_has_maduracion'); ?></label>
                        </div>
                        <p class="tw-text-xs tw-text-slate-500 tw-mb-0"><?php echo _l('ramos_inventory_form_has_maduracion_hint'); ?></p>
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

<?php if (staff_can('edit', RAMOS_MODULE_NAME)) : ?>
<div class="modal fade" id="ramosImageModal" tabindex="-1" role="dialog" aria-labelledby="ramosImageModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="ramosImageModalLabel"><?php echo _l('ramos_inventory_image_modal_title'); ?></h4>
            </div>
            <div class="modal-body">
                <div id="ramos-current-image-wrap" class="tw-mb-3 tw-text-center" style="display:none;">
                    <img id="ramos-current-image" src="" alt="" class="tw-max-w-full tw-rounded tw-border tw-border-slate-200" style="max-height:180px;">
                    <div class="tw-mt-2">
                        <a id="ramos-remove-image-link" href="#" class="tw-text-xs tw-text-red-500">
                            <i class="fa-regular fa-trash tw-mr-1"></i><?php echo _l('ramos_inventory_image_remove'); ?>
                        </a>
                    </div>
                </div>
                <?php echo form_open_multipart('', ['id' => 'ramos-image-upload-form']); ?>
                    <div class="form-group">
                        <label class="control-label"><?php echo _l('ramos_inventory_image_file_label'); ?></label>
                        <input type="file" name="image" id="ramos-image-file" accept="image/jpeg,image/png,image/gif,image/webp" class="form-control" required>
                        <p class="tw-text-xs tw-text-slate-400 tw-mt-1"><?php echo _l('ramos_inventory_image_hint'); ?></p>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-regular fa-upload tw-mr-1"></i><?php echo _l('ramos_inventory_image_upload_btn'); ?>
                    </button>
                <?php echo form_close(); ?>
            </div>
        </div>
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
        var $imageModal = $('#ramosImageModal');
        var $imageForm = $('#ramos-image-upload-form');

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
            $editForm.find('input[name="purchase_price"]').val(item.purchase_price !== '' ? item.purchase_price : '');
            $('#ramos_inventory_has_maduracion_edit').prop('checked', item.has_maduracion === 1);
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

        $('.ramos-upload-image').on('click', function() {
            var $row = $(this).closest('tr');
            var item = $row.data('item') || {};

            $imageForm.attr('action', baseUrl + 'ramos/inventory/upload_image/' + item.id);
            $('#ramos-image-file').val('');

            var $wrap = $('#ramos-current-image-wrap');
            var $img  = $('#ramos-current-image');
            var $removeLink = $('#ramos-remove-image-link');

            if (item.image_path) {
                $img.attr('src', baseUrl.replace('/admin/', '/') + item.image_path);
                $removeLink.attr('href', baseUrl + 'ramos/inventory/remove_image/' + item.id);
                $wrap.show();
            } else {
                $wrap.hide();
                $img.attr('src', '');
            }

            $imageModal.modal('show');
        });
    })();
</script>
