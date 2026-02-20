<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$canEdit = staff_can('edit', RAMOS_MODULE_NAME);
$inventoryOptions = isset($inventory_options) ? $inventory_options : [];
$staffMembers     = isset($staff_members) ? $staff_members : [];
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-col md:tw-flex-row tw-justify-between tw-items-start md:tw-items-center tw-gap-4 tw-mb-4">
                    <div>
                        <h4 class="tw-text-2xl tw-font-semibold tw-text-slate-900 tw-mb-1"><?php echo _l('ramos_picking_title'); ?></h4>
                        <p class="tw-text-slate-500 tw-mb-0"><?php echo _l('ramos_picking_subtitle'); ?></p>
                    </div>
                    <div>
                        <a href="<?php echo admin_url('ramos/picking/console'); ?>" class="btn btn-primary">
                            <i class="fa-regular fa-clipboard-list tw-mr-1"></i><?php echo _l('ramos_picking_open_console_button'); ?>
                        </a>
                    </div>
                </div>

                <?php if (empty($modules)) : ?>
                    <div class="alert alert-info tw-text-sm"><?php echo _l('ramos_picking_no_modules'); ?></div>
                <?php endif; ?>

                <div class="row">
                    <?php foreach ($modules as $module) :
                        $activeStaff = array_filter($module['staff'], function ($record) {
                            return empty($record['shift_ended_at']);
                        });
                        $recentStaff = array_slice($module['staff'], 0, 5);
                        ?>
                        <div class="col-md-6">
                            <div class="panel_s tw-mb-4">
                                <div class="panel-heading">
                                    <h5 class="panel-title tw-text-base tw-font-semibold">
                                        <?php echo html_escape($module['display_name']); ?>
                                        <?php if (!(int) $module['is_active']) : ?>
                                            <span class="label label-default"><?php echo _l('ramos_picking_inactive'); ?></span>
                                        <?php endif; ?>
                                    </h5>
                                </div>
                                <div class="panel-body tw-space-y-4">
                                    <div>
                                        <h6 class="tw-text-sm tw-font-semibold tw-text-slate-700 tw-uppercase"><?php echo _l('ramos_picking_products_heading'); ?></h6>
                                        <?php if (!empty($module['products'])) : ?>
                                            <ul class="tw-list-disc tw-list-inside tw-text-sm tw-text-slate-600 tw-mb-0">
                                                <?php foreach ($module['products'] as $product) : ?>
                                                    <li><?php echo html_escape($product['item_name']); ?><?php if (!empty($product['unit'])) : ?> <span class="tw-text-2xs tw-text-slate-400"><?php echo html_escape($product['unit']); ?></span><?php endif; ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else : ?>
                                            <p class="tw-text-xs tw-text-slate-500 tw-mb-0"><?php echo _l('ramos_picking_no_products'); ?></p>
                                        <?php endif; ?>
                                        <?php if ($canEdit) : ?>
                                            <?php echo form_open(admin_url('ramos/picking/update_products/' . $module['id'])); ?>
                                                <?php
                                                $selectedProducts = array_map(function ($product) {
                                                    return (int) $product['inventory_item_id'];
                                                }, $module['products']);
                                                echo render_select('product_ids[]', $inventoryOptions, ['id', 'name'], _l('ramos_picking_products_label'), $selectedProducts, ['multiple' => true, 'data-width' => '100%', 'data-live-search' => 'true'], [], 'tw-mt-2');
                                                ?>
                                                <button type="submit" class="btn btn-default btn-sm tw-mt-2"><?php echo _l('ramos_picking_update_products_button'); ?></button>
                                            <?php echo form_close(); ?>
                                        <?php endif; ?>
                                    </div>

                                    <div>
                                        <h6 class="tw-text-sm tw-font-semibold tw-text-slate-700 tw-uppercase"><?php echo _l('ramos_picking_staff_heading'); ?></h6>
                                        <?php if (!empty($activeStaff)) : ?>
                                            <ul class="tw-list-disc tw-list-inside tw-text-sm tw-text-slate-600 tw-mb-2">
                                                <?php foreach ($activeStaff as $record) : ?>
                                                    <li>
                                                        <?php echo html_escape(trim(($record['firstname'] ?? '') . ' ' . ($record['lastname'] ?? ''))); ?>
                                                        <span class="tw-text-2xs tw-text-slate-400"><?php echo _dt($record['shift_started_at']); ?></span>
                                                        <?php if ($canEdit) : ?>
                                                            <a href="<?php echo admin_url('ramos/picking/end_shift/' . $record['id']); ?>" class="btn btn-danger btn-xs tw-ml-2" onclick="return confirm('<?php echo _l('ramos_picking_end_shift_confirm'); ?>');"><?php echo _l('ramos_picking_end_shift_button'); ?></a>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else : ?>
                                            <p class="tw-text-xs tw-text-slate-500 tw-mb-2"><?php echo _l('ramos_picking_no_active_staff'); ?></p>
                                        <?php endif; ?>

                                        <?php if ($canEdit) : ?>
                                            <?php echo form_open(admin_url('ramos/picking/start_shift/' . $module['id'])); ?>
                                                <div class="form-group">
                                                    <label class="control-label"><?php echo _l('ramos_picking_assign_staff_label'); ?></label>
                                                    <select name="staff_id" class="form-control selectpicker" data-live-search="true" data-width="100%">
                                                        <option value="">--</option>
                                                        <?php foreach ($staffMembers as $staff) : ?>
                                                            <option value="<?php echo (int) $staff['staffid']; ?>"><?php echo html_escape($staff['firstname'] . ' ' . $staff['lastname']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-sm"><?php echo _l('ramos_picking_start_shift_button'); ?></button>
                                            <?php echo form_close(); ?>
                                        <?php endif; ?>

                                        <div class="tw-mt-3">
                                            <h6 class="tw-text-xs tw-font-semibold tw-text-slate-500 tw-uppercase"><?php echo _l('ramos_picking_recent_staff_heading'); ?></h6>
                                            <?php if (!empty($recentStaff)) : ?>
                                                <ul class="tw-text-xs tw-text-slate-500 tw-list-disc tw-list-inside tw-mb-0">
                                                    <?php foreach ($recentStaff as $record) : ?>
                                                        <li>
                                                            <?php echo html_escape(trim(($record['firstname'] ?? '') . ' ' . ($record['lastname'] ?? ''))); ?>
                                                            <span class="tw-text-[10px] tw-text-slate-400"><?php echo _dt($record['shift_started_at']); ?><?php if (!empty($record['shift_ended_at'])) : ?> → <?php echo _dt($record['shift_ended_at']); ?><?php endif; ?></span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else : ?>
                                                <p class="tw-text-[10px] tw-text-slate-400 tw-mb-0"><?php echo _l('ramos_picking_no_history'); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ($canEdit) : ?>
                                        <div>
                                            <?php echo form_open(admin_url('ramos/picking/update_module/' . $module['id'])); ?>
                                                <div class="form-group">
                                                    <label class="control-label"><?php echo _l('ramos_picking_module_name_label'); ?></label>
                                                    <input type="text" name="display_name" class="form-control" value="<?php echo html_escape($module['display_name']); ?>">
                                                </div>
                                                <div class="checkbox checkbox-primary">
                                                    <input type="checkbox" name="is_active" id="module_active_<?php echo $module['id']; ?>" value="1" <?php echo (int) $module['is_active'] === 1 ? 'checked' : ''; ?>>
                                                    <label for="module_active_<?php echo $module['id']; ?>"><?php echo _l('ramos_picking_module_active_label'); ?></label>
                                                </div>
                                                <button type="submit" class="btn btn-default btn-sm"><?php echo _l('ramos_picking_save_module_button'); ?></button>
                                            <?php echo form_close(); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
