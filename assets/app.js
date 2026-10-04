// 危险操作二次确认（表单上声明 data-confirm="提示文案"）
document.addEventListener('submit', function (e) {
  var f = e.target;
  if (f && f.matches && f.matches('form[data-confirm]')) {
    if (!window.confirm(f.getAttribute('data-confirm'))) {
      e.preventDefault();
    }
  }
}, true);

// 顶部提示自动消失
document.querySelectorAll('.toast').forEach(function (t) {
  setTimeout(function () {
    t.classList.add('hide');
    setTimeout(function () { t.remove(); }, 450);
  }, 3200);
});
