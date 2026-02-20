<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="row">
    <div class="col-md-12 section-client-dashboard">
        <h3 id="greeting" class="tw-font-semibold tw-mt-0"></h3>
        <?php if (has_contact_permission('projects')) { ?>
        <h3 class="projects-summary-heading tw-text-neutral-700 tw-font-medium tw-text-lg tw-mt-7">
            <?= _l('projects_summary'); ?>
        </h3>
        <?php get_template_part('projects/project_summary'); ?>
        <?php } ?>
        <?php hooks()->do_action('client_area_after_project_overview'); ?>
        <?php
            if (has_contact_permission('invoices')) { ?>
        <div class="tw-mb-3">
            <h3 class="invoices-quick-info-heading tw-text-neutral-700 tw-font-medium tw-text-lg tw-mt-7 tw-mb-0">
                <?= _l('clients_quick_invoice_info'); ?>
            </h3>
            <?php if (has_contact_permission('invoices')) { ?>
            <a href="<?= site_url('clients/statement'); ?>"
                class="tw-text-sm">
                <?= _l('view_account_statement'); ?>
            </a>
            <?php } ?>
        </div>
        <div class="panel_s">
            <div class="panel-body">
                <?php get_template_part('invoices_stats'); ?>
                <hr />
                <div class="row">
                    <div class="col-md-3">
                        <?php if (count($payments_years) > 0) { ?>
                        <div class="form-group">
                            <select
                                data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>"
                                class="form-control" id="payments_year" name="payments_years" data-width="100%"
                                onchange="total_income_bar_report();"
                                data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                                <?php foreach ($payments_years as $year) { ?>
                                <option
                                    value="<?= e($year['year']); ?>"
                                    <?php if ($year['year'] == date('Y')) {
                                        echo 'selected';
                                    } ?>>
                                    <?= e($year['year']); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php } ?>
                        <?php if (is_client_using_multiple_currencies()) { ?>
                        <div id="currency" class="form-group mtop15" data-toggle="tooltip"
                            title="<?= _l('clients_home_currency_select_tooltip'); ?>">
                            <select
                                data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>"
                                class="form-control" name="currency">
                                <?php foreach ($currencies as $currency) {
                                    $selected = '';
                                    if ($currency['isdefault'] == 1) {
                                        $selected = 'selected';
                                    } ?>
                                <option
                                    value="<?= e($currency['id']); ?>"
                                    <?= e($selected); ?>>
                                    <?= e($currency['symbol']); ?>
                                    -
                                    <?= e($currency['name']); ?>
                                </option>
                                <?php
                                } ?>
                            </select>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="relative" style="max-height:400px;">
                            <canvas id="client-home-chart" height="400" class="animated fadeIn"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mtop20">
            <div class="col-md-12">
                <h3><?php echo _l('order_management'); ?></h3>
            </div>
        </div>

        <div class="row">
            <!-- Previous Order (Left Side) -->
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title"><?php echo _l('previous_order'); ?></h4>
                    </div>
                    <div class="panel-body">
                        <?php if (isset($latest_order) && $latest_order && !empty($latest_order_items)): ?>
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('product'); ?></th>
                                        <th><?php echo _l('unit'); ?></th>
                                        <th><?php echo _l('quantity'); ?></th>
                                        <th><?php echo _l('rate'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($latest_order_items as $item): ?>
                                    <tr>
                                        <td><?php echo $item->description; ?></td>
                                        <td><?php echo $item->unit; ?></td>
                                        <td><?php echo (int)$item->qty; ?></td>
                                        <td><?php echo number_format($item->rate, 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <?php echo _l('no_previous_orders'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- New Order (Right Side) -->
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title"><?php echo _l('new_order'); ?></h4>
                    </div>
                    <div class="panel-body">
                        <form id="new-order-form">
                            <table class="table table-bordered" id="new-order-table">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('product'); ?></th>
                                        <th><?php echo _l('unit'); ?></th>
                                        <th><?php echo _l('quantity'); ?></th>
                                        <th><?php echo _l('rate'); ?></th>
                                        <th><?php echo _l('action'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="order-items-body">
                                    <!-- Order items will be added here dynamically -->
                                </tbody>
                            </table>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <button type="button" class="btn btn-success" onclick="addNewRow()">
                                        <i class="fa fa-plus"></i> <?php echo _l('add_item'); ?>
                                    </button>
                                    <button type="button" class="btn btn-primary pull-right" onclick="saveNewOrder()">
                                        <i class="fa fa-save"></i> <?php echo _l('save_order'); ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="row mtop15">
                                <div class="col-md-12">
                                    <h4 class="pull-right">
                                        <?php echo _l('order_total'); ?>: 
                                        <span id="order-total">0.00</span>
                                    </h4>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- More Products Section -->
        <div class="row mtop20">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title"><?php echo _l('more_products'); ?></h4>
                    </div>
                    <div class="panel-body">
                        <div class="row" id="products-catalog">
                            <?php if (isset($available_products) && !empty($available_products)): ?>
                                <?php foreach ($available_products as $group => $products): ?>
                                    <?php foreach ($products as $product): ?>
                                    <div class="col-md-3 col-sm-6 col-xs-12">
                                        <div class="panel panel-default product-card" style="cursor: pointer;" 
                                            onclick='addProductToOrder(<?php echo json_encode($product); ?>)'>
                                            <div class="panel-body text-center">
                                                <i class="fa fa-cube fa-3x text-primary"></i>
                                                <h5><strong><?php echo $product['description']; ?></strong></h5>
                                                <p class="text-muted"><?php echo $product['unit'] ? $product['unit'] : 'N/A'; ?></p>
                                                <p><strong><?php echo number_format($product['rate'], 2); ?></strong></p>
                                                <button type="button" class="btn btn-sm btn-success">
                                                    <i class="fa fa-plus"></i> <?php echo _l('add_to_order'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-md-12">
                                    <p class="text-muted"><?php echo _l('no_products_available'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php } ?>
        <?php hooks()->do_action('client_area_dashboard_end'); ?>
    </div>
    <script>
        var greetDate = new Date();
        var hrsGreet = greetDate.getHours();

        var greet;
        if (hrsGreet < 12)
            greet = "<?= _l('good_morning'); ?>";
        else if (hrsGreet >= 12 && hrsGreet <= 17)
            greet = "<?= _l('good_afternoon'); ?>";
        else if (hrsGreet >= 17 && hrsGreet <= 24)
            greet = "<?= _l('good_evening'); ?>";

        if (greet) {
            document.getElementById('greeting').innerHTML =
                '<b>' + greet + ' <?= e($contact->firstname); ?>!</b>';
        }
    </script>
    
    <script>
        let orderItemsCounter = 0;
        let previousOrderData = <?php echo isset($latest_order_items) ? json_encode($latest_order_items) : '[]'; ?>;
        let allProducts = <?php echo isset($all_products) ? json_encode($all_products) : '[]'; ?>;

        // Create product options HTML
        function getProductOptionsHTML(selectedDescription = '') {
            let options = '<option value="">-- Select Product --</option>';
            allProducts.forEach(product => {
                const selected = product.description === selectedDescription ? 'selected' : '';
                options += `<option value="${product.description}" 
                                data-unit="${product.unit || ''}" 
                                data-rate="${product.rate}" 
                                data-long="${product.long_description || ''}"
                                ${selected}>
                                ${product.description}
                            </option>`;
            });
            return options;
        }

        // Add new empty row to order
        function addNewRow() {
            const tbody = document.getElementById('order-items-body');
            const row = createOrderRow('', '', 1, 0);
            tbody.appendChild(row);
            calculateTotal();
        }

        // Create order row HTML with dropdown
        function createOrderRow(description, unit, qty, rate, longDescription = '') {
            orderItemsCounter++;
            const row = document.createElement('tr');
            row.id = `order-row-${orderItemsCounter}`;
            
            row.innerHTML = `
                <td>
                    <select class="form-control input-sm product-select" 
                            name="items[${orderItemsCounter}][description]" 
                            required 
                            onchange="updateProductDetails(${orderItemsCounter})"
                            data-counter="${orderItemsCounter}">
                        ${getProductOptionsHTML(description)}
                    </select>
                    <input type="hidden" name="items[${orderItemsCounter}][long_description]" value="${longDescription}">
                </td>
                <td>
                    <input type="text" class="form-control input-sm" 
                        id="unit-${orderItemsCounter}"
                        name="items[${orderItemsCounter}][unit]" 
                        value="${unit}" 
                        readonly 
                        style="background-color: #f5f5f5;">
                </td>
                <td>
                    <input type="number" class="form-control input-sm" 
                        name="items[${orderItemsCounter}][qty]" 
                        value="${qty}" 
                        min="1" 
                        step="1" 
                        required 
                        onchange="calculateTotal()">
                </td>
                <td>
                    <input type="number" class="form-control input-sm" 
                        id="rate-${orderItemsCounter}"
                        name="items[${orderItemsCounter}][rate]" 
                        value="${rate}" 
                        min="0" 
                        step="0.01" 
                        required 
                        readonly
                        style="background-color: #f5f5f5;"
                        onchange="calculateTotal()">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-xs" onclick="removeRow(${orderItemsCounter})">
                        <i class="fa fa-trash"></i>
                    </button>
                </td>
            `;
            
            return row;
        }

        // Update unit and rate when product is selected
        function updateProductDetails(counter) {
            const select = document.querySelector(`select[data-counter="${counter}"]`);
            if (!select) return;
            
            const selectedOption = select.options[select.selectedIndex];
            const selectedProduct = selectedOption ? selectedOption.value : '';
            
            // Check if this product already exists in another row
            if (selectedProduct && selectedProduct !== '') {
                const rows = document.querySelectorAll('#order-items-body tr');
                const currentRow = document.getElementById(`order-row-${counter}`);
                
                for (let row of rows) {
                    if (row === currentRow) continue;
                    
                    const otherSelect = row.querySelector('select.product-select');
                    if (otherSelect) {
                        const otherOption = otherSelect.options[otherSelect.selectedIndex];
                        const otherValue = otherOption ? otherOption.value : '';
                        
                        if (otherValue === selectedProduct) {
                            alert('<?php echo _l('product_already_in_order'); ?>');
                            select.selectedIndex = 0; // Reset to first option (-- Select Product --)
                            
                            // Update bootstrap-select display
                            if (typeof $ !== 'undefined' && $.fn.selectpicker) {
                                $(select).selectpicker('refresh');
                            }
                            
                            document.getElementById(`unit-${counter}`).value = '';
                            document.getElementById(`rate-${counter}`).value = 0;
                            calculateTotal();
                            return;
                        }
                    }
                }
            }
            
            const unit = selectedOption.getAttribute('data-unit') || '';
            const rate = selectedOption.getAttribute('data-rate') || 0;
            const longDesc = selectedOption.getAttribute('data-long') || '';
            
            document.getElementById(`unit-${counter}`).value = unit;
            document.getElementById(`rate-${counter}`).value = rate;
            document.querySelector(`input[name="items[${counter}][long_description]"]`).value = longDesc;
            
            calculateTotal();
        }

        // Check if product already exists in order
        function productExistsInOrder(productDescription) {
            const rows = document.querySelectorAll('#order-items-body tr');
            for (let row of rows) {
                const select = row.querySelector('select.product-select');
                if (select) {
                    const selectedOption = select.options[select.selectedIndex];
                    const value = selectedOption ? selectedOption.value : '';
                    if (value === productDescription) {
                        return row;
                    }
                }
            }
            return null;
        }

        // Remove row from order
        function removeRow(id) {
            const row = document.getElementById(`order-row-${id}`);
            if (row) {
                row.remove();
                calculateTotal();
            }
        }

        // Copy previous order to new order (auto-populate on page load)
        function copyPreviousOrder() {
            if (!previousOrderData || previousOrderData.length === 0) {
                return;
            }
            
            const tbody = document.getElementById('order-items-body');
            tbody.innerHTML = ''; // Clear existing items
            
            previousOrderData.forEach(item => {
                const row = createOrderRow(
                    item.description,
                    item.unit || '',
                    Math.floor(item.qty), // Convert to integer
                    item.rate,
                    item.long_description || ''
                );
                tbody.appendChild(row);
            });
            
            calculateTotal();
        }

        // Add product from catalog to order
        function addProductToOrder(product) {
            const tbody = document.getElementById('order-items-body');
            const row = createOrderRow(
                product.description,
                product.unit || '',
                1,
                product.rate,
                product.long_description || ''
            );
            tbody.appendChild(row);
            calculateTotal();
        }

        // Calculate order total
        function calculateTotal() {
            let total = 0;
            const rows = document.querySelectorAll('#order-items-body tr');
            
            rows.forEach(row => {
                const qtyInput = row.querySelector('input[name*="[qty]"]');
                const rateInput = row.querySelector('input[name*="[rate]"]');
                
                if (qtyInput && rateInput) {
                    const qty = parseFloat(qtyInput.value) || 0;
                    const rate = parseFloat(rateInput.value) || 0;
                    total += qty * rate;
                }
            });
            
            document.getElementById('order-total').textContent = total.toFixed(2);
        }

        // Save new order
        function saveNewOrder(event) {
            if (event) {
                event.preventDefault();
            }
            
            const form = document.getElementById('new-order-form');
            
            // Check if there are any items
            const tbody = document.getElementById('order-items-body');
            if (tbody.children.length === 0) {
                alert('<?php echo _l('please_add_items_to_order'); ?>');
                return;
            }
            
            // Collect and group items by product
            const groupedItems = {};
            const rows = document.querySelectorAll('#order-items-body tr');
            let hasEmptyProduct = false;
            
            rows.forEach(row => {
                const select = row.querySelector('select.product-select');
                const qtyInput = row.querySelector('input[name*="[qty]"]');
                const rateInput = row.querySelector('input[name*="[rate]"]');
                const unitInput = row.querySelector('input[name*="[unit]"]');
                const longDescInput = row.querySelector('input[name*="[long_description]"]');
                
                if (!select) {
                    return;
                }
                
                const selectedOption = select.options[select.selectedIndex];
                const selectedValue = selectedOption ? selectedOption.value : '';
                
                if (!selectedValue || selectedValue.trim() === '') {
                    hasEmptyProduct = true;
                    return;
                }
                
                const productKey = selectedValue;
                const qty = parseInt(qtyInput.value) || 0;
                const rate = parseFloat(rateInput.value) || 0;
                const unit = unitInput ? unitInput.value : '';
                const longDesc = longDescInput ? longDescInput.value : '';
                
                if (qty > 0) {
                    if (groupedItems[productKey]) {
                        groupedItems[productKey].qty += qty;
                    } else {
                        groupedItems[productKey] = {
                            description: selectedValue,
                            qty: qty,
                            rate: rate,
                            unit: unit,
                            long_description: longDesc
                        };
                    }
                }
            });
            
            if (hasEmptyProduct) {
                alert('<?php echo _l('please_select_all_products'); ?>');
                return;
            }
            
            if (Object.keys(groupedItems).length === 0) {
                alert('<?php echo _l('please_add_items_to_order'); ?>');
                return;
            }
            
            // Prepare data object
            const data = {
                items: Object.values(groupedItems).map((item, index) => ({
                    description: item.description,
                    qty: item.qty,
                    rate: item.rate,
                    unit: item.unit,
                    long_description: item.long_description,
                    order: index + 1  // Add order field for sorting
                }))
            };
            
            // Get the button
            const btn = document.querySelector('button[onclick*="saveNewOrder"]');
            if (!btn) {
                alert('Button not found');
                return;
            }
            
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> <?php echo _l('saving'); ?>...';
            
            // Use jQuery AJAX (Perfex has jQuery loaded)
            $.post('<?php echo site_url('clients/save_new_order'); ?>', data)
                .done(function(response) {

                    console.log(response);

                    if (typeof response === 'string') {
                        try { response = JSON.parse(response); } catch (e) {}
                    }

                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    
                    if (response.success) {
                        alert(response.message);
                        window.location.reload();
                    } else {
                        alert(response.message || '<?php echo _l('order_save_failed'); ?>');
                    }
                })
                .fail(function(xhr, status, error) {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    alert('<?php echo _l('error_occurred'); ?>');
                    console.error('Error:', error);
                });
        }

        // Auto-populate with previous order on page load
        document.addEventListener('DOMContentLoaded', function() {
            if (previousOrderData && previousOrderData.length > 0) {
                copyPreviousOrder();
            } else {
                addNewRow(); // Add one empty row if no previous order
            }
        });
    </script>

    <style>
        .product-card {
            transition: all 0.3s ease;
            height: 100%;
            margin-bottom: 15px;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        #new-order-table input.form-control,
        #new-order-table select.form-control {
            padding: 5px 8px;
            font-size: 13px;
        }

        .mtop20 {
            margin-top: 20px;
        }

        .mtop15 {
            margin-top: 15px;
        }

        .product-select {
            width: 100%;
        }
    </style>