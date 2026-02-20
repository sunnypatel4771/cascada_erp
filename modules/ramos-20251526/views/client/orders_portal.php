<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="panel_s section-heading">
    <div class="panel-body">
        <h4 class="no-margin section-text"><?php echo html_escape($title); ?></h4>
        <p class="text-muted no-margin"><?php echo html_escape($subtitle); ?></p>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="panel_s">
            <div class="panel-heading">
                <h4 class="panel-title"><?php echo _l('ramos_client_orders_form_heading'); ?></h4>
            </div>
            <div class="panel-body">
                <?php echo form_open(site_url('clients/ramos_client/store_order')); ?>
                    <div class="form-group">
                        <label for="customer_name"><?php echo _l('ramos_client_orders_customer_name'); ?></label>
                        <input type="text" class="form-control" id="customer_name" name="customer_name" value="<?php echo html_escape(set_value('customer_name', $default_customer)); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="delivery_address"><?php echo _l('ramos_client_orders_delivery_address'); ?></label>
                        <textarea class="form-control" id="delivery_address" name="delivery_address" rows="3" required><?php echo html_escape(set_value('delivery_address', $default_address)); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="delivery_date"><?php echo _l('ramos_client_orders_delivery_date'); ?></label>
                                <input type="date" class="form-control" id="delivery_date" name="delivery_date" value="<?php echo html_escape(set_value('delivery_date')); ?>">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="delivery_time"><?php echo _l('ramos_client_orders_delivery_time'); ?></label>
                                <input type="time" class="form-control" id="delivery_time" name="delivery_time" value="<?php echo html_escape(set_value('delivery_time')); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="priority"><?php echo _l('ramos_client_orders_priority'); ?></label>
                        <select name="priority" id="priority" class="form-control selectpicker" data-width="100%">
                            <?php foreach ($priorities as $key => $label) : ?>
                                <option value="<?php echo html_escape($key); ?>" <?php echo set_select('priority', $key, $key === RAMOS_PRIORITY_NORMAL); ?>>
                                    <?php echo html_escape($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="notes"><?php echo _l('ramos_client_orders_notes'); ?></label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo html_escape(set_value('notes')); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa-regular fa-paper-plane"></i> <?php echo _l('ramos_client_orders_submit_button'); ?>
                    </button>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="panel_s">
            <div class="panel-heading">
                <h4 class="panel-title"><?php echo _l('ramos_client_orders_upload_heading'); ?></h4>
            </div>
            <div class="panel-body">
                <?php echo form_open_multipart(site_url('clients/ramos_client/upload_orders')); ?>
                    <div class="form-group">
                        <label for="orders_file"><?php echo _l('ramos_client_orders_upload_label'); ?></label>
                        <input type="file" class="form-control" id="orders_file" name="orders_file" accept=".csv,.xlsx,.xls" required>
                    </div>
                    <p class="text-muted small tw-mb-3">
                        <?php echo _l('ramos_client_orders_upload_help'); ?><br>
                        <strong><?php echo _l('ramos_client_orders_upload_required'); ?></strong>
                    </p>
                    <button type="submit" class="btn btn-default">
                        <i class="fa-regular fa-file-import"></i> <?php echo _l('ramos_client_orders_upload_button'); ?>
                    </button>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>

<div class="panel_s">
    <div class="panel-heading">
        <h4 class="panel-title"><?php echo _l('ramos_client_orders_recent_heading'); ?></h4>
    </div>
    <div class="panel-body">
        <?php if (empty($orders)) : ?>
            <p class="text-muted no-margin"><?php echo _l('ramos_client_orders_none'); ?></p>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><?php echo _l('ramos_client_orders_table_number'); ?></th>
                            <th><?php echo _l('ramos_client_orders_table_delivery'); ?></th>
                            <th><?php echo _l('ramos_client_orders_table_priority'); ?></th>
                            <th><?php echo _l('ramos_client_orders_table_status'); ?></th>
                            <th><?php echo _l('ramos_client_orders_table_created'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order) :
                            $statusLabel = ramos_order_statuses()[$order['status']] ?? ucfirst($order['status']);
                            ?>
                            <tr>
                                <td><?php echo html_escape($order['order_number']); ?></td>
                                <td><?php echo $order['delivery_datetime'] ? _dt($order['delivery_datetime']) : _l('ramos_orders_delivery_unscheduled'); ?></td>
                                <td>
                                    <span class="label <?php echo ramos_order_priority_badge_class($order['priority']); ?>">
                                        <?php echo html_escape(ramos_order_priorities()[$order['priority']] ?? ucfirst($order['priority'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="label <?php echo ramos_order_status_badge_class($order['status']); ?>">
                                        <?php echo html_escape($statusLabel); ?>
                                    </span>
                                </td>
                                <td><?php echo _dt($order['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
