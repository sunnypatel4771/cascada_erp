<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="page-title-box">
                    <div class="page-title-right">
                        <ol class="breadcrumb">
                            <li><a href="<?php echo admin_url('ramos'); ?>"><?php echo _l('ramos_menu_label'); ?></a></li>
                            <li class="active"><?php echo _l('ramos_equivalencias_title'); ?></li>
                        </ol>
                    </div>
                    <h4 class="page-title"><?php echo _l('ramos_equivalencias_title'); ?></h4>
                    <p class="text-muted"><?php echo _l('ramos_equivalencias_subtitle'); ?></p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <?php if (empty($items)): ?>
                            <div class="alert alert-info"><?php echo _l('ramos_equivalencias_no_items'); ?></div>
                        <?php else: ?>
                        <table class="table table-striped" id="equivalencias-table">
                            <thead>
                                <tr>
                                    <th><?php echo _l('ramos_equivalencias_col_item'); ?></th>
                                    <th><?php echo _l('ramos_equivalencias_col_maduracion'); ?></th>
                                    <th><?php echo _l('ramos_equivalencias_col_units'); ?></th>
                                    <th class="text-right"><?php echo _l('ramos_equivalencias_col_actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                <tr data-item-id="<?php echo (int) $item['id']; ?>">
                                    <td>
                                        <strong><?php echo html_escape($item['description']); ?></strong>
                                        <small class="text-muted"><?php echo _l('ramos_equivalencias_base_unit'); ?>: <?php echo html_escape($item['unit']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($item['has_maduracion']): ?>
                                            <span class="label label-success"><?php echo _l('ramos_equivalencias_has_maduracion'); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="equiv-list" id="equiv-list-<?php echo (int) $item['id']; ?>">
                                        <?php foreach ($item['equivalences'] as $eq): ?>
                                        <span class="label label-default equiv-tag" data-id="<?php echo (int) $eq['id']; ?>">
                                            <?php echo html_escape($eq['unit_name']); ?>
                                            (×<?php echo rtrim(rtrim(number_format((float)$eq['conversion_factor'], 4), '0'), '.'); ?>)
                                            <a href="#" class="equiv-delete text-danger" data-id="<?php echo (int) $eq['id']; ?>" data-item-id="<?php echo (int) $item['id']; ?>">&times;</a>
                                        </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="text-right">
                                        <button type="button" class="btn btn-xs btn-primary btn-add-equiv"
                                            data-item-id="<?php echo (int) $item['id']; ?>"
                                            data-item-name="<?php echo html_escape($item['description']); ?>">
                                            <i class="fa fa-plus"></i> <?php echo _l('ramos_equivalencias_add_unit'); ?>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Equivalencia Modal -->
<div class="modal fade" id="add-equiv-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo _l('ramos_equivalencias_add_unit'); ?>: <span id="modal-item-name"></span></h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modal-item-id">
                <div class="form-group">
                    <label><?php echo _l('ramos_equivalencias_unit_name'); ?></label>
                    <input type="text" id="new-unit-name" class="form-control" placeholder="Kilo, Pieza, Arpilla, Caja...">
                </div>
                <div class="form-group">
                    <label><?php echo _l('ramos_equivalencias_conversion_factor'); ?></label>
                    <input type="number" id="new-conversion-factor" class="form-control" value="1" step="0.0001" min="0.0001">
                    <span class="help-block"><?php echo _l('ramos_equivalencias_conversion_help'); ?></span>
                </div>
                <div class="form-group">
                    <label><?php echo _l('ramos_equivalencias_sort_order'); ?></label>
                    <input type="number" id="new-sort-order" class="form-control" value="0" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="button" class="btn btn-primary" id="btn-save-equiv"><?php echo _l('save'); ?></button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
$(function () {
    // Client-side DataTables (search + pagination) for server-rendered rows.
    // Mirrors pattern used in modules/ramos/views/report/index.php
    if ($.fn.DataTable && $('#equivalencias-table tbody tr').length > 0) {
        $('#equivalencias-table').DataTable({
            serverSide: false,
            processing: false,
            order: [[0, 'asc']],
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, '<?php echo _l('all'); ?>']],
            autoWidth: false,
            initComplete: function() {
                $(this.api().table().container())
                    .closest('.table-loading')
                    .removeClass('table-loading');
                $(this.api().table().node())
                    .removeClass('dt-table-loading');
                mainWrapperHeightFix();
            },
        });
    }

    // Open add modal
    $(document).on('click', '.btn-add-equiv', function () {
        var itemId   = $(this).data('item-id');
        var itemName = $(this).data('item-name');
        $('#modal-item-id').val(itemId);
        $('#modal-item-name').text(itemName);
        $('#new-unit-name').val('');
        $('#new-conversion-factor').val('1');
        $('#new-sort-order').val('0');
        $('#add-equiv-modal').modal('show');
    });

    // Save equivalencia
    $('#btn-save-equiv').on('click', function () {
        var itemId   = $('#modal-item-id').val();
        var unitName = $.trim($('#new-unit-name').val());
        var factor   = parseFloat($('#new-conversion-factor').val()) || 1;
        var order    = parseInt($('#new-sort-order').val()) || 0;

        if (!unitName) {
            alert_float('warning', '<?php echo _l('ramos_equivalencias_unit_required'); ?>');
            return;
        }

        $.post('<?php echo admin_url('ramos/equivalencias/save'); ?>', {
            item_id: itemId,
            unit_name: unitName,
            conversion_factor: factor,
            sort_order: order
        }).done(function (res) {
            if (res.success) {
                $('#add-equiv-modal').modal('hide');
                // Append new tag
                var label = '<span class="label label-default equiv-tag" data-id="' + res.id + '">'
                    + unitName + ' (×' + factor + ')'
                    + ' <a href="#" class="equiv-delete text-danger" data-id="' + res.id + '" data-item-id="' + itemId + '">&times;</a>'
                    + '</span> ';
                $('#equiv-list-' + itemId).append(label);
                alert_float('success', '<?php echo _l('added_successfully'); ?>');
            } else {
                alert_float('danger', '<?php echo _l('something_went_wrong'); ?>');
            }
        });
    });

    // Delete equivalencia
    $(document).on('click', '.equiv-delete', function (e) {
        e.preventDefault();
        var id     = $(this).data('id');
        var itemId = $(this).data('item-id');
        if (!confirm('<?php echo _l('are_you_sure'); ?>')) {
            return;
        }
        var $tag = $(this).closest('.equiv-tag');
        $.post('<?php echo admin_url('ramos/equivalencias/delete'); ?>/' + id).done(function (res) {
            if (res.success) {
                $tag.remove();
                alert_float('success', '<?php echo _l('deleted'); ?>');
            }
        });
    });
});
</script>
