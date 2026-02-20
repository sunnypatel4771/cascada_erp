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
                                        <th width="50"><?php echo _l('image'); ?></th>
                                        <th><?php echo _l('product'); ?></th>
                                        <th><?php echo _l('maduracion'); ?></th>
                                        <th><?php echo _l('unit'); ?></th>
                                        <th class="text-center"><?php echo _l('quantity'); ?></th>
                                        <th class="text-right"><?php echo _l('rate'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($latest_order_items as $item):
                                        // Get ripeness from dedicated field, fallback to parsing long_description for old data
                                        $ripeness = '';
                                        if (isset($item->ripeness) && $item->ripeness !== '' && $item->ripeness !== null) {
                                            $ripeness = $item->ripeness;
                                        } elseif (!empty($item->long_description) && strpos($item->long_description, 'Maduración:') !== false) {
                                            preg_match('/Maduración:\s*(Maduro|Verde)/i', $item->long_description, $matches);
                                            $ripeness = isset($matches[1]) ? $matches[1] : '';
                                        }
                                    ?>
                                    <tr>
                                        <td class="text-center">
                                            <img src="<?php echo isset($item->image_url) ? $item->image_url : base_url('modules/warehouse/uploads/nul_image.jpg'); ?>"
                                                 alt="<?php echo htmlspecialchars($item->description); ?>"
                                                 class="table-product-image">
                                        </td>
                                        <td><?php echo $item->description; ?></td>
                                        <td><?php echo $ripeness ? _l('maduracion_' . strtolower($ripeness)) : '-'; ?></td>
                                        <td><?php echo $item->unit; ?></td>
                                        <td class="text-center"><?php echo (int)$item->qty; ?></td>
                                        <td class="text-right"><?php echo $currency_symbol . number_format($item->rate, 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="active">
                                        <td colspan="5" class="text-right"><strong><?php echo _l('order_total'); ?>:</strong></td>
                                        <td class="text-right"><strong><?php echo $currency_symbol . number_format($previous_order_total, 2); ?></strong></td>
                                    </tr>
                                </tfoot>
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
                                        <th width="50"><?php echo _l('image'); ?></th>
                                        <th width="180"><?php echo _l('product'); ?></th>
                                        <th width="90"><?php echo _l('maduracion'); ?></th>
                                        <th width="70"><?php echo _l('unit'); ?></th>
                                        <th width="60"><?php echo _l('quantity'); ?></th>
                                        <th width="90" class="text-right"><?php echo _l('rate'); ?></th>
                                        <th width="40"><?php echo _l('action'); ?></th>
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
                                        <?php echo $currency_symbol; ?><span id="order-total">0.00</span>
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
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="panel-title" style="padding-top: 5px;"><?php echo _l('more_products'); ?></h4>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" id="product-search" class="form-control"
                                           placeholder="<?php echo _l('search_products'); ?>"
                                           onkeyup="filterProducts()">
                                    <span class="input-group-btn">
                                        <button class="btn btn-default" type="button" onclick="clearSearch()">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="panel-body">
                        <!-- Products Grid Container -->
                        <div class="row" id="products-catalog">
                            <!-- Products will be rendered by JavaScript -->
                        </div>

                        <!-- No Results Message -->
                        <div id="no-products-found" class="text-center" style="display: none;">
                            <p class="text-muted"><i class="fa fa-search"></i> <?php echo _l('no_products_found'); ?></p>
                        </div>

                        <!-- Pagination Controls -->
                        <div class="row mtop15">
                            <div class="col-md-12 text-center">
                                <nav id="products-pagination">
                                    <ul class="pagination" id="pagination-list">
                                        <!-- Pagination buttons rendered by JavaScript -->
                                    </ul>
                                </nav>
                                <p class="text-muted" id="products-info"></p>
                            </div>
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

        // Default image URL
        const defaultImageUrl = '<?php echo base_url("modules/warehouse/uploads/nul_image.jpg"); ?>';

        // Currency symbol
        const currencySymbol = '<?php echo $currency_symbol; ?>';

        // Create product options HTML
        function getProductOptionsHTML(selectedDescription = '') {
            let options = '<option value="" data-image="' + defaultImageUrl + '">-- Select Product --</option>';
            allProducts.forEach(product => {
                const selected = product.description === selectedDescription ? 'selected' : '';
                const imageUrl = product.image_url || defaultImageUrl;
                options += `<option value="${product.description}"
                                data-unit="${product.unit || ''}"
                                data-rate="${product.rate}"
                                data-long="${product.long_description || ''}"
                                data-image="${imageUrl}"
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
        function createOrderRow(description, unit, qty, rate, longDescription = '', imageUrl = '', maduracion = '') {
            orderItemsCounter++;
            const row = document.createElement('tr');
            row.id = `order-row-${orderItemsCounter}`;

            // Get image URL from product if not provided
            let productImage = imageUrl || defaultImageUrl;
            if (!imageUrl && description) {
                const product = allProducts.find(p => p.description === description);
                if (product && product.image_url) {
                    productImage = product.image_url;
                }
            }

            // Extract maduración from long_description if not provided
            let selectedMaduracion = maduracion;
            if (!selectedMaduracion && longDescription) {
                const match = longDescription.match(/Maduración:\s*(Maduro|Verde)/i);
                if (match) {
                    selectedMaduracion = match[1];
                }
            }

            row.innerHTML = `
                <td class="text-center">
                    <img src="${productImage}"
                         alt="${description}"
                         class="table-product-image"
                         id="img-${orderItemsCounter}">
                </td>
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
                    <select class="form-control input-sm maduracion-select"
                            name="items[${orderItemsCounter}][maduracion]"
                            required>
                        <option value="">-- <?php echo _l('select'); ?> --</option>
                        <option value="Maduro" ${selectedMaduracion === 'Maduro' ? 'selected' : ''}><?php echo _l('maduracion_maduro'); ?></option>
                        <option value="Verde" ${selectedMaduracion === 'Verde' ? 'selected' : ''}><?php echo _l('maduracion_verde'); ?></option>
                    </select>
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
                <td class="text-right">
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
            const imageUrl = selectedOption.getAttribute('data-image') || defaultImageUrl;

            document.getElementById(`unit-${counter}`).value = unit;
            document.getElementById(`rate-${counter}`).value = rate;
            document.querySelector(`input[name="items[${counter}][long_description]"]`).value = longDesc;

            // Update the product image
            const imgElement = document.getElementById(`img-${counter}`);
            if (imgElement) {
                imgElement.src = imageUrl;
                imgElement.alt = selectedProduct;
            }

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
                // Use dedicated ripeness field if available
                const ripeness = item.ripeness || '';
                const row = createOrderRow(
                    item.description,
                    item.unit || '',
                    Math.floor(item.qty), // Convert to integer
                    item.rate,
                    item.long_description || '',
                    item.image_url || '',
                    ripeness
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
                product.long_description || '',
                product.image_url || ''
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
                const maduracionSelect = row.querySelector('select.maduracion-select');

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
                const maduracion = maduracionSelect ? maduracionSelect.value : '';

                if (qty > 0) {
                    if (groupedItems[productKey]) {
                        groupedItems[productKey].qty += qty;
                    } else {
                        groupedItems[productKey] = {
                            description: selectedValue,
                            qty: qty,
                            rate: rate,
                            unit: unit,
                            long_description: longDesc,
                            maduracion: maduracion
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
                    maduracion: item.maduracion,
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

            // Initialize products catalog with pagination
            initProductsCatalog();
        });

        // =============================================
        // Products Catalog - Search & Pagination
        // =============================================
        const PRODUCTS_PER_PAGE = 12;
        let currentPage = 1;
        let filteredProducts = [];

        // Initialize products catalog
        function initProductsCatalog() {
            filteredProducts = [...allProducts];
            renderProducts();
        }

        // Filter products based on search term
        function filterProducts() {
            const searchTerm = document.getElementById('product-search').value.toLowerCase().trim();

            if (searchTerm === '') {
                filteredProducts = [...allProducts];
            } else {
                filteredProducts = allProducts.filter(product => {
                    const description = (product.description || '').toLowerCase();
                    const unit = (product.unit || '').toLowerCase();
                    const groupName = (product.group_name || '').toLowerCase();
                    return description.includes(searchTerm) ||
                           unit.includes(searchTerm) ||
                           groupName.includes(searchTerm);
                });
            }

            currentPage = 1; // Reset to first page when searching
            renderProducts();
        }

        // Clear search and show all products
        function clearSearch() {
            document.getElementById('product-search').value = '';
            filteredProducts = [...allProducts];
            currentPage = 1;
            renderProducts();
        }

        // Render products for current page
        function renderProducts() {
            const container = document.getElementById('products-catalog');
            const noResultsDiv = document.getElementById('no-products-found');
            const paginationNav = document.getElementById('products-pagination');
            const productsInfo = document.getElementById('products-info');

            // Calculate pagination
            const totalProducts = filteredProducts.length;
            const totalPages = Math.ceil(totalProducts / PRODUCTS_PER_PAGE);
            const startIndex = (currentPage - 1) * PRODUCTS_PER_PAGE;
            const endIndex = Math.min(startIndex + PRODUCTS_PER_PAGE, totalProducts);
            const productsToShow = filteredProducts.slice(startIndex, endIndex);

            // Clear container
            container.innerHTML = '';

            // Show/hide no results message
            if (totalProducts === 0) {
                noResultsDiv.style.display = 'block';
                paginationNav.style.display = 'none';
                productsInfo.textContent = '';
                return;
            }

            noResultsDiv.style.display = 'none';
            paginationNav.style.display = totalPages > 1 ? 'block' : 'none';

            // Render product cards
            productsToShow.forEach(product => {
                const productCard = createProductCard(product);
                container.innerHTML += productCard;
            });

            // Update products info
            productsInfo.textContent = `<?php echo _l('showing'); ?> ${startIndex + 1}-${endIndex} <?php echo _l('of'); ?> ${totalProducts} <?php echo _l('products'); ?>`;

            // Render pagination
            renderPagination(totalPages);
        }

        // Create product card HTML
        function createProductCard(product) {
            const imageUrl = product.image_url || defaultImageUrl;
            const productJson = JSON.stringify(product).replace(/'/g, "\\'").replace(/"/g, '&quot;');

            return `
                <div class="col-md-3 col-sm-6 col-xs-12 product-item">
                    <div class="panel panel-default product-card" style="cursor: pointer;"
                        onclick='addProductToOrder(${JSON.stringify(product)})'>
                        <div class="panel-body text-center">
                            <div class="product-image-container">
                                <img src="${imageUrl}"
                                     alt="${escapeHtml(product.description)}"
                                     class="product-image"
                                     onerror="this.src='${defaultImageUrl}'">
                            </div>
                            <h5><strong>${escapeHtml(product.description)}</strong></h5>
                            <p class="text-muted">${product.unit ? escapeHtml(product.unit) : 'N/A'}</p>
                            <p><strong>${currencySymbol}${parseFloat(product.rate).toFixed(2)}</strong></p>
                            <button type="button" class="btn btn-sm btn-success">
                                <i class="fa fa-plus"></i> <?php echo _l('add_to_order'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Render pagination controls
        function renderPagination(totalPages) {
            const paginationList = document.getElementById('pagination-list');
            paginationList.innerHTML = '';

            // Previous button
            const prevDisabled = currentPage === 1 ? 'disabled' : '';
            paginationList.innerHTML += `
                <li class="${prevDisabled}">
                    <a href="#" onclick="goToPage(${currentPage - 1}); return false;" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            `;

            // Page numbers
            const maxVisiblePages = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

            if (endPage - startPage + 1 < maxVisiblePages) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }

            // First page and ellipsis
            if (startPage > 1) {
                paginationList.innerHTML += `
                    <li><a href="#" onclick="goToPage(1); return false;">1</a></li>
                `;
                if (startPage > 2) {
                    paginationList.innerHTML += `<li class="disabled"><span>...</span></li>`;
                }
            }

            // Page numbers
            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === currentPage ? 'active' : '';
                paginationList.innerHTML += `
                    <li class="${activeClass}">
                        <a href="#" onclick="goToPage(${i}); return false;">${i}</a>
                    </li>
                `;
            }

            // Last page and ellipsis
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    paginationList.innerHTML += `<li class="disabled"><span>...</span></li>`;
                }
                paginationList.innerHTML += `
                    <li><a href="#" onclick="goToPage(${totalPages}); return false;">${totalPages}</a></li>
                `;
            }

            // Next button
            const nextDisabled = currentPage === totalPages ? 'disabled' : '';
            paginationList.innerHTML += `
                <li class="${nextDisabled}">
                    <a href="#" onclick="goToPage(${currentPage + 1}); return false;" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            `;
        }

        // Go to specific page
        function goToPage(page) {
            const totalPages = Math.ceil(filteredProducts.length / PRODUCTS_PER_PAGE);
            if (page < 1 || page > totalPages) return;

            currentPage = page;
            renderProducts();

            // Scroll to products section
            document.getElementById('products-catalog').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
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

        .product-image-container {
            width: 100%;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            overflow: hidden;
        }

        .product-image {
            max-width: 100%;
            max-height: 120px;
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 4px;
        }

        #new-order-table {
            /*table-layout: fixed;*/
            width: 100%;
        }

        #new-order-table input.form-control,
        #new-order-table select.form-control {
            padding: 5px 8px;
            font-size: 13px;
            width: 100%;
        }

        #new-order-table input[type="number"] {
            -moz-appearance: textfield;
        }

        #new-order-table input[type="number"]::-webkit-outer-spin-button,
        #new-order-table input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
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

        .table-product-image {
            width: 50px;
            height: 50px;
            object-fit: contain;
            border-radius: 4px;
            border: 1px solid #ddd;
            background-color: #fff;
        }

        /* Search box styles */
        #product-search {
            border-radius: 4px 0 0 4px;
        }

        #product-search:focus {
            border-color: #66afe9;
            box-shadow: inset 0 1px 1px rgba(0,0,0,.075), 0 0 8px rgba(102, 175, 233, 0.6);
        }

        /* Pagination styles */
        #products-pagination {
            margin-top: 10px;
        }

        #products-pagination .pagination {
            margin: 0;
        }

        #products-pagination .pagination > li > a,
        #products-pagination .pagination > li > span {
            padding: 8px 14px;
        }

        #products-pagination .pagination > .active > a {
            background-color: #84c529;
            border-color: #84c529;
        }

        #products-pagination .pagination > .active > a:hover {
            background-color: #6ba021;
            border-color: #6ba021;
        }

        #products-info {
            margin-top: 10px;
            font-size: 13px;
        }

        /* Product item animation */
        .product-item {
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* No products found message */
        #no-products-found {
            padding: 40px 20px;
        }

        #no-products-found .fa-search {
            font-size: 24px;
            margin-right: 10px;
            color: #ccc;
        }
    </style>