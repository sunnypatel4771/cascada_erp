<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-col md:tw-flex-row tw-justify-between tw-items-start md:tw-items-center tw-gap-4 tw-mb-4">
                    <div>
                        <h4 class="tw-text-2xl tw-font-semibold tw-text-slate-900 tw-mb-1"><?php echo _l('ramos_pricing_title'); ?></h4>
                        <p class="tw-text-slate-500 tw-mb-0"><?php echo _l('ramos_pricing_subtitle'); ?></p>
                    </div>
                </div>

                <div class="panel_s tw-mb-4">
                    <div class="panel-heading">
                        <h5 class="panel-title tw-text-base tw-font-semibold"><?php echo _l('ramos_pricing_filters_heading'); ?></h5>
                    </div>
                    <div class="panel-body">
                        <?php echo form_open(admin_url('ramos/pricing'), ['method' => 'get', 'class' => 'tw-flex tw-flex-col md:tw-flex-row tw-gap-3 tw-items-start md:tw-items-end']); ?>
                            <div class="form-group">
                                <label for="inventory_item_id"><?php echo _l('ramos_pricing_filter_item'); ?></label>
                                <select name="inventory_item_id" id="inventory_item_id" class="form-control selectpicker" data-live-search="true" data-width="220px">
                                    <option value=""><?php echo _l('ramos_pricing_filter_all_items'); ?></option>
                                    <?php foreach ($inventory_items as $item) : ?>
                                        <option value="<?php echo (int) $item['id']; ?>" <?php echo (int) $selected_inventory === (int) $item['id'] ? 'selected' : ''; ?>>
                                            <?php echo html_escape($item['item_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="customer_id"><?php echo _l('ramos_pricing_filter_customer'); ?></label>
                                <select name="customer_id" id="customer_id" class="form-control selectpicker" data-live-search="true" data-width="220px">
                                    <option value=""><?php echo _l('ramos_pricing_filter_all_customers'); ?></option>
                                    <option value="0" <?php echo $selected_customer === '0' ? 'selected' : ''; ?>><?php echo _l('ramos_pricing_filter_default_customer'); ?></option>
                                    <?php foreach ($customers as $customer) : ?>
                                        <option value="<?php echo (int) $customer['userid']; ?>" <?php echo (string) $selected_customer === (string) $customer['userid'] ? 'selected' : ''; ?>>
                                            <?php echo html_escape($customer['company']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-default btn-sm"><?php echo _l('submit'); ?></button>
                                    <a href="<?php echo admin_url('ramos/pricing'); ?>" class="btn btn-link btn-sm"><?php echo _l('ramos_pricing_filter_reset'); ?></a>
                                </div>
                            </div>
                        <?php echo form_close(); ?>
                    </div>
                </div>

                <?php if (staff_can('create', RAMOS_MODULE_NAME)) : ?>
                    <div class="panel_s tw-mb-4">
                        <div class="panel-heading">
                            <h5 class="panel-title tw-text-base tw-font-semibold"><?php echo _l('ramos_pricing_add_heading'); ?></h5>
                        </div>
                        <div class="panel-body">
                            <?php echo form_open(admin_url('ramos/pricing/store')); ?>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="new_inventory_item"><?php echo _l('ramos_pricing_form_item'); ?></label>
                                            <select name="inventory_item_id" id="new_inventory_item" class="form-control selectpicker" data-live-search="true" required>
                                                <option value=""><?php echo _l('dropdown_non_selected_tex'); ?></option>
                                                <?php foreach ($inventory_items as $item) : ?>
                                                    <option value="<?php echo (int) $item['id']; ?>">
                                                        <?php echo html_escape($item['item_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="new_customer_id"><?php echo _l('ramos_pricing_form_customer'); ?></label>
                                            <select name="customer_id" id="new_customer_id" class="form-control selectpicker" data-live-search="true">
                                                <option value=""><?php echo _l('ramos_pricing_form_default_price'); ?></option>
                                                <?php foreach ($customers as $customer) : ?>
                                                    <option value="<?php echo (int) $customer['userid']; ?>">
                                                        <?php echo html_escape($customer['company']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="new_price"><?php echo _l('ramos_pricing_form_price'); ?></label>
                                            <input type="number" class="form-control" id="new_price" name="price" step="0.01" min="0" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="new_discount"><?php echo _l('ramos_pricing_form_discount'); ?></label>
                                            <input type="number" class="form-control" id="new_discount" name="discount_percent" step="0.01" min="0" max="100">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="new_currency"><?php echo _l('ramos_pricing_form_currency'); ?></label>
                                            <select name="currency" id="new_currency" class="form-control selectpicker" data-width="100%">
                                                <option value=""><?php echo _l('ramos_pricing_form_currency_auto'); ?></option>
                                                <?php foreach ($currencies as $currency) : ?>
                                                    <option value="<?php echo (int) $currency['id']; ?>">
                                                        <?php echo html_escape($currency['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="new_notes"><?php echo _l('ramos_pricing_form_notes'); ?></label>
                                            <input type="text" class="form-control" id="new_notes" name="notes">
                                        </div>
                                    </div>
                                    <div class="col-md-4 tw-flex tw-items-center tw-gap-3">
                                        <div class="checkbox tw-mb-0 tw-mt-3">
                                            <input type="checkbox" id="new_active" name="active" checked>
                                            <label for="new_active"><?php echo _l('ramos_pricing_form_active'); ?></label>
                                        </div>
                                        <button type="submit" class="btn btn-primary tw-mt-2">
                                            <i class="fa-regular fa-plus"></i> <?php echo _l('ramos_pricing_form_add_button'); ?>
                                        </button>
                                    </div>
                                </div>
                            <?php echo form_close(); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="panel_s">
                    <div class="panel-heading">
                        <h5 class="panel-title tw-text-base tw-font-semibold"><?php echo _l('ramos_pricing_table_heading'); ?></h5>
                    </div>
                    <div class="panel-body">
                        <?php if (empty($price_rules)) : ?>
                            <p class="tw-text-sm tw-text-slate-500 tw-mb-0"><?php echo _l('ramos_pricing_empty_state'); ?></p>
                        <?php else : ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th><?php echo _l('ramos_pricing_table_item'); ?></th>
                                            <th><?php echo _l('ramos_pricing_table_customer'); ?></th>
                                            <th><?php echo _l('ramos_pricing_table_price'); ?></th>
                                            <th><?php echo _l('ramos_pricing_table_discount'); ?></th>
                                            <th><?php echo _l('ramos_pricing_table_currency'); ?></th>
                                            <th><?php echo _l('ramos_pricing_table_active'); ?></th>
                                            <th><?php echo _l('ramos_pricing_table_updated'); ?></th>
                                            <th class="tw-text-right"><?php echo _l('ramos_pricing_table_actions'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($price_rules as $rule) :
                                            $ruleId = (int) $rule['id'];
                                            $item   = $inventory_map[(int) $rule['inventory_item_id']] ?? null;
                                            $customer = $rule['customer_id'] ? ($customer_map[(int) $rule['customer_id']] ?? null) : null;
                                            $currency = $rule['currency'] ? ($currency_map[(int) $rule['currency']] ?? null) : null;
                                            ?>
                                            <tr>
                                                <td><?php echo $item ? html_escape($item['item_name']) : _l('ramos_pricing_table_item_unknown'); ?></td>
                                                <td>
                                                    <?php if ($customer) : ?>
                                                        <?php echo html_escape($customer['company']); ?>
                                                    <?php else : ?>
                                                        <span class="label label-default"><?php echo _l('ramos_pricing_table_default'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo app_format_money($rule['price'], $currency ? $currency['name'] : null); ?></td>
                                                <td><?php echo app_format_number($rule['discount_percent']); ?>%</td>
                                                <td><?php echo $currency ? html_escape($currency['name']) : _l('ramos_pricing_table_currency_auto'); ?></td>
                                                <td>
                                                    <span class="label <?php echo $rule['active'] ? 'label-success' : 'label-default'; ?>">
                                                        <?php echo $rule['active'] ? _l('settings_yes') : _l('settings_no'); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo _dt($rule['updated_at'] ?? $rule['created_at']); ?></td>
                                                <td class="tw-text-right">
                                                    <?php if (staff_can('edit', RAMOS_MODULE_NAME)) : ?>
                                                        <button class="btn btn-default btn-icon" data-toggle="modal" data-target="#ramos-rule-<?php echo $ruleId; ?>">
                                                            <i class="fa-regular fa-pen-to-square"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if (staff_can('delete', RAMOS_MODULE_NAME)) : ?>
                                                        <a href="<?php echo admin_url('ramos/pricing/delete/' . $ruleId); ?>" class="btn btn-danger btn-icon" onclick="return confirm('<?php echo _l('ramos_pricing_delete_confirm'); ?>');">
                                                            <i class="fa-regular fa-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>

                                            <?php if (staff_can('edit', RAMOS_MODULE_NAME)) : ?>
                                                <div class="modal fade" id="ramos-rule-<?php echo $ruleId; ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                                    <div class="modal-dialog modal-md" role="document">
                                                        <div class="modal-content">
                                                            <?php echo form_open(admin_url('ramos/pricing/update/' . $ruleId)); ?>
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title"><?php echo _l('ramos_pricing_edit_heading'); ?></h5>
                                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                        <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="form-group">
                                                                        <label><?php echo _l('ramos_pricing_table_item'); ?></label>
                                                                        <p class="form-control-static"><?php echo $item ? html_escape($item['item_name']) : _l('ramos_pricing_table_item_unknown'); ?></p>
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label><?php echo _l('ramos_pricing_table_customer'); ?></label>
                                                                        <p class="form-control-static">
                                                                            <?php echo $customer ? html_escape($customer['company']) : _l('ramos_pricing_table_default'); ?>
                                                                        </p>
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label for="price-<?php echo $ruleId; ?>"><?php echo _l('ramos_pricing_form_price'); ?></label>
                                                                        <input type="number" class="form-control" id="price-<?php echo $ruleId; ?>" name="price" step="0.01" min="0" value="<?php echo html_escape($rule['price']); ?>" required>
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label for="discount-<?php echo $ruleId; ?>"><?php echo _l('ramos_pricing_form_discount'); ?></label>
                                                                        <input type="number" class="form-control" id="discount-<?php echo $ruleId; ?>" name="discount_percent" step="0.01" min="0" max="100" value="<?php echo html_escape($rule['discount_percent']); ?>">
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label for="currency-<?php echo $ruleId; ?>"><?php echo _l('ramos_pricing_form_currency'); ?></label>
                                                                        <select name="currency" id="currency-<?php echo $ruleId; ?>" class="form-control selectpicker" data-width="100%">
                                                                            <option value=""><?php echo _l('ramos_pricing_form_currency_auto'); ?></option>
                                                                            <?php foreach ($currencies as $currencyOption) : ?>
                                                                                <option value="<?php echo (int) $currencyOption['id']; ?>" <?php echo (int) $rule['currency'] === (int) $currencyOption['id'] ? 'selected' : ''; ?>>
                                                                                    <?php echo html_escape($currencyOption['name']); ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label for="notes-<?php echo $ruleId; ?>"><?php echo _l('ramos_pricing_form_notes'); ?></label>
                                                                        <input type="text" class="form-control" id="notes-<?php echo $ruleId; ?>" name="notes" value="<?php echo html_escape($rule['notes']); ?>">
                                                                    </div>
                                                                    <div class="checkbox">
                                                                        <input type="checkbox" id="active-<?php echo $ruleId; ?>" name="active" <?php echo (int) $rule['active'] === 1 ? 'checked' : ''; ?>>
                                                                        <label for="active-<?php echo $ruleId; ?>"><?php echo _l('ramos_pricing_form_active'); ?></label>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                                                                    <button type="submit" class="btn btn-primary"><?php echo _l('save'); ?></button>
                                                                </div>
                                                            <?php echo form_close(); ?>
                                                        </div>
                                                    </div>
                                                </div>
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
<?php init_tail(); ?>
