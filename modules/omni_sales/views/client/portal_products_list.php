<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="container">
  <div class="row">
    <div class="col-md-12">

      <form method="get" action="" class="mbot15">
        <div class="input-group">
          <input type="text" name="q" class="form-control" placeholder="Buscar productos..."
                 value="<?php echo html_escape($q ?? ''); ?>">
          <span class="input-group-btn">
            <button class="btn btn-primary" type="submit">Buscar</button>
          </span>
        </div>
      </form>

      <div class="panel_s">
        <div class="panel-body">

          <?php if (empty($products)) { ?>
            <p>No se encontraron productos.</p>
          <?php } else { ?>

            <div class="list-group">
              <?php foreach ($products as $p) { ?>
                <div class="list-group-item" style="display:flex;align-items:center;gap:12px;">

                  <img src="<?php echo html_escape($p['image_url']); ?>"
                       alt="<?php echo html_escape($p['description']); ?>"
                       style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #eee;">

                  <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                      <?php echo html_escape($p['description']); ?>
                    </div>
                  </div>

                  <div style="width:110px;text-align:right;font-weight:600;">
                    <?php
                      // Si esto te da warning, lo cambiamos a number_format simple
                      echo app_format_money((float)$p['rate'], get_base_currency());
                    ?>
                  </div>

                  <div style="width:140px;text-align:right;">
                    <button class="btn btn-success btn-sm"
                            onclick="omniAddToOrder(<?php echo (int)$p['itemid']; ?>); return false;">
                      Agregar
                    </button>
                  </div>

                </div>
              <?php } ?>
            </div>

          <?php } ?>

        </div>
      </div>

    </div>
  </div>
</div>

<script>
function omniAddToOrder(productId){
  var url = "<?php echo site_url('omni_sales/omni_sales_client/add_to_cart'); ?>";

  var xhr = new XMLHttpRequest();
  xhr.open("POST", url, true);
  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");

  xhr.onload = function(){
    if(xhr.status === 200){
      try { var r = JSON.parse(xhr.responseText); } catch(e) {}
      alert("Agregado al pedido ✅");
    } else {
      alert("No se pudo agregar. Status: " + xhr.status + " Resp: " + xhr.responseText);
    }
  };

  xhr.send("product_id=" + encodeURIComponent(productId) + "&qty=1");
}
</script>
