/* ==========================================================================
   EcoCycle — icons.js
   Inline SVG icon set. No font, no CDN, no build step.

   Usage:  <i data-icon="truck"></i>
           <i data-icon="truck" class="i-lg"></i>
   EC.icons.hydrate() replaces every [data-icon] with an <svg>, preserving
   the element's own classes. Called automatically on DOMContentLoaded and
   re-callable after any dynamic render.
   ========================================================================== */
(function (global) {
  'use strict';

  var P = {
    /* nav + shell */
    home:    '<path d="M3.2 10.6 12 3.4l8.8 7.2V20a1 1 0 0 1-1 1h-5.1v-6.6H9.3V21H4.2a1 1 0 0 1-1-1z"/>',
    grid:    '<rect x="3.5" y="3.5" width="7" height="7" rx="1.6"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.6"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.6"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.6"/>',
    menu:    '<path d="M4 7h16M4 12h16M4 17h16"/>',
    close:   '<path d="M6 6l12 12M18 6 6 18"/>',
    check:   '<path d="M5 12.8 9.6 17.4 19 8"/>',
    chevR:   '<path d="M9.5 5.5 16 12l-6.5 6.5"/>',
    chevD:   '<path d="M5.5 9 12 15.5 18.5 9"/>',
    chevL:   '<path d="M14.5 5.5 8 12l6.5 6.5"/>',
    plus:    '<path d="M12 5v14M5 12h14"/>',
    minus:   '<path d="M5 12h14"/>',
    search:  '<circle cx="11" cy="11" r="7"/><path d="M20.5 20.5 16.2 16.2"/>',
    filter:  '<path d="M3.5 5h17l-6.8 8v6l-3.4-1.9V13z"/>',
    download:'<path d="M12 3.5v11.5M7.6 10.6 12 15l4.4-4.4"/><path d="M4 20h16"/>',
    refresh: '<path d="M20.5 12a8.5 8.5 0 1 1-2.6-6.1"/><path d="M20.7 4.4v4.4h-4.4"/>',
    more:    '<circle cx="12" cy="5.5" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="12" cy="18.5" r="1.4"/>',
    logout:  '<path d="M15 3.5h3.2a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H15"/><path d="M10 16 6 12l4-4"/><path d="M6 12h10"/>',
    settings:'<circle cx="12" cy="12" r="3"/><path d="M12 2.6v2.6M12 18.8v2.6M4.3 7.4l2.2 1.3M17.5 15.3l2.2 1.3M4.3 16.6l2.2-1.3M17.5 8.7l2.2-1.3"/>',
    eye:     '<path d="M2.5 12S6 5.8 12 5.8 21.5 12 21.5 12 18 18.2 12 18.2 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
    edit:    '<path d="M4 20h4.2L19.6 8.6a2 2 0 0 0 0-2.8l-1.4-1.4a2 2 0 0 0-2.8 0L4 15.8z"/>',
    bell:    '<path d="M18 16.2v-5.4a6 6 0 1 0-12 0v5.4L4.2 19h15.6z"/><path d="M9.9 19.6a2.2 2.2 0 0 0 4.2 0"/>',
    sun:     '<circle cx="12" cy="12" r="4"/><path d="M12 2.4v2.2M12 19.4v2.2M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2.4 12h2.2M19.4 12h2.2M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6"/>',
    moon:    '<path d="M20.4 14.6A8.6 8.6 0 0 1 9.4 3.6a8.6 8.6 0 1 0 11 11z"/>',

    /* domain */
    leaf:    '<path d="M20.5 3.5C9.6 3.5 4.4 8.8 4.4 15.4V20.5"/><path d="M20.5 3.5c.3 9.5-5.2 13.9-11.9 13.9H4.4"/>',
    house:   '<path d="M3.2 10.6 12 3.4l8.8 7.2V20a1 1 0 0 1-1 1H4.2a1 1 0 0 1-1-1z"/><path d="M9.4 21v-6.4h5.2V21"/>',
    truck:   '<path d="M2.6 6.4h10.8v9.8H2.6z"/><path d="M13.4 9.8h3.9l3.1 3.1v3.3h-7z"/><circle cx="7" cy="18.2" r="1.9"/><circle cx="17" cy="18.2" r="1.9"/>',
    package: '<path d="M20.8 8.4 12 3.6 3.2 8.4v7.2L12 20.4l8.8-4.8z"/><path d="M3.2 8.4 12 13.2l8.8-4.8"/><path d="M12 13.2v7.2"/>',
    boxes:   '<rect x="3" y="12.5" width="8" height="8" rx="1.2"/><rect x="13" y="12.5" width="8" height="8" rx="1.2"/><rect x="8" y="3.5" width="8" height="8" rx="1.2"/>',
    pin:     '<path d="M12 21.2s7-6 7-11.2a7 7 0 1 0-14 0c0 5.2 7 11.2 7 11.2z"/><circle cx="12" cy="10" r="2.5"/>',
    nav:     '<path d="M21 3 3 10.6l8.1 2.9 2.9 8.1z"/>',
    route:   '<circle cx="5.5" cy="18" r="2.4"/><circle cx="18.5" cy="6" r="2.4"/><path d="M7.9 18h5.1a4.4 4.4 0 0 0 0-8.8h-1.8a4.4 4.4 0 0 1 0-8.2"/><path d="M11.2 1h4.9"/>',
    scale:   '<path d="M7 8.2h10l2.6 10.2a1 1 0 0 1-1 1.3H5.4a1 1 0 0 1-1-1.3z"/><circle cx="12" cy="5.4" r="1.9"/><path d="M12 7.3v.9"/>',
    scan:    '<path d="M4 8.4V5.6a1.6 1.6 0 0 1 1.6-1.6h2.8M15.6 4h2.8A1.6 1.6 0 0 1 20 5.6v2.8M20 15.6v2.8a1.6 1.6 0 0 1-1.6 1.6h-2.8M8.4 20H5.6A1.6 1.6 0 0 1 4 18.4v-2.8"/><path d="M4 12h16"/>',
    qr:      '<rect x="3.5" y="3.5" width="7" height="7" rx="1"/><rect x="13.5" y="3.5" width="7" height="7" rx="1"/><rect x="3.5" y="13.5" width="7" height="7" rx="1"/><path d="M13.5 13.5h3.2v3.2h-3.2zM20.5 13.5v3.2M17 20.5h3.5M13.5 20.5h.02"/>',
    factory: '<path d="M3.2 20.5V10.8l5.2 3.1v-3.1l5.2 3.1v-3.1l5.2 3.1v6.6z"/><path d="M2.4 20.5h19.2"/><path d="M18.8 10.8V4.4h-2.6v5"/>',
    recycle: '<path d="M8.4 4.8 12 10.6"/><path d="M12 3.4 15.6 9.2"/><path d="M4.2 15.4 7.8 9.6M9.6 20.6H4.4l2.6-4.4M14.4 20.6h5.2l-2.6-4.4M19.8 15.4 16.2 9.6"/>',
    flame:   '<path d="M12 3.2s5.4 4.1 5.4 9.1a5.4 5.4 0 0 1-10.8 0c0-1.6.7-2.9 1.5-3.8.4 1.2 1.3 2 2.2 2 0-2.6.9-5.3 1.7-7.3z"/>',
    wallet:  '<path d="M3.4 7.6a2 2 0 0 1 2-2h11.2a2 2 0 0 1 2 2v.9"/><path d="M3.4 7.6v9.6a2 2 0 0 0 2 2h13.2a2 2 0 0 0 2-2v-6.4a2 2 0 0 0-2-2H5.4a2 2 0 0 1-2-1.2"/><circle cx="16.4" cy="14" r="1.2"/>',
    cash:    '<rect x="2.6" y="6" width="18.8" height="12" rx="2"/><circle cx="12" cy="12" r="2.8"/><path d="M6.2 9.4v.02M17.8 14.6v.02"/>',
    clock:   '<circle cx="12" cy="12" r="8.6"/><path d="M12 6.8V12l3.4 2.1"/>',
    history: '<path d="M3.4 12a8.6 8.6 0 1 0 2.8-6.4L3.4 8.2"/><path d="M3 3.2v5.2h5.2"/><path d="M12 7.4V12l3.4 2.1"/>',
    calendar:'<rect x="3.4" y="5.2" width="17.2" height="15.4" rx="2"/><path d="M8.2 3v4.4M15.8 3v4.4M3.4 10.4h17.2"/>',
    user:    '<circle cx="12" cy="8.2" r="3.6"/><path d="M4.6 20.4a7.4 7.4 0 0 1 14.8 0"/>',
    users:   '<circle cx="9.4" cy="8" r="3.4"/><path d="M2.8 20.2a6.6 6.6 0 0 1 13.2 0"/><path d="M16.2 5.2a3.4 3.4 0 0 1 0 5.6"/><path d="M17.6 14.6a6.4 6.4 0 0 1 3.8 5.6"/>',
    phone:   '<path d="M6 3.4h3.2l1.6 4.4-2.2 1.6a12.4 12.4 0 0 0 6 6l1.6-2.2 4.4 1.6v3.2a2 2 0 0 1-2.2 2A16.4 16.4 0 0 1 4 5.6a2 2 0 0 1 2-2.2z"/>',
    camera:  '<path d="M3.2 8.8a2 2 0 0 1 2-2h2.1l1.3-2h6.8l1.3 2h2.1a2 2 0 0 1 2 2v8.6a2 2 0 0 1-2 2H5.2a2 2 0 0 1-2-2z"/><circle cx="12" cy="13" r="3.4"/>',
    shield:  '<path d="M12 3.2 19 6v6c0 4.5-3 7.6-7 8.9-4-1.3-7-4.4-7-8.9V6z"/><path d="M9 12l2.2 2.2L15.4 10"/>',
    alert:   '<path d="M12 4 21.4 20.2H2.6z"/><path d="M12 10v4.4M12 17.4v.02"/>',
    info:    '<circle cx="12" cy="12" r="8.6"/><path d="M12 11v5.2M12 7.9v.02"/>',
    ok:      '<circle cx="12" cy="12" r="8.6"/><path d="M8.4 12.2 11 14.8l4.6-4.8"/>',
    ban:     '<circle cx="12" cy="12" r="8.6"/><path d="M6.2 6.2l11.6 11.6"/>',
    chart:   '<path d="M3.4 20.4h17.2"/><path d="M6.4 20.4v-7.2M11 20.4V5.6M15.6 20.4v-4.6M20 20.4v-9.4"/>',
    trend:   '<path d="M3.4 16.6 9.2 10.8l3.8 3.8 7.6-7.6"/><path d="M15.6 7h5v5"/>',
    pulse:   '<path d="M2.6 12h4l2.4-6 4 12 2.6-8 1.8 4h4"/>',
    sparkles:'<path d="M11.4 3.2 13 7.8l4.6 1.6-4.6 1.6-1.6 4.6-1.6-4.6L5.2 9.4l4.6-1.6z"/><path d="M18.2 14.6l.9 2.4 2.4.9-2.4.9-.9 2.4-.9-2.4-2.4-.9 2.4-.9z"/>',
    brain:   '<path d="M12 5.2a3 3 0 0 0-5.6-1.1A3 3 0 0 0 3.8 9a3.2 3.2 0 0 0 .6 5.4A3 3 0 0 0 9.4 19a2.8 2.8 0 0 0 2.6 1.8z"/><path d="M12 5.2a3 3 0 0 1 5.6-1.1A3 3 0 0 1 20.2 9a3.2 3.2 0 0 1-.6 5.4A3 3 0 0 1 14.6 19a2.8 2.8 0 0 1-2.6 1.8z"/><path d="M12 5.2v15.6"/>',
    target:  '<circle cx="12" cy="12" r="8.4"/><circle cx="12" cy="12" r="4.2"/><circle cx="12" cy="12" r=".9"/>',
    building:'<path d="M4 20.6V4.4a1 1 0 0 1 1-1h8.6a1 1 0 0 1 1 1v16.2"/><path d="M14.6 9.6h4.4a1 1 0 0 1 1 1v10"/><path d="M2.6 20.6h18.8"/><path d="M7.2 7.6h3.4M7.2 11.4h3.4M7.2 15.2h3.4M17 13.6h.02M17 17h.02"/>',
    inspect: '<circle cx="10.8" cy="10.8" r="6.8"/><path d="M20.4 20.4 16 16"/><path d="M8 10.9l2.1 2.1 3.6-3.9"/>',
    droplet: '<path d="M12 3.4s5.6 5.6 5.6 9.4a5.6 5.6 0 1 1-11.2 0c0-3.8 5.6-9.4 5.6-9.4z"/>',
    arrowUp: '<path d="M12 19.4V5.2M6.4 10.8 12 5.2l5.6 5.6"/>',
    arrowDn: '<path d="M12 4.6v14.2M6.4 13.2 12 18.8l5.6-5.6"/>',
    arrowR:  '<path d="M4.6 12h14.2M13.2 6.4 18.8 12l-5.6 5.6"/>',
    star:    '<path d="M12 3.6 14.6 9l5.9.8-4.3 4.1 1.1 5.8L12 17l-5.3 2.7 1.1-5.8L3.5 9.8 9.4 9z"/>',
    play:    '<path d="M7 4.8 19 12 7 19.2z"/>',
    pause:   '<path d="M8.6 5v14M15.4 5v14"/>',
    link:    '<path d="M10 13.4a4 4 0 0 0 5.7 0l2.8-2.8a4 4 0 1 0-5.7-5.7l-1.5 1.5"/><path d="M14 10.6a4 4 0 0 0-5.7 0L5.5 13.4a4 4 0 1 0 5.7 5.7l1.5-1.5"/>'
  };

  var ALIAS = {
    dashboard: 'grid', collection: 'package', batch: 'package', agent: 'truck',
    household: 'house', company: 'building', money: 'wallet', weight: 'scale',
    processing: 'factory', energy: 'flame', analytics: 'chart', ai: 'sparkles',
    fraud: 'shield', map: 'pin', job: 'boxes', earnings: 'cash'
  };

  function svg(name, cls) {
    var key = P[name] ? name : (ALIAS[name] && P[ALIAS[name]] ? ALIAS[name] : null);
    var body = key ? P[key] : P.info;
    return '<svg class="' + (cls || 'i') + '" viewBox="0 0 24 24" aria-hidden="true">' + body + '</svg>';
  }

  function hydrate(root) {
    var nodes = (root || document).querySelectorAll('[data-icon]');
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      var cls = el.className && el.className.baseVal !== undefined ? el.className.baseVal : (el.className || '');
      cls = String(cls).trim();
      if (cls.indexOf('i-') !== 0 && cls.indexOf(' i-') === -1 && cls.split(' ').indexOf('i') === -1) {
        cls = ('i ' + cls).trim();
      }
      var tmp = document.createElement('div');
      tmp.innerHTML = svg(el.getAttribute('data-icon'), cls);
      var node = tmp.firstChild;
      if (el.getAttribute('title')) {
        node.setAttribute('role', 'img');
        node.setAttribute('aria-label', el.getAttribute('title'));
        node.removeAttribute('aria-hidden');
      }
      el.parentNode.replaceChild(node, el);
    }
  }

  global.EC = global.EC || {};
  global.EC.icons = { svg: svg, hydrate: hydrate, paths: P };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { hydrate(); });
  } else {
    hydrate();
  }
})(window);
