(function () {
  'use strict';
  var d = document;


  var nb = d.querySelector('[data-nav-toggle]');
  var nav = d.getElementById('nav');
  if (nb && nav) {
    nb.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      nb.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }


  var tb = d.querySelector('[data-theme-toggle]');
  if (tb) {
    tb.addEventListener('click', function () {
      var root = d.documentElement;
      var cur = root.getAttribute('data-theme');
      if (!cur) cur = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      var next = cur === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('ptl-theme', next); } catch (e) {}
    });
  }


  d.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (!(f instanceof HTMLFormElement) || f.getAttribute('method') !== 'post') return;
    if (f.dataset.busy === '1') { ev.preventDefault(); return; }
    f.dataset.busy = '1';
    var b = f.querySelector('button[type=submit]');
    if (b) { b.setAttribute('aria-busy', 'true'); setTimeout(function () { b.disabled = true; }, 0); }
    setTimeout(function () { f.dataset.busy = '0'; if (b) { b.disabled = false; b.removeAttribute('aria-busy'); } }, 15000);
  });


  var sel = d.getElementById('f_platform');
  if (sel && sel.dataset.dsp) {
    var dsp = sel.dataset.dsp.split(',');
    var note = d.getElementById('dspnote');
    var apply = function () {
      var isDsp = dsp.indexOf(sel.value) !== -1;
      if (note) note.hidden = !isDsp;
      d.querySelectorAll('.offer-edit').forEach(function (el) {
        var off = isDsp && el.dataset.svc !== 'review';
        el.hidden = off;
        if (off) { var c = el.querySelector('input[type=checkbox]'); if (c) c.checked = false; }
      });
    };
    sel.addEventListener('change', apply);
    apply();
  }


  var chips = d.querySelectorAll('.chip input');
  chips.forEach(function (c) {
    c.addEventListener('change', function () {
      var n = d.querySelectorAll('.chip input:checked').length;
      if (n > 4) { c.checked = false; }
    });
  });
})();
