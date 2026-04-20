<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
// Vendor-first mode: item picker should be disabled until a vendor is chosen.
$_item_vendor_first_mode = (function_exists('get_purchase_option') && get_purchase_option('item_by_vendor') == 1);

// In edit view ($pur_order is set) the vendor is already preselected — picker must stay enabled.
$_item_picker_disabled  = $_item_vendor_first_mode && !isset($pur_order);

// Also allow a GET ?vendor=X pre-selection (new PO started from a vendor page).
if ($_item_picker_disabled && !empty($this->input->get('vendor'))) {
    $_item_picker_disabled = false;
}

$_item_none_text = $_item_picker_disabled
    ? _l('select_vendor_first_item')
    : _l('select_item');
?>
<div class="form-group mbot25  select-placeholder">
     <select name="item_select"
             class="selectpicker no-margin<?php if($ajaxItems == true){echo ' ajax-search';} ?>"
             data-width="100%"
             id="item_select"
             data-none-selected-text="<?php echo $_item_none_text; ?>"
             data-live-search="true"
             <?php if($_item_picker_disabled){ echo 'disabled="disabled"'; } ?>>
      <option value=""></option>
      <?php foreach($items as $group_id=>$_items){ ?>
      <optgroup data-group-id="<?php echo $group_id; ?>" label="<?php echo $_items[0]['group_name']; ?>">
       <?php foreach($_items as $item){ ?>
       <option value="<?php echo $item['id']; ?>" data-subtext="<?php echo strip_tags(mb_substr($item['long_description'] ?? '',0,200)).'...'; ?>"><?php
         if (!function_exists('purchase_po_hides_prices') || !purchase_po_hides_prices()) {
             echo '(' . app_format_number($item['purchase_price']) . ') ';
         }
         echo $item['description'];
       ?></option>
       <?php } ?>
     </optgroup>
     <?php } ?>
   </select>
</div>
