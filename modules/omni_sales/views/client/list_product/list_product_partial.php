<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php
$items = array();
if (isset($ereise_all_items) && is_array($ereise_all_items) && count($ereise_all_items) > 0) {
    $items = $ereise_all_items;
}
?>

<style>
.ereise-products { margin-top: 10px; }
.ereise-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px;
    border: 1px solid #eee;
    border-radius: 8px;
    margin-bottom: 10px;
    background: #fff;
}
.ereise-img {
    width: 70px;
    height: 70px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #f0f0f0;
    flex: 0 0 70px;
}
.ereise-info { flex: 1; min-width: 0; }
.ereise-name { font-weight: 600; margin: 0; }
.ereise-desc { margin: 4px 0 0 0; color: #666; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ereise-price { font-weight: 700; margin: 0; }
.ereise-actions { display: flex; align-items: center; gap: 8px; }
.ereise-qty { width: 80px; }
</style>

<?php if (count($items) > 0): ?>
    <div class="ereise-products">
        <?php foreach ($items as $it): ?>
            <?php
                $id   = isset($it['itemid']) ? (int)$it['itemid'] : 0;
                $name = isset($it['description']) ? $it['description'] : ('Item #' . $id);
                $desc = isset($it['long_description']) ? strip_tags($it['long_description']) : '';
                $rate = isset($it['rate']) ? (float)$it['rate'] : 0;

                $img = base_url('assets/images/placeholder.png');
                if (isset($it['image_url']) && $it['image_url'] != '') {
                    $img = $it['image_url'];
                }
            ?>
            <div class="ereise-row">
                <img class="ereise-img" src="<?php echo html_escape($img); ?>" alt="<?php echo html_escape($name); ?>">

                <div class="ereise-info">
                    <p class="ereise-name"><?php echo html_escape($name); ?></p>
                    <?php if ($desc !== ''): ?>
                        <p class="ereise-desc"><?php echo html_escape($desc); ?></p>
                    <?php endif; ?>
                </div>

                <div style="text-align:right; min-width:120px;">
                    <p class="ereise-price"><?php echo number_format($rate, 2); ?></p>
                </div>

                <div class="ereise-actions">
                    <input type="number" class="form-control ereise-qty" name="qty" value="1" min="1">
                    <button type="button" class="btn btn-success add_cart" data-id="<?php echo (int)$id; ?>">
                        <i class="fa fa-shopping-cart"></i> <?php echo _l('add_to_cart'); ?>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php else: ?>
    <?php
    // Fallback sin null coalescing
    if (isset($product) && is_array($product) && count($product) > 0) {
        foreach ($product as $p) {
            $pname = '';
            if (isset($p['name'])) { $pname = $p['name']; }
            echo '<div style="padding:10px;border:1px solid #eee;margin-bottom:10px;border-radius:8px;">';
            echo '<strong>'.html_escape($pname).'</strong>';
            echo '</div>';
        }
    } else {
        echo '<div class="alert alert-info">No hay productos para mostrar.</div>';
    }
    ?>
<?php endif; ?>
