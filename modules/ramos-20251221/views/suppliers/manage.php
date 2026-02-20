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
                    <div>
                        <?php if (staff_can('create', RAMOS_MODULE_NAME)) : ?>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#ramosSupplierModal">
                                <i class="fa-regular fa-plus tw-mr-1"></i><?php echo _l('ramos_suppliers_add'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('ramos_suppliers_table_name'); ?></th>
                                        <th><?php echo _l('ramos_suppliers_table_contact'); ?></th>
                                        <th><?php echo _l('ramos_suppliers_table_phone'); ?></th>
                                        <th><?php echo _l('ramos_suppliers_table_email'); ?></th>
                                        <th><?php echo _l('ramos_suppliers_table_priority'); ?></th>
                                        <th><?php echo _l('ramos_suppliers_table_status'); ?></th>
                                        <th><?php echo _l('ramos_suppliers_table_notes'); ?></th>
                                        <th class="tw-text-right"><?php echo _l('ramos_suppliers_table_actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($suppliers)) : ?>
                                        <tr>
                                            <td colspan="8" class="text-center tw-text-slate-500">
                                                <?php echo _l('ramos_suppliers_empty_state'); ?>
                                            </td>
                                        </tr>
                                    <?php else : ?>
                                        <?php foreach ($suppliers as $supplier) : ?>
                                            <tr data-supplier='<?php echo json_encode([
                                                    'id'           => (int) $supplier['id'],
                                                    'supplier_name'=> $supplier['supplier_name'],
                                                    'contact_name' => $supplier['contact_name'],
                                                    'phone'        => $supplier['phone'],
                                                    'email'        => $supplier['email'],
                                                    'priority'     => (int) $supplier['priority'],
                                                    'notes'        => $supplier['notes'],
                                                    'active'       => (int) $supplier['active'],
                                                ], JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>'>
                                                <td><?php echo html_escape($supplier['supplier_name']); ?></td>
                                                <td><?php echo html_escape($supplier['contact_name'] ?: _l('ramos_suppliers_no_contact')); ?></td>
                                                <td><?php echo html_escape($supplier['phone'] ?: _l('ramos_suppliers_no_phone')); ?></td>
                                                <td><?php echo html_escape($supplier['email'] ?: _l('ramos_suppliers_no_email')); ?></td>
                                                <td>
                                                    <?php
                                                    $priority = (int) $supplier['priority'];
                                                    $priorityLabel = $priority === 999 ? _l('ramos_suppliers_no_priority') : $priority;
                                                    $priorityClass = $priority <= 10 ? 'label-success' : ($priority <= 50 ? 'label-info' : 'label-default');
                                                    ?>
                                                    <span class="label <?php echo $priorityClass; ?>">
                                                        <?php echo html_escape($priorityLabel); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="label <?php echo $supplier['active'] ? 'label-success' : 'label-default'; ?>">
                                                        <?php echo $supplier['active'] ? _l('ramos_suppliers_active') : _l('ramos_suppliers_inactive'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($supplier['notes'])) : ?>
                                                        <span class="tw-text-slate-600"><?php echo html_escape($supplier['notes']); ?></span>
                                                    <?php else : ?>
                                                        <span class="tw-text-slate-400"><?php echo _l('ramos_suppliers_no_notes'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="tw-text-right tw-space-x-2">
                                                    <?php if (staff_can('edit', RAMOS_MODULE_NAME)) : ?>
                                                        <button class="btn btn-default btn-icon ramos-edit-supplier">
                                                            <i class="fa-regular fa-pen-to-square"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if (staff_can('delete', RAMOS_MODULE_NAME)) : ?>
                                                        <a href="<?php echo admin_url('ramos/suppliers/delete/' . $supplier['id']); ?>" class="btn btn-danger btn-icon" onclick="return confirm('<?php echo _l('ramos_suppliers_delete_confirm'); ?>');">
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
    <div class="modal fade" id="ramosSupplierModal" tabindex="-1" role="dialog" aria-labelledby="ramosSupplierModalLabel">
        <div class="modal-dialog" role="document">
            <?php echo form_open(admin_url('ramos/suppliers/store'), ['id' => 'ramos-supplier-form']); ?>
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="ramosSupplierModalLabel"><?php echo _l('ramos_suppliers_add'); ?></h4>
                </div>
                <div class="modal-body">
                    <?php echo render_input('supplier_name', _l('ramos_suppliers_form_name'), '', 'text', ['required' => 'required']); ?>
                    <?php echo render_input('contact_name', _l('ramos_suppliers_form_contact')); ?>
                    <?php echo render_input('phone', _l('ramos_suppliers_form_phone')); ?>
                    <?php echo render_input('email', _l('ramos_suppliers_form_email')); ?>
                    <?php echo render_input('priority', _l('ramos_suppliers_form_priority'), '999', 'number', [
                        'min' => '1',
                        'max' => '999',
                        'data-toggle' => 'tooltip',
                        'title' => _l('ramos_suppliers_priority_help')
                    ]); ?>
                    <?php echo render_textarea('notes', _l('ramos_suppliers_form_notes'), '', ['rows' => 3]); ?>
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
    <div class="modal fade" id="ramosSupplierEditModal" tabindex="-1" role="dialog" aria-labelledby="ramosSupplierEditModalLabel">
        <div class="modal-dialog" role="document">
            <?php echo form_open(admin_url('ramos/suppliers/update'), ['id' => 'ramos-supplier-edit-form']); ?>
            <input type="hidden" name="id" value="">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="ramosSupplierEditModalLabel"><?php echo _l('ramos_suppliers_edit'); ?></h4>
                </div>
                <div class="modal-body">
                    <?php echo render_input('supplier_name', _l('ramos_suppliers_form_name'), '', 'text', ['required' => 'required']); ?>
                    <?php echo render_input('contact_name', _l('ramos_suppliers_form_contact')); ?>
                    <?php echo render_input('phone', _l('ramos_suppliers_form_phone')); ?>
                    <?php echo render_input('email', _l('ramos_suppliers_form_email')); ?>
                    <?php echo render_input('priority', _l('ramos_suppliers_form_priority'), '999', 'number', [
                        'min' => '1',
                        'max' => '999',
                        'data-toggle' => 'tooltip',
                        'title' => _l('ramos_suppliers_priority_help')
                    ]); ?>
                    <?php echo render_textarea('notes', _l('ramos_suppliers_form_notes'), '', ['rows' => 3]); ?>
                    <div class="form-group">
                        <label for="ramos_supplier_active" class="control-label"><?php echo _l('ramos_suppliers_form_active'); ?></label>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="active" id="ramos_supplier_active" value="1" checked>
                            <label for="ramos_supplier_active"></label>
                        </div>
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
        var $editModal = $('#ramosSupplierEditModal');
        var $editForm = $('#ramos-supplier-edit-form');

        $('.ramos-edit-supplier').on('click', function() {
            var $row = $(this).closest('tr');
            var supplier = $row.data('supplier') || {};

            $editForm.attr('action', baseUrl + 'ramos/suppliers/update/' + supplier.id);
            $editForm.find('input[name=\"id\"]').val(supplier.id);
            $editForm.find('input[name=\"supplier_name\"]').val(supplier.supplier_name);
            $editForm.find('input[name=\"contact_name\"]').val(supplier.contact_name);
            $editForm.find('input[name=\"phone\"]').val(supplier.phone);
            $editForm.find('input[name=\"email\"]').val(supplier.email);
            $editForm.find('input[name=\"priority\"]').val(supplier.priority || 999);
            $editForm.find('textarea[name=\"notes\"]').val(supplier.notes);
            $('#ramos_supplier_active').prop('checked', supplier.active === 1);

            $editModal.modal('show');
        });
    })();
</script>
