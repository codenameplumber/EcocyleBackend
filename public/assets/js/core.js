/* ==========================================================================
   EcoCycle — core.js
   Formatting, theme, DOM helpers, toasts, modals, pseudo-QR, status registry.
   Framework-free. Everything hangs off the global `EC` namespace.
   ========================================================================== */
(function (global) {
  'use strict';

  var EC = global.EC = global.EC || {};

  /* --- DOM --------------------------------------------------------------- */
  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function el(html) { var d = document.createElement('div'); d.innerHTML = html.trim(); return d.firstChild; }
  function on(node, evt, sel, fn) {
    node.addEventListener(evt, function (e) {
      var t = e.target.closest(sel);
      if (t && node.contains(t)) fn.call(t, e, t);
    });
  }

  /* --- Asset paths -------------------------------------------------------
     Pages live at two depths (index.html vs household/index.html), so any path
     built in JS has to know which. Derive it once from this script's own src
     rather than making every caller guess.                                   */
  var BASE = (function () {
    var s = document.querySelector('script[src$="core.js"]');
    var src = s ? s.getAttribute('src') : 'assets/js/core.js';
    return src.replace(/js\/core\.js.*$/, '');     /* 'assets/' or '../assets/' */
  })();
  function asset(p) { return BASE + p; }
  function img(name) { return BASE + 'img/' + name + '.jpg'; }

  /* --- Formatting -------------------------------------------------------- */
  var NAIRA = '₦';

  function money(v, opts) {
    opts = opts || {};
    var n = Number(v) || 0;
    if (opts.compact && Math.abs(n) >= 1000000) return NAIRA + (n / 1000000).toFixed(n % 1000000 === 0 ? 0 : 1) + 'M';
    if (opts.compact && Math.abs(n) >= 1000)    return NAIRA + (n / 1000).toFixed(n % 1000 === 0 ? 0 : 1) + 'k';
    return NAIRA + n.toLocaleString('en-NG', {
      minimumFractionDigits: opts.dp != null ? opts.dp : 0,
      maximumFractionDigits: opts.dp != null ? opts.dp : 0
    });
  }

  function kg(v, dp) {
    var n = Number(v) || 0;
    if (Math.abs(n) >= 1000) return (n / 1000).toLocaleString('en-NG', { maximumFractionDigits: 1 }) + ' t';
    return n.toLocaleString('en-NG', { minimumFractionDigits: dp != null ? dp : 1, maximumFractionDigits: dp != null ? dp : 1 }) + ' kg';
  }

  function num(v, dp) {
    return (Number(v) || 0).toLocaleString('en-NG', {
      minimumFractionDigits: dp || 0, maximumFractionDigits: dp != null ? dp : 0
    });
  }

  function pct(v, dp) { return (Number(v) || 0).toFixed(dp == null ? 1 : dp) + '%'; }

  /* Relative time from a minutes-ago offset (mock data stores offsets, not dates,
     so the prototype never goes stale). */
  function ago(mins) {
    if (mins < 1) return 'just now';
    if (mins < 60) return mins + 'm ago';
    var h = Math.floor(mins / 60);
    if (h < 24) return h + 'h ago';
    var d = Math.floor(h / 24);
    if (d === 1) return 'yesterday';
    if (d < 7) return d + 'd ago';
    return Math.floor(d / 7) + 'w ago';
  }

  function clockAt(minsAgo) {
    var t = new Date(Date.now() - minsAgo * 60000);
    return t.toLocaleTimeString('en-NG', { hour: '2-digit', minute: '2-digit', hour12: true });
  }

  function dateAt(minsAgo) {
    var t = new Date(Date.now() - minsAgo * 60000);
    return t.toLocaleDateString('en-NG', { day: '2-digit', month: 'short' });
  }

  function initials(name) {
    return String(name || '').trim().split(/\s+/).slice(0, 2).map(function (w) { return w[0]; }).join('').toUpperCase();
  }

  /* Deterministic avatar variant so the same person keeps the same colour. */
  function variant(seed) {
    var h = 0, s = String(seed);
    for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0;
    var v = h % 5;
    return v === 0 ? '' : 'v' + (v + 1);
  }

  /* --- Status registry ---------------------------------------------------
     Single source of truth for the WasteBatch state machine. Labels, colour
     class and pipeline order all come from here so no screen drifts.        */
  var STATUS = {
    REQUESTED:  { label: 'Requested',   cls: 'st-requested',  step: 1,  icon: 'bell' },
    AVAILABLE:  { label: 'Available',   cls: 'st-available',  step: 2,  icon: 'boxes' },
    ACCEPTED:   { label: 'Accepted',    cls: 'st-accepted',   step: 3,  icon: 'check' },
    COLLECTED:  { label: 'Collected',   cls: 'st-collected',  step: 4,  icon: 'package' },
    IN_TRANSIT: { label: 'In transit',  cls: 'st-transit',    step: 5,  icon: 'truck' },
    RECEIVED:   { label: 'Received',    cls: 'st-received',   step: 6,  icon: 'building' },
    INSPECTING: { label: 'Inspecting',  cls: 'st-inspecting', step: 7,  icon: 'inspect' },
    VERIFIED:   { label: 'Verified',    cls: 'st-verified',   step: 8,  icon: 'shield' },
    REJECTED:   { label: 'Rejected',    cls: 'st-rejected',   step: 8,  icon: 'ban' },
    PROCESSING: { label: 'Processing',  cls: 'st-processing', step: 9,  icon: 'factory' },
    PROCESSED:  { label: 'Processed',   cls: 'st-processed',  step: 10, icon: 'recycle' }
  };

  function status(key) { return STATUS[key] || { label: key, cls: 'st-requested', step: 0, icon: 'info' }; }
  function statusPill(key) {
    var s = status(key);
    return '<span class="st ' + s.cls + '">' + s.label + '</span>';
  }

  /* Waste categories — colour is reused by the mix bars and charts. */
  var CATEGORIES = {
    organic:     { label: 'Organic',     color: 'var(--c1)', rate: 42 },
    plastic:     { label: 'Plastic',     color: 'var(--c2)', rate: 118 },
    paper:       { label: 'Paper',       color: 'var(--c3)', rate: 65 },
    metal:       { label: 'Metal',       color: 'var(--c4)', rate: 210 },
    glass:       { label: 'Glass',       color: 'var(--c5)', rate: 54 },
    ewaste:      { label: 'E-waste',     color: 'var(--c6)', rate: 320 },
    mixed:       { label: 'Mixed',       color: 'var(--text-3)', rate: 38 }
  };
  function category(k) { return CATEGORIES[k] || CATEGORIES.mixed; }

  /* --- Theme ------------------------------------------------------------- */
  var THEME_KEY = 'ec.theme';
  function theme(next) {
    if (next) {
      document.documentElement.setAttribute('data-theme', next);
      try { localStorage.setItem(THEME_KEY, next); } catch (e) {}
      return next;
    }
    return document.documentElement.getAttribute('data-theme') || 'light';
  }
  function toggleTheme() { return theme(theme() === 'dark' ? 'light' : 'dark'); }
  (function bootTheme() {
    var saved = null;
    try { saved = localStorage.getItem(THEME_KEY); } catch (e) {}
    if (!saved && global.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) saved = 'dark';
    document.documentElement.setAttribute('data-theme', saved || 'light');
  })();

  /* --- Toast ------------------------------------------------------------- */
  function toast(msg, kind, ms) {
    var host = $('.toasts');
    if (!host) { host = el('<div class="toasts"></div>'); document.body.appendChild(host); }
    var icons = { ok: 'ok', warn: 'alert', err: 'ban', info: 'info' };
    var k = kind || 'ok';
    var t = el(
      '<div class="toast ' + k + '">' +
        EC.icons.svg(icons[k] || 'info', 'i i-sm') +
        '<span class="grow">' + msg + '</span>' +
      '</div>'
    );
    host.appendChild(t);
    setTimeout(function () {
      t.style.transition = 'opacity .2s, transform .2s';
      t.style.opacity = '0'; t.style.transform = 'translateX(12px)';
      setTimeout(function () { t.remove(); }, 220);
    }, ms || 2600);
  }

  /* --- Modal ------------------------------------------------------------- */
  function modal(html, opts) {
    opts = opts || {};
    var scrim = el('<div class="scrim"></div>');
    var box = el('<div class="modal" role="dialog" aria-modal="true"></div>');
    box.innerHTML = html;
    scrim.appendChild(box);
    document.body.appendChild(scrim);
    document.body.style.overflow = 'hidden';
    EC.icons.hydrate(box);

    function close() {
      scrim.remove();
      document.body.style.overflow = '';
      document.removeEventListener('keydown', esc);
      if (opts.onClose) opts.onClose();
    }
    function esc(e) { if (e.key === 'Escape') close(); }
    scrim.addEventListener('mousedown', function (e) { if (e.target === scrim) close(); });
    document.addEventListener('keydown', esc);
    $$('[data-close]', box).forEach(function (b) { b.addEventListener('click', close); });
    return { node: box, close: close };
  }

  function confirmSheet(title, body, confirmLabel, onConfirm, tone) {
    var m = modal(
      '<div class="card-head"><h3>' + title + '</h3>' +
      '<button class="btn btn-icon btn-quiet" data-close><i data-icon="close"></i></button></div>' +
      '<div class="card-body"><p class="muted">' + body + '</p></div>' +
      '<div class="card-foot row" style="justify-content:flex-end">' +
      '<button class="btn btn-ghost" data-close>Cancel</button>' +
      '<button class="btn ' + (tone === 'danger' ? 'btn-danger' : 'btn-primary') + '" data-go>' + confirmLabel + '</button></div>'
    );
    $('[data-go]', m.node).addEventListener('click', function () { m.close(); if (onConfirm) onConfirm(); });
    return m;
  }

  /* --- Pseudo-QR ---------------------------------------------------------
     A deterministic block pattern derived from the batch ID. It LOOKS like a
     QR code for design review; it does not encode anything. In Laravel this
     becomes a real QR pointing at /waste/{code}.                            */
  function qr(code, size) {
    var n = 21, cell = (size || 84) / n;
    var h = 2166136261;
    for (var i = 0; i < code.length; i++) { h ^= code.charCodeAt(i); h = (h * 16777619) >>> 0; }
    function bit(x, y) {
      var v = (x * 73856093) ^ (y * 19349663) ^ h;
      v = (v ^ (v >>> 13)) >>> 0;
      return (v % 100) < 46;
    }
    function finder(ox, oy) {
      var s = '';
      s += '<rect x="' + ox * cell + '" y="' + oy * cell + '" width="' + 7 * cell + '" height="' + 7 * cell + '"/>';
      s += '<rect x="' + (ox + 1) * cell + '" y="' + (oy + 1) * cell + '" width="' + 5 * cell + '" height="' + 5 * cell + '" fill="#fff"/>';
      s += '<rect x="' + (ox + 2) * cell + '" y="' + (oy + 2) * cell + '" width="' + 3 * cell + '" height="' + 3 * cell + '"/>';
      return s;
    }
    function inFinder(x, y) {
      return (x < 8 && y < 8) || (x > n - 9 && y < 8) || (x < 8 && y > n - 9);
    }
    var out = '';
    for (var y = 0; y < n; y++) {
      for (var x = 0; x < n; x++) {
        if (inFinder(x, y)) continue;
        if (bit(x, y)) out += '<rect x="' + x * cell + '" y="' + y * cell + '" width="' + cell + '" height="' + cell + '"/>';
      }
    }
    out += finder(0, 0) + finder(n - 7, 0) + finder(0, n - 7);
    return '<svg class="qr" viewBox="0 0 ' + (size || 84) + ' ' + (size || 84) + '" role="img" aria-label="Batch tag ' + code + '">' + out + '</svg>';
  }

  /* --- Misc -------------------------------------------------------------- */
  function copy(text) {
    if (navigator.clipboard) navigator.clipboard.writeText(text).then(function () { toast('Copied ' + text); });
    else toast('Copy unavailable', 'warn');
  }

  /* Wire up any element carrying a declarative behaviour attribute. */
  function bindActions(root) {
    $$('[data-toast]', root || document).forEach(function (b) {
      if (b.__bound) return; b.__bound = true;
      b.addEventListener('click', function () { toast(b.getAttribute('data-toast'), b.getAttribute('data-toast-kind') || 'ok'); });
    });
    $$('[data-copy]', root || document).forEach(function (b) {
      if (b.__bound) return; b.__bound = true;
      b.addEventListener('click', function () { copy(b.getAttribute('data-copy')); });
    });
    $$('[data-theme-toggle]', root || document).forEach(function (b) {
      if (b.__bound) return; b.__bound = true;
      b.addEventListener('click', function () {
        toggleTheme();
        if (EC.charts && EC.charts.redraw) EC.charts.redraw();
      });
    });
    /* Simple tab / segmented control switching */
    $$('[data-tabs]', root || document).forEach(function (group) {
      if (group.__bound) return; group.__bound = true;
      group.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-tab]');
        if (!btn) return;
        $$('[data-tab]', group).forEach(function (b) { b.setAttribute('aria-selected', b === btn); });
        var target = group.getAttribute('data-tabs');
        if (!target) return;
        $$('[data-panel]', document.querySelector(target) || document).forEach(function (p) {
          p.classList.toggle('hidden', p.getAttribute('data-panel') !== btn.getAttribute('data-tab'));
        });
      });
    });
  }

  EC.$ = $; EC.$$ = $$; EC.el = el; EC.on = on;
  EC.asset = asset; EC.img = img; EC.BASE = BASE;
  EC.money = money; EC.kg = kg; EC.num = num; EC.pct = pct;
  EC.ago = ago; EC.clockAt = clockAt; EC.dateAt = dateAt;
  EC.initials = initials; EC.variant = variant;
  EC.STATUS = STATUS; EC.status = status; EC.statusPill = statusPill;
  EC.CATEGORIES = CATEGORIES; EC.category = category;
  EC.theme = theme; EC.toggleTheme = toggleTheme;
  EC.toast = toast; EC.modal = modal; EC.confirm = confirmSheet;
  EC.qr = qr; EC.copy = copy; EC.bindActions = bindActions;
  EC.NAIRA = NAIRA;

  document.addEventListener('DOMContentLoaded', function () { bindActions(); });
})(window);
