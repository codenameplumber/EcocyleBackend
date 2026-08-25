/* ==========================================================================
   EcoCycle — render.js
   Shared view partials. Each function returns an HTML string, mirroring the
   Blade partials these become later (resources/views/partials/*.blade.php).
   ========================================================================== */
(function (global) {
  'use strict';
  var EC = global.EC = global.EC || {};
  var R = {};

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
  }); }

  /* --- Stat tile ---------------------------------------------------------- */
  R.stat = function (o) {
    return '<div class="card stat tone-' + (o.tone || 'green') + '">' +
      '<div class="stat-top"><span class="stat-label">' + esc(o.label) + '</span>' +
      '<span class="stat-icon">' + EC.icons.svg(o.icon || 'chart', 'i i-sm') + '</span></div>' +
      '<div class="stat-value">' + o.value + (o.unit ? '<small>' + o.unit + '</small>' : '') + '</div>' +
      '<div class="stat-foot">' +
        (o.delta != null
          ? '<span class="delta ' + (o.delta >= 0 ? 'up' : 'down') + '">' +
            EC.icons.svg(o.delta >= 0 ? 'arrowUp' : 'arrowDn', 'i i-sm') + Math.abs(o.delta) + '%</span>' : '') +
        '<span>' + esc(o.foot || '') + '</span>' +
      '</div></div>';
  };

  /* --- Short code, e.g. EC-W-…92388 --------------------------------------- */
  R.shortCode = function (code) {
    var s = String(code);
    return s.length > 12 ? s.slice(0, 7) + '…' + s.slice(-5) : s;
  };

  /* --- Waste category chip ------------------------------------------------ */
  R.cat = function (key) {
    var c = EC.category(key);
    return '<span class="badge"><span class="dot" style="background:' + c.color + '"></span>' + c.label + '</span>';
  };

  /* --- Imagery -------------------------------------------------------------
     One place that knows how a photograph is framed, so ratio, lazy-loading
     and alt text stay consistent wherever a picture appears.                 */
  R.media = function (name, opts) {
    opts = opts || {};
    return '<div class="media media-' + (opts.ratio || '3x2') +
        (opts.scrim ? ' media-scrim' : '') + (opts.cls ? ' ' + opts.cls : '') + '"' +
        (opts.height ? ' style="aspect-ratio:auto;height:' + opts.height + 'px"' : '') + '>' +
      '<img src="' + EC.img(name) + '" alt="' + esc(opts.alt || '') + '"' +
        (opts.eager ? '' : ' loading="lazy"') + '>' +
      (opts.tag ? '<span class="media-tag">' + esc(opts.tag) + '</span>' : '') +
      (opts.cap ? '<div class="media-cap">' + opts.cap + '</div>' : '') +
    '</div>';
  };

  /* Square thumbnail of a waste category — used in lists and pickers. */
  R.catThumb = function (key, cls) {
    var c = EC.category(key);
    return '<span class="thumb ' + (cls || '') + '">' +
      '<img src="' + EC.img('cat-' + key) + '" alt="' + esc(c.label) + '" loading="lazy">' +
    '</span>';
  };

  /* Category chip with its photograph attached. */
  R.catRow = function (key) {
    var c = EC.category(key);
    return '<span class="row g-8">' + R.catThumb(key, 'thumb-sm') +
      '<span><span class="small strong">' + c.label + '</span>' +
      '<span class="tiny dim figure-num" style="display:block">₦' + c.rate + '/kg</span></span></span>';
  };

  /* --- Batch table row ---------------------------------------------------- */
  R.batchRow = function (b, opts) {
    opts = opts || {};
    var h = EC.data.hh(b.hh);
    var a = b.agent ? EC.data.agent(b.agent) : null;
    var w = b.verified_kg != null ? b.verified_kg : (b.collected_kg != null ? b.collected_kg : b.expected_kg);
    var variance = b.verified_kg != null && b.collected_kg
      ? ((b.verified_kg - b.collected_kg) / b.collected_kg) * 100 : null;

    return '<tr data-code="' + b.code + '" style="cursor:pointer">' +
      '<td><span class="mono strong">' + R.shortCode(b.code) + '</span>' +
        '<div class="tiny dim">' + EC.ago(b.created) + '</div></td>' +
      '<td><div class="row g-8"><span class="avatar avatar-sm ' + EC.variant(h.name) + '">' + EC.initials(h.name) + '</span>' +
        '<span style="min-width:0"><div class="small strong truncate">' + esc(h.name) + '</div>' +
        '<div class="tiny dim">' + esc(h.area) + '</div></span></div></td>' +
      '<td>' + (a ? '<div class="small">' + esc(a.name) + '</div><div class="tiny dim">' + esc(a.vehicle) + '</div>'
                  : '<span class="tiny dim">Unassigned</span>') + '</td>' +
      '<td><span class="row g-8">' + R.catThumb(b.cat, 'thumb-sm') +
        '<span class="small">' + EC.category(b.cat).label + '</span></span></td>' +
      '<td class="num"><span class="strong">' + EC.kg(w) + '</span>' +
        (variance != null
          ? '<div class="tiny ' + (Math.abs(variance) > 5 ? 't-neg' : 'dim') + '">' +
            (variance >= 0 ? '+' : '') + variance.toFixed(1) + '% vs declared</div>'
          : '<div class="tiny dim">' + (b.verified_kg == null ? 'unverified' : '') + '</div>') + '</td>' +
      '<td>' + EC.statusPill(b.status) + '</td>' +
      (opts.value === false ? '' : '<td class="num strong">' + EC.money(b.value) + '</td>') +
      '</tr>';
  };

  /* --- Job card (agent marketplace) --------------------------------------- */
  R.jobCard = function (j) {
    var h = EC.data.hh(j.hh);
    var tone = j.priority === 'high' ? 'badge-red' : j.priority === 'low' ? '' : 'badge-amber';
    return '<article class="card card-flat card-hover" data-job="' + j.code + '">' +
      '<div class="card-body" style="padding:14px">' +
        '<div class="row-top g-12">' +
          /* The material photograph is the first thing an agent looks at: it
             tells them what they are lifting before any of the numbers do. */
          R.catThumb(j.cat, 'thumb-lg') +
          '<div class="grow" style="min-width:0">' +
            '<div class="between" style="align-items:flex-start">' +
              '<div style="min-width:0">' +
                '<div class="item-title truncate">' + esc(h.name) + '</div>' +
                '<div class="item-sub">' + esc(h.area) + ' · ' + j.km.toFixed(1) + ' km away</div>' +
              '</div>' +
              '<span class="badge ' + tone + '">' + (j.priority === 'high' ? 'Urgent' : j.priority === 'low' ? 'Flexible' : 'Today') + '</span>' +
            '</div>' +
            '<div class="row g-14 mt-10 wrap">' +
              '<span class="row g-6 small">' + EC.icons.svg('scale', 'i i-sm') +
                '<span class="strong figure-num">' + EC.kg(j.est_kg, 1) + '</span><span class="dim">est.</span></span>' +
              '<span class="small dim">' + esc(EC.category(j.cat).label) + '</span>' +
              '<span class="row g-6 small dim">' + EC.icons.svg('clock', 'i i-sm') + esc(j.window) + '</span>' +
            '</div>' +
          '</div>' +
        '</div>' +
        '<div class="divider mt-12 mb-12"></div>' +
        '<div class="between">' +
          '<div><div class="tiny dim">Your share (40%)</div>' +
            '<div class="strong figure-num" style="font-size:17px">' + EC.money(Math.round(j.payout * 0.4)) + '</div></div>' +
          '<div class="row g-10">' +
            '<div class="right"><div class="tiny dim">Match</div><div class="small strong" style="color:var(--violet)">' + j.match + '%</div></div>' +
            '<button class="btn btn-primary btn-sm" data-accept="' + j.code + '">Accept</button>' +
          '</div>' +
        '</div>' +
      '</div></article>';
  };

  /* --- Faux map ----------------------------------------------------------- */
  R.map = function (pins, opts) {
    opts = opts || {};
    var roads =
      '<svg class="map-roads" viewBox="0 0 100 100" preserveAspectRatio="none">' +
        '<path d="M0 34 H100" stroke="var(--border-strong)" stroke-width=".8" fill="none"/>' +
        '<path d="M0 68 H100" stroke="var(--border-strong)" stroke-width=".8" fill="none"/>' +
        '<path d="M28 0 V100" stroke="var(--border-strong)" stroke-width=".8" fill="none"/>' +
        '<path d="M64 0 V100" stroke="var(--border-strong)" stroke-width=".6" fill="none"/>' +
        '<path d="M0 12 C30 18 44 44 100 52" stroke="var(--border-strong)" stroke-width=".5" fill="none"/>' +
      '</svg>';

    var body = pins.map(function (p) {
      return '<span class="pin ' + (p.kind || 'house') + (p.live ? ' pin-live' : '') + '" style="left:' + p.x + '%;top:' + p.y + '%">' +
        '<span class="pin-mark">' + EC.icons.svg(p.icon || (p.kind === 'agent' ? 'truck' : p.kind === 'plant' ? 'factory' : 'house'), 'i i-sm') + '</span>' +
        (p.label ? '<span class="pin-label">' + esc(p.label) + '</span>' : '') +
      '</span>';
    }).join('');

    var legend = opts.legend === false ? '' :
      '<div class="map-legend">' +
        '<span><i style="background:var(--primary)"></i>Household</span>' +
        '<span><i style="background:var(--info)"></i>Agent</span>' +
        '<span><i style="background:var(--violet)"></i>Facility</span>' +
      '</div>';

    return '<div class="map" style="' + (opts.height ? 'min-height:' + opts.height + 'px' : '') + '">' +
      roads + body + legend +
      (opts.note ? '<div class="tiny dim" style="position:absolute;right:10px;top:10px;background:var(--surface);' +
        'padding:4px 9px;border-radius:var(--r-pill);border:1px solid var(--border)">' + esc(opts.note) + '</div>' : '') +
      '</div>';
  };

  /* --- Waste mix bar ------------------------------------------------------ */
  R.mixBar = function (mix) {
    return '<div class="mixbar">' + mix.map(function (m) {
      return '<span style="width:' + m.v + '%;background:' + EC.category(m.key).color + '" title="' +
             EC.category(m.key).label + ' ' + m.v + '%"></span>';
    }).join('') + '</div>';
  };

  R.mixLegend = function (mix) {
    return '<div class="row wrap g-12">' + mix.map(function (m) {
      return '<span class="row g-6 tiny"><span style="width:8px;height:8px;border-radius:2px;background:' +
             EC.category(m.key).color + ';display:inline-block"></span>' +
             EC.category(m.key).label + ' <span class="strong">' + m.v + '%</span></span>';
    }).join('') + '</div>';
  };

  /* --- Traceability timeline for one batch -------------------------------- */
  R.trace = function (b) {
    var order = ['REQUESTED', 'ACCEPTED', 'COLLECTED', 'IN_TRANSIT', 'RECEIVED', 'INSPECTING',
                 b.status === 'REJECTED' ? 'REJECTED' : 'VERIFIED', 'PROCESSING', 'PROCESSED'];
    var cur = EC.status(b.status).step;
    var h = EC.data.hh(b.hh);
    var a = b.agent ? EC.data.agent(b.agent) : null;

    var meta = {
      REQUESTED:  ['Household raised the request', h.name + ' · ' + h.area, b.created],
      ACCEPTED:   ['Agent accepted the job', a ? a.name + ' · ' + a.vehicle : 'Awaiting an agent', b.created - 8],
      COLLECTED:  ['Picked up and tagged', b.collected_kg != null ? 'Declared ' + EC.kg(b.collected_kg, 1) : '—', b.collected_at],
      IN_TRANSIT: ['Moving to the facility', a ? a.vehicle : '—', b.collected_at != null ? b.collected_at - 5 : null],
      RECEIVED:   ['Logged in at the gate', EC.data.company.short, b.received_at],
      INSPECTING: ['Quality and weight check', 'Inspection bay 2', b.received_at != null ? b.received_at - 12 : null],
      VERIFIED:   ['Verified and accepted', b.verified_kg != null ? EC.kg(b.verified_kg, 1) + ' · grade ' + (b.quality || '—') : '—', b.inspected_at],
      REJECTED:   ['Rejected at inspection', b.reject_reason || '—', b.inspected_at],
      PROCESSING: ['Entered processing', 'Line assignment automatic', b.inspected_at != null ? b.inspected_at - 30 : null],
      PROCESSED:  ['Processing complete', b.output || '—', b.processed_at]
    };

    return '<div class="timeline">' + order.map(function (k) {
      var s = EC.status(k), m = meta[k];
      var state = s.step < cur ? 'done' : s.step === cur ? 'current' : '';
      return '<div class="tl-item ' + state + '">' +
        '<span class="tl-dot">' + EC.icons.svg(state ? s.icon : 'clock', 'i i-sm') + '</span>' +
        '<div><div class="between"><span class="tl-title">' + m[0] + '</span>' +
        '<span class="tl-time">' + (state && m[2] != null ? EC.ago(m[2]) : '—') + '</span></div>' +
        '<div class="tl-meta">' + esc(m[1]) + '</div></div></div>';
    }).join('') + '</div>';
  };

  /* --- Horizontal step rail (household-facing progress) -------------------- */
  R.steps = function (statusKey) {
    var stages = [
      { k: 'REQUESTED', l: 'Requested', i: 'bell' },
      { k: 'ACCEPTED',  l: 'Accepted',  i: 'check' },
      { k: 'COLLECTED', l: 'Collected', i: 'package' },
      { k: 'RECEIVED',  l: 'Received',  i: 'building' },
      { k: 'VERIFIED',  l: 'Verified',  i: 'shield' }
    ];
    var cur = EC.status(statusKey).step;
    return '<div class="steps">' + stages.map(function (s) {
      var st = EC.status(s.k).step;
      var cls = st < cur ? 'done' : st === cur || (cur > st && cur < st + 1) ? 'active' : '';
      if (cur > st) cls = 'done';
      else if (cur === st) cls = 'active';
      else cls = '';
      return '<div class="step ' + cls + '"><span class="step-dot">' +
        EC.icons.svg(cls === 'done' ? 'check' : s.i, 'i i-sm') + '</span>' +
        '<span class="step-label">' + s.l + '</span></div>';
    }).join('') + '</div>';
  };

  /* --- AI insight card ---------------------------------------------------- */
  R.insight = function (o) {
    return '<div class="ai-card">' +
      '<div class="between mb-8"><span class="ai-tag">' + EC.icons.svg('sparkles', 'i i-sm') + ' ' + esc(o.tag) + '</span>' +
      '<span class="confidence">' + Math.round(o.conf * 100) + '%<span class="bar bar-sm" style="width:52px">' +
      '<span style="width:' + (o.conf * 100) + '%;background:var(--violet)"></span></span></span></div>' +
      '<div class="strong" style="font-size:14.5px;color:var(--text)">' + esc(o.title) + '</div>' +
      '<p class="small muted mt-4">' + esc(o.body) + '</p>' +
      '</div>';
  };

  /* --- Empty state -------------------------------------------------------- */
  R.empty = function (icon, title, sub) {
    return '<div class="empty"><span class="empty-icon">' + EC.icons.svg(icon, 'i i-lg') + '</span>' +
      '<div class="strong">' + esc(title) + '</div>' +
      (sub ? '<p class="small dim" style="max-width:280px">' + esc(sub) + '</p>' : '') + '</div>';
  };

  R.esc = esc;
  EC.render = R;
})(window);
