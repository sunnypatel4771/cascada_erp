<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php if (!isset($product) || !is_array($product) || count($product) === 0): ?>
    <div class="alert alert-info">No hay productos para mostrar.</div>
<?php return; endif; ?>

<style>
.ramos-product-grid { margin-top: 10px; }
.ramos-product-card {
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 10px;
    background: #fff;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    flex-wrap: wrap;
}
.ramos-product-info { flex: 1 1 180px; min-width: 0; }
.ramos-product-name { font-weight: 600; font-size: 14px; margin: 0 0 4px 0; }
.ramos-product-price { font-size: 13px; color: #555; margin: 0; }
.ramos-product-unit-price { font-weight: 700; color: #27ae60; font-size: 15px; }
.ramos-product-selectors { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex: 2 1 300px; }
.ramos-product-selectors select { max-width: 140px; }
.ramos-product-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.ramos-qty-input { width: 70px; }
</style>

<div class="ramos-product-grid">
<?php foreach ($product as $p):
    $pid          = (int) $p['id'];
    $pname        = isset($p['name']) ? $p['name'] : '';
    $price        = (float) ($p['price'] ?? 0);
    $wQty         = (int) ($p['w_quantity'] ?? 0);
    $hasMaduracion = (int) ($p['has_maduracion'] ?? 0);
    $equivalences  = is_array($p['equivalences'] ?? null) ? $p['equivalences'] : [];
    $hasVariation  = (int) ($p['has_variation'] ?? 0);
?>
<div class="ramos-product-card" data-product-id="<?php echo $pid; ?>">

    <!-- Product name & base price -->
    <div class="ramos-product-info">
        <p class="ramos-product-name"><?php echo html_escape($pname); ?></p>
        <p class="ramos-product-price ramos-unit-price-display-<?php echo $pid; ?>">
            <?php if ($price > 0): ?>
                <span class="ramos-product-unit-price"><?php echo number_format($price, 2); ?></span>
            <?php endif; ?>
        </p>
    </div>

    <!-- Selectors: maduracion + equivalencia unit -->
    <div class="ramos-product-selectors">

        <?php if ($hasMaduracion): ?>
        <select class="form-control input-sm ramos-maduracion-select" name="maduracion_<?php echo $pid; ?>" style="max-width:120px;">
            <option value="">-- <?php echo _l('maduracion'); ?> --</option>
            <option value="Verde"><?php echo _l('maduracion_verde'); ?></option>
            <option value="Maduro"><?php echo _l('maduracion_maduro'); ?></option>
        </select>
        <?php endif; ?>

        <?php if (!empty($equivalences)): ?>
        <select class="form-control input-sm ramos-unit-select"
                name="unit_<?php echo $pid; ?>"
                data-base-price="<?php echo $price; ?>"
                data-product-id="<?php echo $pid; ?>"
                style="max-width:140px;">
            <option value="" data-factor="1"><?php echo _l('unit'); ?></option>
            <?php foreach ($equivalences as $eq):
                $unitPrice = $price * (float) $eq['conversion_factor'];
            ?>
            <option value="<?php echo html_escape($eq['unit_name']); ?>"
                    data-factor="<?php echo (float) $eq['conversion_factor']; ?>"
                    data-unit-price="<?php echo number_format($unitPrice, 2); ?>">
                <?php echo html_escape($eq['unit_name']); ?>
                (<?php echo number_format($unitPrice, 2); ?>)
            </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

    </div>

    <!-- Qty + cart button -->
    <div class="ramos-product-actions">
        <input type="number"
               class="form-control ramos-qty-input qty"
               value="1" min="1" max="<?php echo $wQty > 0 ? $wQty : 9999; ?>"
               data-w_quantity="<?php echo $wQty; ?>">

        <?php if ($hasVariation): ?>
        <button type="button"
                class="btn btn-success add_cart"
                data-id="<?php echo $pid; ?>"
                data-has-maduracion="<?php echo $hasMaduracion; ?>">
            <i class="fa fa-shopping-cart"></i>
        </button>
        <?php else: ?>
        <button type="button"
                class="btn btn-success ramos-add-cart-btn"
                data-id="<?php echo $pid; ?>"
                data-has-maduracion="<?php echo $hasMaduracion; ?>"
                data-has-equiv="<?php echo !empty($equivalences) ? 1 : 0; ?>">
            <i class="fa fa-shopping-cart"></i>
        </button>
        <button type="button" class="btn btn-default hide added" data-id="<?php echo $pid; ?>">
            <i class="fa fa-check"></i>
        </button>
        <?php endif; ?>
    </div>

</div><!-- /.ramos-product-card -->
<?php endforeach; ?>
</div>

<script>
(function ($) {
    // Update displayed price when equivalencia unit changes
    $(document).on('change', '.ramos-unit-select', function () {
        var $sel       = $(this);
        var pid        = $sel.data('product-id');
        var basePrice  = parseFloat($sel.data('base-price')) || 0;
        var $opt       = $sel.find(':selected');
        var factor     = parseFloat($opt.data('factor')) || 1;
        var unitPrice  = (basePrice * factor).toFixed(2);
        $('.ramos-unit-price-display-' + pid + ' .ramos-product-unit-price').text(unitPrice);
    });

    // Enhanced add-to-cart with maduracion/unit validation
    $(document).on('click', '.ramos-add-cart-btn', function () {
        var $btn        = $(this);
        var $card       = $btn.closest('.ramos-product-card');
        var pid         = $btn.data('id');
        var hasMad      = parseInt($btn.data('has-maduracion')) || 0;
        var hasEquiv    = parseInt($btn.data('has-equiv')) || 0;
        var $qtyInput   = $card.find('.qty');
        var qty         = parseInt($qtyInput.val()) || 1;
        var wQty        = parseInt($qtyInput.attr('data-w_quantity')) || 9999;
        var maduracion  = '';
        var equivUnit   = '';
        var equivFactor = 1;

        if (hasMad) {
            var $madSel = $card.find('.ramos-maduracion-select');
            maduracion  = $madSel.val();
            if (!maduracion) {
                alert_float('warning', '<?php echo _l('maduracion'); ?>: selecciona Verde o Maduro');
                return;
            }
        }

        if (hasEquiv) {
            var $unitSel = $card.find('.ramos-unit-select');
            equivUnit    = $unitSel.val();
            equivFactor  = parseFloat($unitSel.find(':selected').data('factor')) || 1;
        }

        if (wQty === 0) {
            alert_float('warning', $('input[name="msg_amount_not_available"]').val());
            return;
        }

        // Store extra metadata in cookies (comma-sep parallel to id/qty)
        var madList   = getCookie('cart_mad_list')   || '';
        var unitList  = getCookie('cart_unit_list')  || '';
        var factList  = getCookie('cart_fact_list')  || '';
        var idList    = getCookie('cart_id_list')    || '';
        var qtyList   = getCookie('cart_qty_list')   || '';

        var ids    = idList   ? idList.split(',')   : [];
        var qtys   = qtyList  ? qtyList.split(',')  : [];
        var mads   = madList  ? madList.split(',')  : [];
        var units  = unitList ? unitList.split(',') : [];
        var facts  = factList ? factList.split(',') : [];

        var idx = ids.indexOf(String(pid));
        if (idx === -1) {
            ids.push(pid);
            qtys.push(qty);
            mads.push(maduracion);
            units.push(equivUnit);
            facts.push(equivFactor);
        } else {
            qtys[idx] = Math.min(parseInt(qtys[idx]) + qty, wQty);
            mads[idx] = maduracion;
            units[idx] = equivUnit;
            facts[idx] = equivFactor;
        }

        add_to_cart(ids, qtys);
        add_cookie('cart_mad_list',  mads.join(','), 30);
        add_cookie('cart_unit_list', units.join(','), 30);
        add_cookie('cart_fact_list', facts.join(','), 30);

        $btn.addClass('hide');
        $card.find('button.added').removeClass('hide');
        alert_float('success', $('input[name="msg_add"]').val());
    });
})(jQuery);
</script>
