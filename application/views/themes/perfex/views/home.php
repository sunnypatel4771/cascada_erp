<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="row">
    <div class="col-md-12 section-client-dashboard">
        <?php hooks()->do_action('client_area_after_project_overview'); ?>

        <div class="row mtop20">
            <div class="col-md-12">
                <h3><?php echo _l('order_management'); ?></h3>
            </div>
        </div>

        <div class="row">
            <!-- Previous Order (Left Side) — full width until lg so tables are not squeezed -->
            <div class="col-md-12 col-lg-6">
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
            <div class="col-md-12 col-lg-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title"><?php echo _l('new_order'); ?></h4>
                    </div>
                    <div class="panel-body">
                        <form id="new-order-form">
                            <div class="new-order-table-wrapper">
                                <table class="table table-bordered" id="new-order-table">
                                    <colgroup>
                                        <col class="new-order-col-image">
                                        <col class="new-order-col-product">
                                        <col class="new-order-col-unit">
                                        <col class="new-order-col-equivalencias">
                                        <col class="new-order-col-maduracion">
                                        <col class="new-order-col-qty">
                                        <col class="new-order-col-rate">
                                        <col class="new-order-col-action">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th class="new-order-col-image"><?php echo _l('image'); ?></th>
                                            <th class="new-order-col-product"><?php echo _l('product'); ?></th>
                                            <th class="new-order-col-unit"><?php echo _l('unit'); ?></th>
                                            <th class="new-order-col-equivalencias"><?php echo _l('equivalencias'); ?></th>
                                            <th class="new-order-col-maduracion"><?php echo _l('maduracion'); ?></th>
                                            <th class="new-order-col-qty"><?php echo _l('quantity'); ?></th>
                                            <th class="new-order-col-rate text-right"><?php echo _l('rate'); ?></th>
                                            <th class="new-order-col-action"><?php echo _l('action'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="order-items-body">
                                        <!-- Order items will be added here dynamically -->
                                    </tbody>
                                </table>
                            </div>

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
                                    <table class="table table-condensed pull-right" style="width: auto;">
                                        <tr class="active">
                                            <td class="text-right"><strong><?php echo _l('order_total'); ?>:</strong></td>
                                            <td class="text-right" style="min-width: 100px;"><strong><?php echo $currency_symbol; ?><span id="order-total">0.00</span></strong></td>
                                        </tr>
                                    </table>
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

        <?php hooks()->do_action('client_area_dashboard_end'); ?>
    </div>

    <script>
        let orderItemsCounter = 0;
        let previousOrderData = <?php echo isset($latest_order_items) ? json_encode($latest_order_items) : '[]'; ?>;
        let allProducts = <?php echo isset($all_products) ? json_encode($all_products) : '[]'; ?>;

        // Default image URL
        const defaultImageUrl = '<?php echo base_url("modules/warehouse/uploads/nul_image.jpg"); ?>';

        // Currency symbol
        const currencySymbol = '<?php echo $currency_symbol; ?>';

        // Customer markup percentage (applied to purchase_price to get selling price)
        const customerMarkupPercent = <?php echo $customer_markup_percent; ?>;

        // Calculate selling price with markup: purchase_price * (1 + markup/100)
        function calculateSellingPrice(purchasePrice) {
            const price = parseFloat(purchasePrice) || 0;
            if (price <= 0 || customerMarkupPercent <= 0) {
                return price; // Return original if no valid price or markup
            }
            return price * (1 + customerMarkupPercent / 100);
        }

        function escapeHtmlAttr(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;');
        }

        function productNeedsEquivalenciasSlot(product) {
            if (!product || !Array.isArray(product.equivalences) || product.equivalences.length === 0) {
                return false;
            }
            // equivalences entries are objects {unit_name, conversion_factor, ...}
            const base = (product.unit || '').trim();
            const set = new Set();
            if (base) {
                set.add(base.toLowerCase());
            }
            product.equivalences.forEach((e) => {
                const name = e && typeof e === 'object' ? String(e.unit_name || '').trim() : String(e || '').trim();
                if (name) {
                    set.add(name.toLowerCase());
                }
            });
            return set.size >= 2;
        }

        /** Bootstrap-select for #order-items-body — menu must attach to body or it is clipped in narrow table cells */
        function initOrderTableSelects() {
            if (typeof $ === 'undefined' || !$.fn.selectpicker) {
                return;
            }
            const pickerOpts = { showSubtext: true, container: 'body' };
            $('#order-items-body select').each(function () {
                const $s = $(this);
                if ($s.parent().hasClass('bootstrap-select')) {
                    const api = $s.data('selectpicker');
                    const cont = api && api.options && api.options.container;
                    if (cont !== 'body') {
                        $s.selectpicker('destroy');
                        $s.selectpicker(pickerOpts);
                    } else {
                        $s.selectpicker('refresh');
                    }
                } else {
                    $s.selectpicker(pickerOpts);
                }
            });
        }

        function recomputeColumnVisibility() {
            const table = document.getElementById('new-order-table');
            if (!table) {
                return;
            }

            let anyMad = false;
            document.querySelectorAll('#order-items-body tr').forEach((row) => {
                const sel = row.querySelector('select.product-select');
                const opt = sel && sel.options[sel.selectedIndex];
                if (opt && opt.value && (parseInt(opt.getAttribute('data-has-maduracion'), 10) === 1)) {
                    anyMad = true;
                }
            });

            const madDisplay = anyMad ? '' : 'none';
            const madCol = table.querySelector('col.new-order-col-maduracion');
            if (madCol) {
                madCol.style.display = anyMad ? '' : 'none';
            }
            const madTh = table.querySelector('th.new-order-col-maduracion');
            if (madTh) {
                madTh.style.display = madDisplay;
            }
            table.querySelectorAll('td.new-order-col-maduracion').forEach((td) => {
                td.style.display = madDisplay;
            });

            let anyEq = false;
            document.querySelectorAll('#order-items-body tr').forEach((row) => {
                const sel = row.querySelector('select.product-select');
                const opt = sel && sel.options[sel.selectedIndex];
                if (!opt || !opt.value) {
                    return;
                }
                const p = allProducts.find((x) => x.description === opt.value);
                if (p && productNeedsEquivalenciasSlot(p)) {
                    anyEq = true;
                }
            });

            const eqDisplay = anyEq ? '' : 'none';
            const eqCol = table.querySelector('col.new-order-col-equivalencias');
            if (eqCol) {
                eqCol.style.display = anyEq ? '' : 'none';
            }
            const eqTh = table.querySelector('th.new-order-col-equivalencias');
            if (eqTh) {
                eqTh.style.display = eqDisplay;
            }
            table.querySelectorAll('td.new-order-col-equivalencias').forEach((td) => {
                td.style.display = eqDisplay;
            });

            initOrderTableSelects();
        }

        function syncRowAuxiliaryControls(counter) {
            const row = document.getElementById(`order-row-${counter}`);
            if (!row) {
                return;
            }
            const select = row.querySelector('select.product-select');
            const selectedOption = select && select.options[select.selectedIndex];
            const selectedProduct = selectedOption ? selectedOption.value : '';
            if (!selectedProduct) {
                updateMaduracionVisibility(counter, false);
                updateEquivalenciasVisibility(counter, null);
                return;
            }
            const hasMad = parseInt(selectedOption.getAttribute('data-has-maduracion'), 10) || 0;
            updateMaduracionVisibility(counter, hasMad === 1);
            const product = allProducts.find((p) => p.description === selectedProduct);
            updateEquivalenciasVisibility(counter, product || null);
        }

        function updateEquivalenciasVisibility(counter, product) {
            const cell = document.getElementById(`equivalencias-cell-${counter}`);
            const select = document.getElementById(`equivalencias-select-${counter}`);
            const unitInput = document.getElementById(`unit-${counter}`);
            const factorInput = document.getElementById(`equivalencia-factor-${counter}`);
            if (!cell || !select) {
                return;
            }
            const wrap = cell.querySelector('.equivalencias-select-wrap');
            const placeholder = cell.querySelector('.equivalencias-placeholder');
            if (!wrap || !placeholder) {
                return;
            }

            if (!product || !productNeedsEquivalenciasSlot(product)) {
                wrap.style.display = 'none';
                placeholder.style.display = '';
                select.innerHTML = '';
                select.removeAttribute('required');
                if (factorInput) { factorInput.value = '1'; }
                return;
            }

            const baseUnit = (product.unit || '').trim();

            // Build ordered options: base unit first (factor=1), then each equivalencia.
            const options = [];
            const seenLower = new Set();
            if (baseUnit) {
                options.push({ unit_name: baseUnit, conversion_factor: 1 });
                seenLower.add(baseUnit.toLowerCase());
            }
            product.equivalences.forEach((e) => {
                if (!e) { return; }
                const name = typeof e === 'object' ? String(e.unit_name || '').trim() : String(e).trim();
                const factor = typeof e === 'object' ? (parseFloat(e.conversion_factor) || 1) : 1;
                if (name && !seenLower.has(name.toLowerCase())) {
                    options.push({ unit_name: name, conversion_factor: factor });
                    seenLower.add(name.toLowerCase());
                }
            });

            if (options.length < 2) {
                wrap.style.display = 'none';
                placeholder.style.display = '';
                select.innerHTML = '';
                select.removeAttribute('required');
                if (factorInput) { factorInput.value = '1'; }
                return;
            }

            wrap.style.display = '';
            placeholder.style.display = 'none';

            let currentUnit = unitInput && unitInput.value ? unitInput.value.trim() : '';
            const currentLower = currentUnit.toLowerCase();
            const matchedOption = options.find((o) => o.unit_name.toLowerCase() === currentLower);
            if (!matchedOption) {
                currentUnit = options[0].unit_name;
            }

            select.innerHTML = options.map((o) => {
                const esc = escapeHtmlAttr(o.unit_name);
                const label = escapeHtml(o.unit_name);
                const factorEsc = escapeHtmlAttr(String(o.conversion_factor));
                const sel = o.unit_name.toLowerCase() === currentUnit.toLowerCase() ? ' selected' : '';
                return `<option value="${esc}" data-factor="${factorEsc}"${sel}>${label}</option>`;
            }).join('');

            select.setAttribute('required', 'required');
            if (unitInput) {
                unitInput.value = currentUnit;
            }

            // Sync factor hidden input with the now-selected option.
            const selOpt = select.options[select.selectedIndex];
            if (factorInput && selOpt) {
                factorInput.value = selOpt.getAttribute('data-factor') || '1';
            }
        }

        function onEquivalenciaChange(counter) {
            const select = document.getElementById(`equivalencias-select-${counter}`);
            const unitInput = document.getElementById(`unit-${counter}`);
            const factorInput = document.getElementById(`equivalencia-factor-${counter}`);
            if (select) {
                const selOpt = select.options[select.selectedIndex];
                if (unitInput) {
                    unitInput.value = select.value || '';
                }
                if (factorInput && selOpt) {
                    factorInput.value = selOpt.getAttribute('data-factor') || '1';
                }
            }
            calculateTotal();
        }

        // Create product options HTML
        function getProductOptionsHTML(selectedDescription = '') {
            let options = '<option value="" data-image="' + defaultImageUrl + '" data-purchase-price="0" data-has-maduracion="0" data-has-equivalencias="0">-- Select Product --</option>';
            allProducts.forEach(product => {
                const selected = product.description === selectedDescription ? 'selected' : '';
                const imageUrl = product.image_url || defaultImageUrl;
                const purchasePrice = parseFloat(product.purchase_price) || 0;
                const sellingPrice = calculateSellingPrice(purchasePrice);
                // Use calculated selling price, fallback to rate if no purchase_price
                const finalRate = purchasePrice > 0 ? sellingPrice : (parseFloat(product.rate) || 0);
                // Check if product has maduración (ripeness) options
                const hasMaduracion = parseInt(product.has_maduracion) || 0;
                const hasEquivalencias = productNeedsEquivalenciasSlot(product) ? 1 : 0;
                options += `<option value="${product.description}"
                                data-unit="${product.unit || ''}"
                                data-rate="${finalRate.toFixed(2)}"
                                data-purchase-price="${purchasePrice}"
                                data-long="${product.long_description || ''}"
                                data-image="${imageUrl}"
                                data-has-maduracion="${hasMaduracion}"
                                data-has-equivalencias="${hasEquivalencias}"
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
            syncRowAuxiliaryControls(orderItemsCounter);
            recomputeColumnVisibility();
            calculateTotal();
        }

        // Create order row HTML with dropdown
        function createOrderRow(description, unit, qty, rate, longDescription = '', imageUrl = '', maduracion = '', hasMaduracion = null) {
            orderItemsCounter++;
            const row = document.createElement('tr');
            row.id = `order-row-${orderItemsCounter}`;

            // Get product details if not provided
            let productImage = imageUrl || defaultImageUrl;
            let productHasMaduracion = hasMaduracion;

            if (description) {
                const product = allProducts.find(p => p.description === description);
                if (product) {
                    if (!imageUrl && product.image_url) {
                        productImage = product.image_url;
                    }
                    // Get has_maduracion from product if not explicitly provided
                    if (productHasMaduracion === null) {
                        productHasMaduracion = parseInt(product.has_maduracion) || 0;
                    }
                }
            }

            // Default to hidden if no product selected
            if (productHasMaduracion === null) {
                productHasMaduracion = 0;
            }

            // Extract maduración from long_description if not provided
            let selectedMaduracion = maduracion;
            if (!selectedMaduracion && longDescription) {
                const match = longDescription.match(/Maduración:\s*(Maduro|Verde)/i);
                if (match) {
                    selectedMaduracion = match[1];
                }
            }

            const maduracionWrapStyle = productHasMaduracion ? '' : 'display: none;';
            const maduracionPlaceholderStyle = productHasMaduracion ? 'display: none;' : '';
            const maduracionRequired = productHasMaduracion ? 'required' : '';

            row.innerHTML = `
                <td class="text-center new-order-col-image">
                    <div class="new-order-img-wrap">
                        <img src="${productImage}"
                             alt="${description}"
                             class="table-product-image"
                             id="img-${orderItemsCounter}">
                    </div>
                </td>
                <td class="new-order-col-product">
                    <select class="form-control input-sm product-select"
                            name="items[${orderItemsCounter}][description]"
                            required
                            data-container="body"
                            data-dropup-auto="false"
                            onchange="updateProductDetails(${orderItemsCounter})"
                            data-counter="${orderItemsCounter}">
                        ${getProductOptionsHTML(description)}
                    </select>
                    <input type="hidden" name="items[${orderItemsCounter}][long_description]" value="${longDescription}">
                </td>
                <td class="new-order-col-unit">
                    <input type="text" class="form-control input-sm"
                        id="unit-${orderItemsCounter}"
                        name="items[${orderItemsCounter}][unit]"
                        value="${unit}"
                        readonly
                        style="background-color: #f5f5f5;">
                </td>
                <td class="equivalencias-cell new-order-col-equivalencias" id="equivalencias-cell-${orderItemsCounter}">
                    <input type="hidden"
                           id="equivalencia-factor-${orderItemsCounter}"
                           name="items[${orderItemsCounter}][equivalencia_factor]"
                           value="1">
                    <div class="equivalencias-select-wrap" style="display: none;">
                        <select class="form-control input-sm equivalencias-select"
                                name="items[${orderItemsCounter}][equivalencia_unit]"
                                id="equivalencias-select-${orderItemsCounter}"
                                data-container="body"
                                data-dropup-auto="false"
                                onchange="onEquivalenciaChange(${orderItemsCounter})">
                        </select>
                    </div>
                    <span class="equivalencias-placeholder text-muted">&mdash;</span>
                </td>
                <td class="maduracion-cell new-order-col-maduracion" id="maduracion-cell-${orderItemsCounter}">
                    <div class="maduracion-select-wrap" style="${maduracionWrapStyle}">
                    <select class="form-control input-sm maduracion-select"
                            name="items[${orderItemsCounter}][maduracion]"
                            id="maduracion-select-${orderItemsCounter}"
                            data-container="body"
                            data-dropup-auto="false"
                            ${maduracionRequired}>
                        <option value="">-- <?php echo _l('select'); ?> --</option>
                        <option value="Maduro" ${selectedMaduracion === 'Maduro' ? 'selected' : ''}><?php echo _l('maduracion_maduro'); ?></option>
                        <option value="Verde" ${selectedMaduracion === 'Verde' ? 'selected' : ''}><?php echo _l('maduracion_verde'); ?></option>
                    </select>
                    </div>
                    <span class="maduracion-placeholder text-muted" style="${maduracionPlaceholderStyle}">&mdash;</span>
                </td>
                <td class="new-order-col-qty">
                    <input type="number" class="form-control input-sm"
                        name="items[${orderItemsCounter}][qty]"
                        value="${qty}"
                        min="1"
                        step="1"
                        required
                        oninput="calculateTotal()"
                        onchange="calculateTotal()">
                </td>
                <td class="text-right new-order-col-rate">
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
                <td class="text-center new-order-col-action">
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
                            // Hide maduración cell when no product selected
                            updateMaduracionVisibility(counter, false);
                            updateEquivalenciasVisibility(counter, null);
                            recomputeColumnVisibility();
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
            const hasMaduracion = parseInt(selectedOption.getAttribute('data-has-maduracion')) || 0;

            document.getElementById(`unit-${counter}`).value = unit;
            document.getElementById(`rate-${counter}`).value = rate;
            document.querySelector(`input[name="items[${counter}][long_description]"]`).value = longDesc;

            // Update the product image
            const imgElement = document.getElementById(`img-${counter}`);
            if (imgElement) {
                imgElement.src = imageUrl;
                imgElement.alt = selectedProduct;
            }

            // Show/hide maduración / equivalencias based on product flags
            const productRow = allProducts.find((p) => p.description === selectedProduct);
            updateMaduracionVisibility(counter, hasMaduracion === 1);
            updateEquivalenciasVisibility(counter, productRow || null);
            recomputeColumnVisibility();

            calculateTotal();
        }

        // Toggle ripeness control vs placeholder (keep <td> so columns align with <thead>)
        function updateMaduracionVisibility(counter, show) {
            const maduracionCell = document.getElementById(`maduracion-cell-${counter}`);
            const maduracionSelect = document.getElementById(`maduracion-select-${counter}`);
            if (!maduracionCell || !maduracionSelect) {
                return;
            }
            const wrap = maduracionCell.querySelector('.maduracion-select-wrap');
            const placeholder = maduracionCell.querySelector('.maduracion-placeholder');
            if (!wrap || !placeholder) {
                return;
            }
            if (show) {
                wrap.style.display = '';
                placeholder.style.display = 'none';
                maduracionSelect.setAttribute('required', 'required');
            } else {
                wrap.style.display = 'none';
                placeholder.style.display = '';
                maduracionSelect.removeAttribute('required');
                maduracionSelect.value = '';
            }
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
                recomputeColumnVisibility();
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

                // Look up current product to get has_maduracion flag
                // (product settings may have changed since the previous order)
                const product = allProducts.find(p => p.description === item.description);
                const hasMaduracion = product ? (parseInt(product.has_maduracion) || 0) : 0;

                const row = createOrderRow(
                    item.description,
                    item.unit || '',
                    Math.floor(item.qty), // Convert to integer
                    item.rate,
                    item.long_description || '',
                    item.image_url || '',
                    ripeness,
                    hasMaduracion
                );
                tbody.appendChild(row);
                syncRowAuxiliaryControls(orderItemsCounter);
            });

            recomputeColumnVisibility();
            calculateTotal();
        }

        // Add product from catalog to order
        function addProductToOrder(product) {
            const tbody = document.getElementById('order-items-body');
            // Calculate selling price with markup
            const purchasePrice = parseFloat(product.purchase_price) || 0;
            const sellingPrice = purchasePrice > 0 ? calculateSellingPrice(purchasePrice) : (parseFloat(product.rate) || 0);
            // Get has_maduracion flag
            const hasMaduracion = parseInt(product.has_maduracion) || 0;

            const row = createOrderRow(
                product.description,
                product.unit || '',
                1,
                sellingPrice.toFixed(2),
                product.long_description || '',
                product.image_url || '',
                '', // maduracion - empty, user will select
                hasMaduracion
            );
            tbody.appendChild(row);
            syncRowAuxiliaryControls(orderItemsCounter);
            recomputeColumnVisibility();
            calculateTotal();
        }

        // Calculate order total (prices already include markup).
        // When an equivalencia is selected, qty is in the chosen unit;
        // total = qty * conversion_factor * base_unit_rate.
        function calculateTotal() {
            let total = 0;
            const rows = document.querySelectorAll('#order-items-body tr');

            rows.forEach(row => {
                const qtyInput   = row.querySelector('input[name*="[qty]"]');
                const rateInput  = row.querySelector('input[name*="[rate]"]');
                const factorInput = row.querySelector('input[name*="[equivalencia_factor]"]');

                if (qtyInput && rateInput) {
                    const qty    = parseFloat(qtyInput.value) || 0;
                    const rate   = parseFloat(rateInput.value) || 0;
                    const factor = factorInput ? (parseFloat(factorInput.value) || 1) : 1;
                    total += qty * factor * rate;
                }
            });

            // Update display (no discount - markup is already in prices)
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
            let hasMissingMaduracion = false;
            let hasMissingEquivalencia = false;

            rows.forEach(row => {
                const select = row.querySelector('select.product-select');
                const qtyInput = row.querySelector('input[name*="[qty]"]');
                const rateInput = row.querySelector('input[name*="[rate]"]');
                const unitInput = row.querySelector('input[name*="[unit]"]');
                const longDescInput = row.querySelector('input[name*="[long_description]"]');
                const maduracionSelect = row.querySelector('select.maduracion-select');
                const equivalenciasSelect = row.querySelector('select.equivalencias-select');

                if (!select) {
                    return;
                }

                const selectedOption = select.options[select.selectedIndex];
                const selectedValue = selectedOption ? selectedOption.value : '';

                if (!selectedValue || selectedValue.trim() === '') {
                    hasEmptyProduct = true;
                    return;
                }

                // Check if product requires maduración
                const hasMaduracion = parseInt(selectedOption.getAttribute('data-has-maduracion')) || 0;
                const maduracion = maduracionSelect ? maduracionSelect.value : '';

                // Validate maduración is selected for products that require it
                if (hasMaduracion === 1 && (!maduracion || maduracion.trim() === '')) {
                    hasMissingMaduracion = true;
                }

                const factorInputEl = row.querySelector('input[name*="[equivalencia_factor]"]');
                const equivUnit = equivalenciasSelect ? (equivalenciasSelect.value || '').trim() : '';
                const equivFactor = factorInputEl ? (parseFloat(factorInputEl.value) || 1) : 1;

                const portalProduct = allProducts.find((p) => p.description === selectedValue);
                if (portalProduct && productNeedsEquivalenciasSlot(portalProduct)) {
                    if (!equivUnit) {
                        hasMissingEquivalencia = true;
                    }
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
                            long_description: longDesc,
                            maduracion: maduracion,
                            equivalencia_unit: equivUnit,
                            equivalencia_factor: equivFactor
                        };
                    }
                }
            });

            if (hasEmptyProduct) {
                alert('<?php echo _l('please_select_all_products'); ?>');
                return;
            }

            if (hasMissingMaduracion) {
                alert('<?php echo _l('please_select_maduracion'); ?>');
                return;
            }

            if (hasMissingEquivalencia) {
                alert('<?php echo _l('please_select_equivalencias'); ?>');
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
                    equivalencia_unit: item.equivalencia_unit || '',
                    equivalencia_factor: item.equivalencia_factor || 1,
                    order: index + 1  // Add order field for sorting
                }))
            };

            // Include CSRF token to avoid 419 responses when posting from portal.
            if (typeof csrfData !== 'undefined' && csrfData && csrfData.token_name && csrfData.hash) {
                data[csrfData.token_name] = csrfData.hash;
            } else {
                const legacyToken = document.querySelector('input[name="csrf_token_name"]');
                if (legacyToken && legacyToken.value) {
                    data['csrf_token_name'] = legacyToken.value;
                }
            }
            
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
                        // Strip any PHP notices/warnings prepended before the JSON
                        const jsonStart = response.lastIndexOf('{');
                        if (jsonStart > 0) {
                            response = response.substring(jsonStart);
                        }
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

            // Let bootstrap-select menus escape the scroll wrapper (same idea as global.js + .table-responsive)
            if (typeof $ !== 'undefined') {
                $('body').on('shown.bs.dropdown', '.new-order-table-wrapper .btn-group', function () {
                    $(this).closest('.new-order-table-wrapper').css('overflow', 'visible');
                });
                $('body').on('hidden.bs.dropdown', '.new-order-table-wrapper .btn-group', function () {
                    $(this).closest('.new-order-table-wrapper').css('overflow', 'auto');
                });
            }
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
            // Calculate selling price with markup
            const purchasePrice = parseFloat(product.purchase_price) || 0;
            const sellingPrice = purchasePrice > 0 ? calculateSellingPrice(purchasePrice) : (parseFloat(product.rate) || 0);

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
                            <p><strong>${currencySymbol}${sellingPrice.toFixed(2)}</strong></p>
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

        /* Wrapper: reserve space so horizontal scrollbar does not overlap row content */
        .new-order-table-wrapper {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: visible;
            padding-bottom: 22px;
            margin-bottom: 4px;
        }

        #new-order-table {
            table-layout: fixed;
            width: 100%;
            margin-bottom: 0;
            min-width: 640px;
        }

        #new-order-table col.new-order-col-image { width: 76px; }
        #new-order-table col.new-order-col-product { width: 26%; }
        #new-order-table col.new-order-col-unit { width: 9%; }
        #new-order-table col.new-order-col-equivalencias { width: 11%; }
        #new-order-table col.new-order-col-maduracion { width: 168px; }
        #new-order-table col.new-order-col-qty { width: 72px; }
        #new-order-table col.new-order-col-rate { width: 88px; }
        #new-order-table col.new-order-col-action { width: 44px; }

        #new-order-table th,
        #new-order-table td {
            vertical-align: middle;
        }

        #new-order-table th {
            font-size: 12px;
            line-height: 1.25;
            white-space: normal;
            word-break: break-word;
        }

        #new-order-table td:not(.new-order-col-image):not(.new-order-col-maduracion):not(.new-order-col-equivalencias) {
            overflow: hidden;
        }

        #new-order-table td.new-order-col-maduracion,
        #new-order-table td.new-order-col-equivalencias {
            overflow: visible;
        }

        #new-order-table td.new-order-col-product {
            min-width: 0;
        }

        #new-order-table td.new-order-col-maduracion .maduracion-select-wrap {
            min-width: 152px;
        }

        #new-order-table td.new-order-col-maduracion .bootstrap-select > .dropdown-toggle {
            min-width: 152px;
        }

        #new-order-table td.new-order-col-maduracion .bootstrap-select .filter-option-inner-inner {
            white-space: nowrap;
            overflow: visible;
            text-overflow: clip;
            max-width: none;
        }

        /* Dropdown list when appended to body (narrow table cells) */
        body > .bootstrap-select.open > .dropdown-menu {
            min-width: 12rem;
        }

        body > .bootstrap-select.open > .dropdown-menu li a span.text {
            white-space: normal;
        }

        #new-order-table .new-order-img-wrap {
            width: 100%;
            min-height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px 2px;
        }

        #new-order-table .bootstrap-select,
        #new-order-table .bootstrap-select > .dropdown-toggle,
        #new-order-table .bootstrap-select > button {
            width: 100% !important;
            max-width: 100%;
        }

        #new-order-table .bootstrap-select .filter-option-inner-inner {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 100%;
        }

        #new-order-table input.form-control,
        #new-order-table select.form-control {
            padding: 5px 6px;
            font-size: 12px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
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
            width: 48px;
            height: 48px;
            max-width: 100%;
            object-fit: contain;
            border-radius: 4px;
            border: 1px solid #ddd;
            background-color: #fff;
            display: block;
        }

        #new-order-table .table-product-image {
            width: 52px;
            height: 52px;
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