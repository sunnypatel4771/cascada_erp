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
                            <?php echo form_open(admin_url('ramos/routes/update_status/' . $route['id']), ['id' => 'ramos-route-status-form', 'class' => 'tw-flex tw-gap-2 tw-items-center tw-mt-2']); ?>
                                <input type="hidden" name="dispatch_confirmed" id="dispatch_confirmed" value="0">
                                <select name="status" id="ramos-route-status-select" class="form-control selectpicker">
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

                <!-- Delivery Sheet Section -->
                <div class="panel_s tw-mt-4">
                    <div class="panel-heading">
                        <div class="tw-flex tw-justify-between tw-items-center">
                            <h5 class="panel-title tw-text-base tw-font-semibold"><?php echo _l('ramos_routes_delivery_sheet_heading'); ?></h5>
                            <div class="tw-flex tw-gap-2">
                                <button id="copy-delivery-sheet" class="btn btn-default btn-sm">
                                    <i class="fa-regular fa-copy tw-mr-1"></i><?php echo _l('ramos_routes_copy_button'); ?>
                                </button>
                                <button onclick="window.print()" class="btn btn-default btn-sm">
                                    <i class="fa-regular fa-print tw-mr-1"></i><?php echo _l('print'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="panel-body">
                        <?php if (empty($delivery_sheet)) : ?>
                            <p class="tw-text-sm tw-text-slate-500 tw-mb-0"><?php echo _l('ramos_routes_no_stops'); ?></p>
                        <?php else : ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="delivery-sheet-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th><?php echo _l('ramos_routes_delivery_sheet_zone'); ?></th>
                                            <th><?php echo _l('ramos_routes_delivery_sheet_order'); ?></th>
                                            <th><?php echo _l('ramos_routes_delivery_sheet_customer'); ?></th>
                                            <th><?php echo _l('ramos_routes_delivery_sheet_product'); ?></th>
                                            <th><?php echo _l('ramos_routes_delivery_sheet_quantity'); ?></th>
                                            <th><?php echo _l('ramos_routes_delivery_sheet_unit'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $lastZona = null;
                                        foreach ($delivery_sheet as $stop) :
                                            $currentZona = $stop['zona'] ?? _l('ramos_routes_no_zone');
                                            $showZonaHeader = ($lastZona !== $currentZona);
                                        ?>
                                            <?php if ($showZonaHeader) : ?>
                                                <tr class="tw-bg-slate-100">
                                                    <td colspan="7" class="tw-font-semibold tw-text-slate-700">
                                                        <i class="fa-regular fa-location-dot tw-mr-1"></i><?php echo html_escape($currentZona); ?>
                                                    </td>
                                                </tr>
                                                <?php $lastZona = $currentZona; ?>
                                            <?php endif; ?>
                                            <?php if (!empty($stop['products'])) : ?>
                                                <?php $productCount = count($stop['products']); ?>
                                                <?php foreach ($stop['products'] as $index => $product) : ?>
                                                    <tr>
                                                        <?php if ($index === 0) : ?>
                                                            <td rowspan="<?php echo $productCount; ?>" class="tw-align-middle tw-font-semibold"><?php echo (int) $stop['stop_number']; ?></td>
                                                            <td rowspan="<?php echo $productCount; ?>" class="tw-align-middle"><?php echo html_escape($currentZona); ?></td>
                                                            <td rowspan="<?php echo $productCount; ?>" class="tw-align-middle"><?php echo html_escape($stop['order_number']); ?></td>
                                                            <td rowspan="<?php echo $productCount; ?>" class="tw-align-middle tw-font-semibold"><?php echo html_escape($stop['customer_name']); ?></td>
                                                        <?php endif; ?>
                                                        <td><?php echo html_escape($product['item_name']); ?></td>
                                                        <td class="tw-text-right"><?php echo number_format($product['quantity'], 2); ?></td>
                                                        <td><?php echo html_escape($product['unit']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else : ?>
                                                <tr>
                                                    <td class="tw-font-semibold"><?php echo (int) $stop['stop_number']; ?></td>
                                                    <td><?php echo html_escape($currentZona); ?></td>
                                                    <td><?php echo html_escape($stop['order_number']); ?></td>
                                                    <td class="tw-font-semibold"><?php echo html_escape($stop['customer_name']); ?></td>
                                                    <td colspan="3" class="tw-text-slate-500 tw-italic"><?php echo _l('ramos_routes_no_products'); ?></td>
                                                </tr>
                                            <?php endif; ?>
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
<!-- Dispatch readiness warning modal -->
<div class="modal fade" id="ramos-dispatch-warning-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo _l('ramos_routes_dispatch_warning_title'); ?></h4>
            </div>
            <div class="modal-body">
                <p class="tw-text-slate-600 tw-mb-3"><?php echo _l('ramos_routes_dispatch_warning_intro'); ?></p>
                <ul id="ramos-dispatch-issues" class="tw-pl-4 tw-space-y-1 tw-text-sm"></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('cancel'); ?></button>
                <button type="button" class="btn btn-warning" id="ramos-dispatch-proceed"><?php echo _l('ramos_routes_dispatch_proceed_button'); ?></button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
    (function() {
        "use strict";

        var $copyBtn = $('#copy-delivery-sheet');
        var $deliverySheetTable = $('#delivery-sheet-table');

        // Copy delivery sheet to clipboard
        $copyBtn.on('click', function() {
            var text = buildDeliverySheetText();

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    alert_float('success', <?php echo json_encode(_l('ramos_routes_copied_success')); ?>);
                }).catch(function() {
                    fallbackCopy(text);
                });
            } else {
                fallbackCopy(text);
            }
        });

        function buildDeliverySheetText() {
            var text = '<?php echo _l('ramos_routes_delivery_sheet_route_header', html_escape($route['vehicle_label'] ?? 'Route ' . $route['id']), html_escape($route['start_time'] ?: '--')); ?>\n\n';
            var lastCustomer = '';

            $deliverySheetTable.find('tbody tr').each(function() {
                var $cells = $(this).find('td');

                if ($cells.length === 1) {
                    // Zone header row
                    text += '\n' + $cells.eq(0).text().trim() + '\n';
                    text += '----------------------------------------\n';
                } else if ($cells.length === 7) {
                    // Row with all columns (first product for customer)
                    var stopNumber = $cells.eq(0).text().trim();
                    var zone = $cells.eq(1).text().trim();
                    var orderNumber = $cells.eq(2).text().trim();
                    var customer = $cells.eq(3).text().trim();
                    var product = $cells.eq(4).text().trim();
                    var quantity = $cells.eq(5).text().trim();
                    var unit = $cells.eq(6).text().trim();

                    text += stopNumber + '. [' + orderNumber + '] ' + customer + '\n';
                    text += '   ' + product + ' - ' + quantity + ' ' + unit + '\n';
                    lastCustomer = customer;
                } else if ($cells.length === 3) {
                    // Row with only product columns (subsequent products)
                    var product = $cells.eq(0).text().trim();
                    var quantity = $cells.eq(1).text().trim();
                    var unit = $cells.eq(2).text().trim();

                    text += '   ' + product + ' - ' + quantity + ' ' + unit + '\n';
                }
            });

            return text;
        }

        function fallbackCopy(text) {
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();

            try {
                document.execCommand('copy');
                alert_float('success', <?php echo json_encode(_l('ramos_routes_copied_success')); ?>);
            } catch (err) {
                alert_float('danger', 'Unable to copy to clipboard.');
            }

            $temp.remove();
        }

        // Dispatch readiness check
        <?php if (staff_can('edit', RAMOS_MODULE_NAME)) : ?>
        (function() {
            var routeId        = <?php echo (int) $route['id']; ?>;
            var $form          = $('#ramos-route-status-form');
            var $statusSelect  = $('#ramos-route-status-select');
            var $confirmed     = $('#dispatch_confirmed');
            var $modal         = $('#ramos-dispatch-warning-modal');
            var $issues        = $('#ramos-dispatch-issues');
            var $proceedBtn    = $('#ramos-dispatch-proceed');

            var msgNotPicked   = <?php echo json_encode(_l('ramos_routes_dispatch_issue_not_picked')); ?>;
            var msgNotInvoiced = <?php echo json_encode(_l('ramos_routes_dispatch_issue_not_invoiced')); ?>;

            $form.on('submit', function(e) {
                if ($statusSelect.val() !== 'dispatched' || $confirmed.val() === '1') {
                    return true;
                }

                e.preventDefault();

                $.getJSON(admin_url + 'ramos/routes/dispatch_readiness/' + routeId, function(data) {
                    if (data.all_picked && data.all_invoiced) {
                        $confirmed.val('1');
                        $form.submit();
                        return;
                    }

                    $issues.empty();
                    if (!data.all_picked) {
                        $issues.append('<li><i class="fa-regular fa-circle-xmark tw-text-red-500 tw-mr-1"></i>' + msgNotPicked + '</li>');
                    }
                    if (!data.all_invoiced) {
                        $issues.append('<li><i class="fa-regular fa-circle-xmark tw-text-red-500 tw-mr-1"></i>' + msgNotInvoiced + '</li>');
                    }

                    $modal.modal('show');
                }).fail(function() {
                    // If check fails, allow dispatch without warning
                    $confirmed.val('1');
                    $form.submit();
                });
            });

            $proceedBtn.on('click', function() {
                $modal.modal('hide');
                $confirmed.val('1');
                $form.submit();
            });
        })();
        <?php endif; ?>
    })();
</script>
