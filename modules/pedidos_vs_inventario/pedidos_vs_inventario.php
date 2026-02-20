<?php
defined('BASEPATH') or exit('No direct script access allowed');
/*
Module Name: Pedidos vs Inventario
Description: Reporte pedidos vs inventario, faltantes y generar OCs por producto usando prioridad de proveedor.
Version: 1.3.1
Author: EREISE
*/
hooks()->add_action('admin_init', 'pvi_add_menu');
hooks()->add_action('app_admin_head', 'pvi_inject_vendor_items_price_ui');
hooks()->add_action('app_admin_footer', 'pvi_inject_vendor_items_price_ui');
register_activation_hook('pedidos_vs_inventario', 'pvi_module_activate');

function pvi_add_menu()
{
    $CI = &get_instance();
    $CI->app_menu->add_sidebar_menu_item('pedidos_vs_inventario', array(
        'name'     => 'Pedidos vs Inventario',
        'href'     => admin_url('pedidos_vs_inventario'),
        'position' => 30,
        'icon'     => 'fa fa-balance-scale',
    ));
    $CI->app_menu->add_sidebar_children_item('pedidos_vs_inventario', array(
        'slug'     => 'pvi_vendor_priority',
        'name'     => 'Prioridad Proveedores',
        'href'     => admin_url('pedidos_vs_inventario/vendor_priority'),
        'position' => 5,
    ));
}

function pvi_module_activate()
{
    $CI = &get_instance();
    if ($CI->db->table_exists('tblpur_vendor_items')) {
        if (!$CI->db->field_exists('priority', 'tblpur_vendor_items')) {
            $CI->db->query("ALTER TABLE `tblpur_vendor_items` ADD COLUMN `priority` INT(11) NOT NULL DEFAULT 0");
        }
        if (!$CI->db->field_exists('purchase_price', 'tblpur_vendor_items')) {
            $CI->db->query("ALTER TABLE `tblpur_vendor_items` ADD COLUMN `purchase_price` DECIMAL(18,2) NOT NULL DEFAULT 0");
        }
    }
}

