<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<h4 class="customer-profile-group-heading">
    <?php echo _l('orders'); ?>
</h4>

<?php
$table_data = [
    'ID#',
    _l('order_number'),
    _l('order_date'),
    _l('omni_order_type'),
    _l('payment_method'),
    _l('channel'),
    _l('status'),
    _l('invoice'),
    _l('options')
];

render_datatable($table_data, 'customer-orders');
?>
