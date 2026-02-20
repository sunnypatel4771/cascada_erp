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
                    <div class="tw-flex tw-gap-2">
                        <?php if (staff_can('create', RAMOS_MODULE_NAME)) : ?>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#ramosOrderModal">
                                <i class="fa-regular fa-plus tw-mr-1"></i><?php echo _l('ramos_orders_add_new'); ?>
                            </button>
                            <button class="btn btn-default" data-toggle="modal" data-target="#ramosImportModal">
                                <i class="fa-regular fa-file-import tw-mr-1"></i><?php echo _l('ramos_orders_import_button'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <div class="tw-flex tw-flex-col md:tw-flex-row tw-gap-3 tw-justify-between tw-items-start md:tw-items-center tw-mb-3">
                            <form method="get" class="tw-flex tw-flex-col md:tw-flex-row tw-gap-2 tw-w-full md:tw-w-auto">
                                <div>
                                    <select name="status" class="selectpicker" data-width="180" onchange="this.form.submit()">
                                        <option value=""><?php echo _l('ramos_orders_filter_status_all'); ?></option>
                                        <?php foreach ($statuses as $key => $label) : ?>
                                            <option value="<?php echo html_escape($key); ?>" <?php echo $status_filter === $key ? 'selected' : ''; ?>>
                                                <?php echo html_escape($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="tw-flex tw-gap-2">
                                    <input type="text" name="search" value="<?php echo html_escape($search_term); ?>" class="form-control" placeholder="<?php echo _l('ramos_orders_filter_search_placeholder'); ?>">
                                    <button class="btn btn-default" type="submit">
                                        <i class="fa-regular fa-filter"></i>
                                    </button>
                                    <?php if ($status_filter || $search_term) : ?>
                                        <a href="<?php echo admin_url('ramos/orders'); ?>" class="btn btn-default">
                                            <i class="fa-regular fa-rotate-left"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('ramos_orders_table_order_number'); ?></th>
                                        <th><?php echo _l('ramos_orders_table_customer'); ?></th>
                                        <th><?php echo _l('ramos_orders_table_delivery'); ?></th>
                                        <th><?php echo _l('ramos_orders_table_priority'); ?></th>
                                        <th><?php echo _l('ramos_orders_table_status'); ?></th>
                                        <th><?php echo _l('ramos_orders_table_invoice'); ?></th>
                                        <th><?php echo _l('ramos_orders_table_notes'); ?></th>
                                        <th class="tw-text-right"><?php echo _l('ramos_orders_table_actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)) : ?>
                                        <tr>
                                            <td colspan="8" class="text-center tw-text-slate-500">
                                                <?php echo _l('ramos_orders_empty_state'); ?>
                                            </td>
                                        </tr>
                                    <?php else : ?>
                                        <?php foreach ($orders as $order) : ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo html_escape($order['order_number']); ?></strong>
                                                    <div class="tw-text-2xs tw-text-slate-400">
                                                        <?php echo _dt($order['created_at']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="tw-font-medium"><?php echo html_escape($order['customer_name']); ?></div>
                                                    <div class="tw-text-2xs tw-text-slate-500">
                                                        <?php echo html_escape($order['delivery_address']); ?>
                                                    </div>
                                                    <?php if (!empty($order['client_id']) && isset($clientMap[$order['client_id']])) : ?>
                                                        <div class="tw-text-2xs tw-text-slate-400">
                                                            <i class="fa-regular fa-building tw-mr-1"></i><?php echo sprintf(_l('ramos_orders_customer_linked'), html_escape($clientMap[$order['client_id']]['company'])); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($order['delivery_datetime'])) : ?>
                                                        <span class="label label-default">
                                                            <i class="fa-regular fa-clock tw-mr-1"></i><?php echo _dt($order['delivery_datetime']); ?>
                                                        </span>
                                                    <?php else : ?>
                                                        <span class="tw-text-slate-400"><?php echo _l('ramos_orders_delivery_unscheduled'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $priority = $order['priority'] ?? RAMOS_PRIORITY_NORMAL;
                                                    ?>
                                                    <span class="label <?php echo ramos_order_priority_badge_class($priority); ?>">
                                                        <?php echo html_escape($priorities[$priority] ?? $priority); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (staff_can('edit', RAMOS_MODULE_NAME)) : ?>
                                                        <select class="form-control input-sm ramos-order-status" data-url="<?php echo admin_url('ramos/orders/update_status/' . $order['id']); ?>">
                                                            <?php foreach ($statuses as $statusKey => $statusLabel) : ?>
                                                                <option value="<?php echo html_escape($statusKey); ?>" <?php echo $order['status'] === $statusKey ? 'selected' : ''; ?>>
                                                                    <?php echo html_escape($statusLabel); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php else : ?>
                                                        <span class="label <?php echo ramos_order_status_badge_class($order['status']); ?>">
                                                            <?php echo html_escape($statuses[$order['status']] ?? $order['status']); ?>
                                                        </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($order['invoice_id'])) : ?>
                                                    <a href="<?php echo admin_url('invoices/invoice/' . $order['invoice_id']); ?>" class="label label-success" target="_blank">
                                                        <?php echo _l('ramos_orders_invoice_view'); ?>
                                                    </a>
                                                <?php else : ?>
                                                    <span class="label label-default"><?php echo _l('ramos_orders_invoice_pending'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                                <td>
                                                    <?php if (!empty($order['notes'])) : ?>
                                                        <span class="tw-text-slate-600"><?php echo html_escape($order['notes']); ?></span>
                                                    <?php else : ?>
                                                        <span class="tw-text-slate-400"><?php echo _l('ramos_orders_no_notes'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="tw-text-right tw-space-x-2">
                                                    <a href="<?php echo admin_url('ramos/orders/items/' . $order['id']); ?>" class="btn btn-default btn-icon" title="<?php echo _l('ramos_orders_items_manage'); ?>">
                                                        <i class="fa-regular fa-list"></i>
                                                    </a>
                                                    <?php if (staff_can('delete', RAMOS_MODULE_NAME)) : ?>
                                                        <a href="<?php echo admin_url('ramos/orders/delete/' . $order['id']); ?>" class="btn btn-danger btn-icon" onclick="return confirm('<?php echo _l('ramos_orders_delete_confirm'); ?>');">
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

<?php
$clientMap = [];
if (!empty($clients)) {
    foreach ($clients as $client) {
        $clientMap[(int) $client['userid']] = $client;
    }
}

if (staff_can('create', RAMOS_MODULE_NAME)) :
    $priorityOptions = [];
    foreach ($priorities as $key => $label) {
        $priorityOptions[] = [
            'id'   => $key,
            'name' => $label,
        ];
    }

    $statusOptions = [];
    foreach ($statuses as $key => $label) {
        $statusOptions[] = [
            'id'   => $key,
            'name' => $label,
        ];
    }

    $clientOptions = [];
    foreach ($clientMap as $client) {
        $clientOptions[] = [
            'id'   => $client['userid'],
            'name' => $client['company'],
        ];
    }
    ?>
    <div class="modal fade" id="ramosOrderModal" tabindex="-1" role="dialog" aria-labelledby="ramosOrderModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <?php echo form_open(admin_url('ramos/orders/store'), ['id' => 'ramos-order-form']); ?>
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="ramosOrderModalLabel"><?php echo _l('ramos_orders_add_new'); ?></h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <?php echo render_input('order_number', _l('ramos_orders_form_order_number')); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo render_select('status', $statusOptions, ['id', 'name'], _l('ramos_orders_form_status'), RAMOS_ORDER_STATUS_NEW); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <?php echo render_input('customer_name', _l('ramos_orders_form_customer_name'), '', 'text', ['required' => 'required']); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo render_select('priority', $priorityOptions, ['id', 'name'], _l('ramos_orders_form_priority'), RAMOS_PRIORITY_NORMAL); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <?php echo render_select('client_id', $clientOptions, ['id', 'name'], _l('ramos_orders_form_client'), '', ['data-live-search' => 'true', 'data-width' => '100%', 'data-none-selected-text' => _l('dropdown_non_selected_tex')]); ?>
                        </div>
                    </div>
                    <?php echo render_textarea('delivery_address', _l('ramos_orders_form_delivery_address'), '', ['rows' => 3, 'required' => 'required']); ?>
                    <div class="row">
                        <div class="col-md-6">
                            <?php echo render_input('delivery_date', _l('ramos_orders_form_delivery_date'), '', 'date'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo render_input('delivery_time', _l('ramos_orders_form_delivery_time'), '', 'time'); ?>
                        </div>
                    </div>
                    <?php echo render_textarea('notes', _l('ramos_orders_form_notes'), '', ['rows' => 3]); ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
                </div>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>

    <div class="modal fade" id="ramosImportModal" tabindex="-1" role="dialog" aria-labelledby="ramosImportModalLabel">
        <div class="modal-dialog" role="document">
            <?php echo form_open_multipart(admin_url('ramos/orders/import'), ['id' => 'ramos-import-form']); ?>
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="ramosImportModalLabel"><?php echo _l('ramos_orders_import_heading'); ?></h4>
                </div>
                <div class="modal-body tw-space-y-3">
                    <p class="tw-text-slate-600 tw-text-sm"><?php echo _l('ramos_orders_import_help_text'); ?></p>
                    <div class="form-group">
                        <label for="orders_file" class="control-label"><?php echo _l('ramos_orders_import_file_label'); ?> <span class="text-danger">*</span></label>
                        <input type="file" name="orders_file" id="orders_file" class="form-control" accept=".csv,.xls,.xlsx" required>
                    </div>
                    <ul class="tw-text-xs tw-text-slate-500 tw-list-disc tw-list-inside tw-m-0">
                        <li><?php echo _l('ramos_orders_import_required_columns'); ?></li>
                        <li><?php echo _l('ramos_orders_import_status_hint'); ?></li>
                        <li><?php echo _l('ramos_orders_import_priority_hint'); ?></li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo _l('ramos_orders_import_submit'); ?></button>
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

        var $statusSelectors = $('.ramos-order-status');

        $statusSelectors.on('change', function() {
            var $select = $(this);
            var url = $select.data('url');
            var value = $select.val();

            $select.prop('disabled', true);

            var payload = $.extend({}, typeof csrfData !== 'undefined' ? csrfData.formatted : {}, {
                status: value
            });

            $.post(url, payload).done(function(response) {
                if (response && response.message) {
                    alert_float(response.success ? 'success' : 'danger', response.message);
                }
            }).fail(function(xhr) {
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    alert_float('danger', xhr.responseJSON.message);
                } else {
                    alert_float('danger', '<?php echo _l('ramos_orders_status_update_failed'); ?>');
                }
            }).always(function() {
                $select.prop('disabled', false);
            });
        });
    })();
</script>
