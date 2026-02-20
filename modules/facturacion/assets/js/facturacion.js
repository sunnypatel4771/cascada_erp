(function(){
  function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }
  qsa('.js-select-green').forEach(function(btn){
    btn.addEventListener('click', function(){
      var userid = btn.getAttribute('data-userid');
      qsa('tr[data-userid="'+userid+'"][data-status="green"] input[type=checkbox]').forEach(function(cb){
        cb.checked = true;
      });
    });
  });
})();