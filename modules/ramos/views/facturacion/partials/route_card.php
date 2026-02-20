<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$routeName = html_escape($route['vehicle_label'] ?? 'Route');
$routeStartTime = !empty($route['start_time']) ? substr($route['start_time'], 0, 5) : '';
$routeStatus = $route['status'] ?? 'draft';
$statusBadgeClass = ramos_route_status_badge_class($routeStatus);
?>
<div class="panel_s tw-mb-4">
    <div class="panel-heading tw-bg-slate-50">
        <div class="tw-flex tw-justify-between tw-items-center">
            <h5 class="panel-title tw-text-lg tw-font-bold tw-text-slate-800">
                <i class="fa-regular fa-truck tw-mr-2"></i><?php echo $routeName; ?>
                <?php if ($routeStartTime) : ?>
                    <span class="tw-text-sm tw-font-normal tw-text-slate-500 tw-ml-2">
                        <i class="fa-regular fa-clock"></i> <?php echo $routeStartTime; ?>
                    </span>
                <?php endif; ?>
            </h5>
            <span class="label <?php echo $statusBadgeClass; ?>"><?php echo html_escape(ramos_route_statuses()[$routeStatus] ?? $routeStatus); ?></span>
        </div>
    </div>
    <div class="panel-body tw-p-0">
        <?php if (empty($customers)) : ?>
            <div class="tw-p-4 tw-text-slate-500 tw-text-sm"><?php echo _l('ramos_facturacion_no_customers'); ?></div>
        <?php else : ?>
            <?php foreach ($customers as $customer) :
                $orderStatus = $customer['status'] ?? 'red';
                $statusBadge = $statusLabels[$orderStatus] ?? 'label-default';
                $hasInvoice = !empty($customer['invoice_id']);
            ?>
                <div class="tw-border-b tw-border-slate-200 last:tw-border-b-0">
                    <div class="tw-p-4 tw-bg-slate-100 tw-flex tw-justify-between tw-items-center">
                        <div>
                            <h6 class="tw-font-semibold tw-text-slate-800 tw-mb-0">
                                <?php echo html_escape($customer['customer_name']); ?>
                                <span class="label <?php echo $statusBadge; ?> tw-ml-2"><?php echo _l('ramos_facturacion_status_' . $orderStatus); ?></span>
                            </h6>
                            <div class="tw-text-xs tw-text-slate-500">
                                <i class="fa-regular fa-receipt tw-mr-1"></i><?php echo html_escape($customer['order_number']); ?>
                                <?php if ($customer['delivery_address']) : ?>
                                    <span class="tw-mx-2">|</span>
                                    <i class="fa-regular fa-location-dot tw-mr-1"></i><?php echo html_escape($customer['delivery_address']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="tw-flex tw-gap-2">
                            <?php if ($hasInvoice) : ?>
                                <a href="<?php echo admin_url('invoices/invoice/' . $customer['invoice_id']); ?>" class="btn btn-success btn-sm" target="_blank">
                                    <i class="fa-regular fa-file-invoice tw-mr-1"></i><?php echo _l('ramos_facturacion_view_invoice'); ?>
                                </a>
                            <?php else : ?>
                                <div class="tw-text-center">
                                    <div class="tw-text-xs tw-text-slate-500 tw-mb-1"><?php echo _l('ramos_facturacion_generate_documents'); ?></div>
                                    <div class="btn-group">
                                        <a href="<?php echo admin_url('ramos/facturacion/generate_invoice/' . $customer['order_id']); ?>"
                                           class="btn btn-primary btn-sm"
                                           onclick="return confirm('<?php echo _l('ramos_facturacion_confirm_invoice'); ?>');">
                                            <i class="fa-regular fa-file-invoice tw-mr-1"></i><?php echo _l('ramos_facturacion_btn_factura'); ?>
                                        </a>
                                        <a href="<?php echo admin_url('ramos/facturacion/generate_remision/' . $customer['order_id']); ?>"
                                           class="btn btn-default btn-sm" target="_blank">
                                            <i class="fa-regular fa-file-lines tw-mr-1"></i><?php echo _l('ramos_facturacion_btn_remision'); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-condensed tw-mb-0">
                            <thead>
                                <tr class="tw-bg-slate-50">
                                    <th class="tw-text-xs tw-uppercase tw-text-slate-500"><?php echo _l('ramos_facturacion_col_product'); ?></th>
                                    <th class="tw-text-xs tw-uppercase tw-text-slate-500 tw-text-center"><?php echo _l('ramos_facturacion_col_pedido'); ?></th>
                                    <th class="tw-text-xs tw-uppercase tw-text-slate-500 tw-text-center"><?php echo _l('ramos_facturacion_col_surtido'); ?></th>
                                    <th class="tw-text-xs tw-uppercase tw-text-slate-500 tw-text-center"><?php echo _l('ramos_facturacion_col_peso'); ?></th>
                                    <?php if ($can_edit) : ?>
                                        <th class="tw-text-xs tw-uppercase tw-text-slate-500 tw-text-right"><?php echo _l('ramos_facturacion_col_actions'); ?></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customer['items'] as $item) :
                                    $itemStatus = $item['pick_status'] ?? 'pending';
                                    $rowClass = '';
                                    if ($itemStatus === 'completed') {
                                        $rowClass = 'tw-bg-green-50';
                                    } elseif ($itemStatus === 'weight_missing') {
                                        $rowClass = 'tw-bg-yellow-50';
                                    } elseif ($itemStatus === 'pending' || $itemStatus === 'in_progress') {
                                        $rowClass = 'tw-bg-red-50';
                                    }
                                ?>
                                    <tr class="<?php echo $rowClass; ?>">
                                        <td class="tw-text-sm tw-font-medium">
                                            <?php echo html_escape($item['item_name']); ?>
                                            <span class="tw-text-xs tw-text-slate-400 tw-ml-1">(<?php echo html_escape($item['unit'] ?? 'pz'); ?>)</span>
                                        </td>
                                        <td class="tw-text-center tw-text-sm">
                                            <?php echo number_format((float) $item['required_qty'], 2); ?>
                                        </td>
                                        <td class="tw-text-center tw-text-sm">
                                            <?php echo number_format((float) $item['picked_qty'], 2); ?>
                                        </td>
                                        <td class="tw-text-center tw-text-sm">
                                            <?php echo number_format((float) $item['weight'], 2); ?> kg
                                        </td>
                                        <?php if ($can_edit) : ?>
                                            <td class="tw-text-right">
                                                <?php if ((int) $item['pick_id'] > 0) : ?>
                                                    <?php echo form_open(admin_url('ramos/facturacion/update_item/' . $item['pick_id']), ['class' => 'form-inline tw-inline-flex tw-gap-1']); ?>
                                                        <input type="number" name="picked_qty" class="form-control input-sm tw-w-16"
                                                               value="<?php echo (float) $item['picked_qty']; ?>" step="0.01" min="0" placeholder="Qty">
                                                        <input type="number" name="weight" class="form-control input-sm tw-w-16"
                                                               value="<?php echo (float) $item['weight']; ?>" step="0.01" min="0" placeholder="Peso">
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <?php echo _l('ramos_facturacion_btn_guardar'); ?>
                                                        </button>
                                                    <?php echo form_close(); ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="tw-bg-slate-100">
                                    <td colspan="<?php echo $can_edit ? 5 : 4; ?>" class="tw-text-right tw-font-bold">
                                        <?php echo _l('ramos_facturacion_total'); ?>: $<?php echo number_format((float) $customer['total'], 2); ?>
                                        <span class="tw-text-slate-500 tw-font-normal tw-ml-2">(<?php echo count($customer['items']); ?> <?php echo _l('ramos_facturacion_products'); ?>)</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