function pvi_inject_vendor_items_price_ui()
{
    $uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
    if (stripos($uri, '/admin/purchase/vendor_items') === false) return;

    $getUrl  = admin_url('pedidos_vs_inventario/vendor_item_price_get');
    $saveUrl = admin_url('pedidos_vs_inventario/vendor_item_price_save');

    $js = <<<JS
<script>
(function(){
  if (window.__PVI_PRICE_UI_DONE__) return;
  window.__PVI_PRICE_UI_DONE__ = true;

  function getCsrf(){
    try{
      if (typeof csrfData !== 'undefined' && csrfData.token_name && csrfData.hash){
        var o={}; o[csrfData.token_name]=csrfData.hash; return o;
      }
    }catch(e){}
    var inp=document.querySelector('input[name^=csrf]');
    if(inp){ var o={}; o[inp.name]=inp.value; return o; }
    return {};
  }
  function normalize(s){ return (s||'').toString().trim(); }
  function pickTable(){
    var tables=[].slice.call(document.querySelectorAll('table'));
    if(!tables.length) return null;
    tables.sort(function(a,b){ return (b.querySelectorAll('tbody tr').length||0)-(a.querySelectorAll('tbody tr').length||0); });
    return tables[0];
  }
  function extractIds(tr){
    var vendorId=null, itemCode=null;
    if (tr.dataset){
      vendorId = tr.dataset.vendorId || tr.dataset.vendor || tr.dataset.supplier || null;
      itemCode = tr.dataset.itemCode || tr.dataset.code || tr.dataset.commodityId || tr.dataset.itemcode || null;
    }
    if(!vendorId || !itemCode){
      var nums=[];
      tr.querySelectorAll('td').forEach(function(td){
        var m=normalize(td.textContent).match(/\b\d+\b/g);
        if(m){ m.forEach(function(n){ nums.push(n); }); }
      });
      if(!vendorId && nums.length>=1) vendorId=nums[0];
      if(!itemCode && nums.length>=2) itemCode=nums[1];
    }
    return {vendorId:vendorId, itemCode:itemCode};
  }
  function ajaxPost(url, payload){
    var body=new URLSearchParams(payload).toString();
    return fetch(url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body})
      .then(function(r){ return r.json(); });
  }
  function attachBlocker(tr, td, input, btn){
    function block(e){
      var t=e.target;
      if(t===input || t===btn || (td && td.contains(t))){
        e.stopPropagation();
        if (e.stopImmediatePropagation) e.stopImmediatePropagation();
      }
    }
    ['click','mousedown','mouseup','dblclick','keydown','keyup','keypress','focus','focusin','pointerdown','touchstart'].forEach(function(ev){
      tr.addEventListener(ev, block, true);
      td.addEventListener(ev, block, true);
      input.addEventListener(ev, block, true);
      btn.addEventListener(ev, block, true);
    });
  }
  function run(){
    var target=pickTable(); if(!target) return;
    var thead=target.querySelector('thead'); if(!thead) return;
    var hr=thead.querySelector('tr'); if(!hr) return;

    if(!hr.querySelector('.pvi-purchase-price-th')){
      var th=document.createElement('th');
      th.className='pvi-purchase-price-th';
      th.textContent='Precio compra';
      hr.appendChild(th);
    }

    var tbody=target.querySelector('tbody'); if(!tbody) return;

    tbody.querySelectorAll('tr').forEach(function(tr){
      if(tr.querySelector('.pvi-purchase-price-td')) return;

      var ids=extractIds(tr);
      var vendorId=ids.vendorId, itemCode=ids.itemCode;

      var td=document.createElement('td');
      td.className='pvi-purchase-price-td';
      td.style.whiteSpace='nowrap';
      td.style.position='relative';
      td.style.zIndex='50';

      var input=document.createElement('input');
      input.type='number';
      input.step='0.01';
      input.min='0';
      input.style.width='110px';
      input.style.padding='4px 6px';
      input.style.border='1px solid #ccc';
      input.style.borderRadius='4px';
      input.style.background='#fff';
      input.style.pointerEvents='auto';
      input.style.position='relative';
      input.style.zIndex='60';
      input.value='';

      var btn=document.createElement('button');
      btn.type='button';
      btn.textContent='Guardar';
      btn.style.marginLeft='8px';
      btn.style.padding='4px 8px';
      btn.style.border='1px solid #111';
      btn.style.background='#111';
      btn.style.color='#fff';
      btn.style.borderRadius='4px';
      btn.style.cursor='pointer';
      btn.style.pointerEvents='auto';
      btn.style.position='relative';
      btn.style.zIndex='60';

      var msg=document.createElement('span');
      msg.style.marginLeft='8px';
      msg.style.fontSize='12px';

      function setMsg(t, ok){
        msg.textContent=t;
        msg.style.color = ok ? '#166534' : '#b91c1c';
      }

      if(vendorId && itemCode){
        var payload = Object.assign({vendor_id: vendorId, item_code: itemCode}, getCsrf());
        ajaxPost('{$getUrl}', payload).then(function(j){
          input.value = (j && j.ok) ? (j.purchase_price ?? 0) : 0;
        }).catch(function(){ input.value=0; });
      } else {
        input.value=0;
      }

      btn.addEventListener('click', function(){
        if(!vendorId || !itemCode){ setMsg('No se detectó vendor/item.', false); return; }
        msg.textContent='Guardando...'; msg.style.color='#555';
        var payload = Object.assign({vendor_id: vendorId, item_code: itemCode, purchase_price: input.value}, getCsrf());
        ajaxPost('{$saveUrl}', payload).then(function(j){
          if(j && j.ok){ setMsg('OK', true); }
          else { setMsg((j && j.error) ? j.error : 'Error', false); }
        }).catch(function(){ setMsg('Error AJAX', false); });
      });

      td.appendChild(input);
      td.appendChild(btn);
      td.appendChild(msg);
      tr.appendChild(td);

      attachBlocker(tr, td, input, btn);
    });
  }
  function schedule(){
    try{ run(); }catch(e){}
    setTimeout(function(){ try{ run(); }catch(e){} }, 900);
    setTimeout(function(){ try{ run(); }catch(e){} }, 1800);
  }
  if (document.readyState==='loading') document.addEventListener('DOMContentLoaded', schedule);
  else schedule();
  document.addEventListener('draw.dt', schedule, true);
})();
</script>
JS;
    echo $js;
}
