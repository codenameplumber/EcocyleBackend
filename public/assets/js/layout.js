/* ==========================================================================
   EcoCycle — layout.js
   Renders the app shell from body attributes so no page repeats chrome.
   These map 1:1 onto Blade layouts later (layouts.company / layouts.phone).

     <body data-app="company"   data-page="overview" data-title="Overview">
     <body data-app="household" data-page="home" data-title="Good afternoon"
           data-sub="Nkechi Obiora" data-back="index.html">

   Page markup supplies only:
     .main        (company)                    — content region
     .phone-body  (household / agent)          — scrollable content
     .action-bar  (optional, phone)            — sticky footer actions
   ========================================================================== */
(function (global) {
  'use strict';
  var EC = global.EC = global.EC || {};

  var NAV = {
    company: {
      brand: 'EcoCycle', sub: 'Facility Console', root: '../index.html',
      groups: [
        { label: 'Operations', items: [
          { id: 'overview',   href: 'index.html',      icon: 'grid',    label: 'Overview' },
          { id: 'pipeline',   href: 'pipeline.html',   icon: 'route',   label: 'Waste pipeline', count: '284' },
          { id: 'batches',    href: 'batches.html',    icon: 'package', label: 'Batches' }
        ]},
        { label: 'Facility', items: [
          { id: 'inspection', href: 'inspection.html', icon: 'inspect', label: 'Inspection', count: '9', hot: true },
          { id: 'processing', href: 'processing.html', icon: 'factory', label: 'Processing' }
        ]},
        { label: 'Network', items: [
          { id: 'households', href: 'households.html', icon: 'house',   label: 'Households' },
          { id: 'agents',     href: 'agents.html',     icon: 'truck',   label: 'Agents', count: '289' }
        ]},
        { label: 'Finance', items: [
          { id: 'payments',   href: 'payments.html',   icon: 'wallet',  label: 'Settlements' }
        ]},
        { label: 'Intelligence', items: [
          { id: 'analytics',  href: 'analytics.html',  icon: 'chart',   label: 'Analytics' },
          { id: 'integrity',  href: 'integrity.html',  icon: 'shield',  label: 'Integrity', count: '5', hot: true }
        ]}
      ]
    },
    household: {
      brand: 'EcoCycle', root: '../index.html',
      tabs: [
        { id: 'home',    href: 'index.html',    icon: 'home',    label: 'Home' },
        { id: 'request', href: 'request.html',  icon: 'plus',    label: 'Request' },
        { id: 'track',   href: 'track.html',    icon: 'pin',     label: 'Track', badge: '1' },
        { id: 'wallet',  href: 'earnings.html', icon: 'wallet',  label: 'Wallet' },
        { id: 'profile', href: 'profile.html',  icon: 'user',    label: 'Profile' }
      ]
    },
    agent: {
      brand: 'EcoCycle', root: '../index.html',
      tabs: [
        { id: 'today',   href: 'index.html',    icon: 'home',    label: 'Today' },
        { id: 'jobs',    href: 'jobs.html',     icon: 'boxes',   label: 'Jobs', badge: '6' },
        { id: 'route',   href: 'route.html',    icon: 'route',   label: 'Route' },
        { id: 'earn',    href: 'earnings.html', icon: 'cash',    label: 'Earnings' },
        { id: 'profile', href: 'profile.html',  icon: 'user',    label: 'Profile' }
      ]
    }
  };

  function icon(name, cls) { return EC.icons.svg(name, cls || 'i'); }

  var YEAR = 2026;   /* the fixtures are dated 2026; keep the chrome consistent */

  /* --- Marketing site chrome ----------------------------------------------
     index / about / facilities / location share one header and one footer.
     They are rendered here for the same reason the console sidebar is: four
     copies of the same markup drift apart the moment one of them is edited. */
  var SITE = {
    nav: [
      { id: 'about',      href: 'about.html',          label: 'About' },
      { id: 'how',        href: 'index.html#pipeline', label: 'How it works' },
      { id: 'apps',       href: 'index.html#apps',     label: 'Apps' },
      { id: 'facilities', href: 'facilities.html',     label: 'Facilities' },
      { id: 'location',   href: 'location.html',       label: 'Location' }
    ],
    cols: [
      { h: 'Product', items: [
        ['Household app', 'household/index.html'], ['Agent app', 'agent/index.html'],
        ['Facility console', 'company/index.html'], ['Intelligence', 'company/analytics.html'],
        ['Integrity review', 'company/integrity.html']
      ]},
      { h: 'Operations', items: [
        ['Waste pipeline', 'company/pipeline.html'], ['Inspection bay', 'company/inspection.html'],
        ['Processing lines', 'company/processing.html'], ['Settlements', 'company/payments.html'],
        ['Batch registry', 'company/batches.html']
      ]},
      { h: 'Company', items: [
        ['About us', 'about.html'], ['Facilities', 'facilities.html'],
        ['Location', 'location.html'], ['Become an agent', 'auth/register.html'],
        ['Register a household', 'auth/register.html']
      ]}
    ]
  };

  function siteHead(page) {
    return '<header class="site-head">' +
      '<a class="brand" href="index.html" style="height:auto;padding:0">' +
        '<span class="brand-mark">' + icon('leaf') + '</span>' +
        '<span class="brand-name">EcoCycle</span></a>' +
      '<nav class="site-nav">' +
        SITE.nav.map(function (n) {
          return '<a href="' + n.href + '"' + (n.id === page ? ' aria-current="page"' : '') + '>' + n.label + '</a>';
        }).join('') +
      '</nav>' +
      '<div class="grow"></div>' + themeBtn() +
      '<a class="btn btn-ghost btn-sm" href="auth/login.html">Sign in</a>' +
      '<a class="btn btn-primary btn-sm" href="auth/register.html">Get started</a>' +
    '</header>';
  }

  function siteFoot() {
    var cols = SITE.cols.map(function (c) {
      return '<div class="foot-col"><h4>' + c.h + '</h4><ul>' +
        c.items.map(function (it) { return '<li><a href="' + it[1] + '">' + it[0] + '</a></li>'; }).join('') +
        '</ul></div>';
    }).join('');

    return '<footer class="site-foot">' +
      '<div class="container">' +
        '<div class="foot-top">' +
          '<div class="foot-col">' +
            '<div class="row g-10"><span class="brand-mark">' + icon('leaf', 'i i-sm') + '</span>' +
            '<span class="brand-name" style="font-size:16px">EcoCycle</span></div>' +
            '<p class="small muted mt-12" style="max-width:34ch">Waste-to-value infrastructure. ' +
            'Households, agents and processors on one verified record.</p>' +
            '<div class="row g-8 mt-16"><span class="foot-dot"></span>' +
            '<span class="tiny dim">All systems operational</span></div>' +
          '</div>' + cols +
          '<div class="foot-col"><h4>Lagos Facility</h4><ul>' +
            '<li>Plot 14, Ojota Industrial Layout</li>' +
            '<li>Lagos, Lagos State</li>' +
            '<li><a href="tel:+2348001234567">0800 123 4567</a></li>' +
            '<li><a href="mailto:ops@ecocycle.ng">ops@ecocycle.ng</a></li>' +
          '</ul><p class="tiny dim mt-10">Intake 06:00–18:00, Mon–Sat</p></div>' +
        '</div>' +

        '<div class="foot-note mb-16">' +
          '<strong style="color:var(--text-2)">This is a design prototype.</strong> ' +
          'There is no server, no database and no real authentication behind these screens. ' +
          'Figures are fixtures. Photography is openly licensed — see ' +
          '<a href="assets/img/CREDITS.md" style="color:var(--primary)">assets/img/CREDITS.md</a> ' +
          'for per-image credits.</div>' +

        '<div class="foot-bottom">' +
          '<span>© ' + YEAR + ' EcoCycle Limited</span><span class="sep dim">·</span><span>RC 1948220</span>' +
          '<div class="grow"></div>' +
          '<nav class="foot-legal">' +
            '<a href="#" data-toast="Terms are not part of this prototype">Terms</a>' +
            '<a href="#" data-toast="Privacy notice is not part of this prototype">Privacy</a>' +
            '<a href="#" data-toast="Collection terms are not part of this prototype">Collection terms</a>' +
            '<a href="#" data-toast="Payout schedule is not part of this prototype">Payout schedule</a>' +
          '</nav>' +
          '<span class="sep dim">·</span><span>English (Nigeria) · ₦ NGN</span>' +
        '</div>' +
      '</div>' +
    '</footer>';
  }

  function renderSite(body, page) {
    var main = body.querySelector('.site-main');
    var wrap = document.createElement('div');
    wrap.className = 'site-wrap';
    wrap.innerHTML = siteHead(page);
    if (main) wrap.appendChild(main);
    wrap.insertAdjacentHTML('beforeend', siteFoot());
    body.insertBefore(wrap, body.firstChild);
  }

  /* --- Footers -------------------------------------------------------------
     Rendered by the shell so no page has to repeat them. The console gets a
     status rail (what an operator needs to know at a glance); the phone apps
     get a short legal block at the end of the scroll.                        */
  function companyFoot() {
    return '<footer class="app-foot no-print">' +
      '<span class="row g-6"><span class="foot-dot"></span>All systems operational</span>' +
      '<span class="sep">·</span>' +
      '<span>Lagos Facility · Ojota, Lagos</span>' +
      '<span class="sep">·</span>' +
      '<span>Intake 06:00–18:00 Mon–Sat</span>' +
      '<span class="grow"></span>' +
      '<span>Shift supervisor <strong>A. Okafor</strong></span>' +
      '<span class="sep">·</span>' +
      '<a href="../index.html">About EcoCycle</a>' +
      '<span class="sep">·</span>' +
      '<span>© ' + YEAR + ' EcoCycle Limited</span>' +
    '</footer>';
  }

  function phoneFoot(app) {
    var help = app === 'agent'
      ? 'Agent support · 0800 123 4568'
      : 'Household support · 0800 123 4567';
    return '<footer class="phone-foot">' +
      '<div class="row g-8">' +
        '<span class="foot-dot"></span>' +
        '<span>' + help + '</span>' +
      '</div>' +
      '<nav class="phone-foot-links">' +
        '<a href="../index.html">About</a>' +
        '<a href="#" data-toast="Collection terms are not part of this prototype">Collection terms</a>' +
        '<a href="#" data-toast="Payout schedule is not part of this prototype">Payouts</a>' +
        '<a href="#" data-toast="Privacy notice is not part of this prototype">Privacy</a>' +
      '</nav>' +
      '<div class="dim">© ' + YEAR + ' EcoCycle Limited · Lagos, Lagos State</div>' +
    '</footer>';
  }

  function themeBtn() {
    return '<button class="btn btn-icon btn-quiet" data-theme-toggle title="Toggle theme" aria-label="Toggle theme">' +
           icon('moon', 'i') + '</button>';
  }

  /* --- Company shell ------------------------------------------------------ */
  function renderCompany(body, cfg, page) {
    var main = body.querySelector('.main');
    var title = body.getAttribute('data-title') || '';

    var nav = cfg.groups.map(function (g) {
      return '<div class="nav-group"><span class="eyebrow">' + g.label + '</span>' +
        g.items.map(function (it) {
          return '<a class="nav-link" href="' + it.href + '"' + (it.id === page ? ' aria-current="page"' : '') + '>' +
                 icon(it.icon) + '<span class="grow truncate">' + it.label + '</span>' +
                 (it.count ? '<span class="nav-count' + (it.hot ? ' hot' : '') + '">' + it.count + '</span>' : '') +
                 '</a>';
        }).join('') + '</div>';
    }).join('');

    var sidebar =
      '<aside class="sidebar">' +
        '<a class="brand" href="' + cfg.root + '">' +
          '<span class="brand-mark">' + icon('leaf', 'i') + '</span>' +
          '<span><span class="brand-name">' + cfg.brand + '</span><br><span class="brand-sub">' + cfg.sub + '</span></span>' +
        '</a>' +
        '<nav class="nav">' + nav + '</nav>' +
        '<div class="sidebar-foot">' +
          '<div class="row">' +
            '<span class="avatar avatar-sm v4">AO</span>' +
            '<span class="grow" style="min-width:0"><span class="small strong truncate" style="display:block">Adaeze Okafor</span>' +
            '<span class="tiny dim">Facility manager</span></span>' +
            '<a class="btn btn-icon btn-quiet" href="../auth/login.html" title="Sign out">' + icon('logout', 'i i-sm') + '</a>' +
          '</div>' +
        '</div>' +
      '</aside>';

    var topbar =
      '<header class="topbar">' +
        '<button class="btn btn-icon btn-quiet menu-btn" data-menu>' + icon('menu') + '</button>' +
        '<h1>' + title + '</h1>' +
        '<div class="grow"></div>' +
        '<div class="topbar-search input-group no-print" style="width:260px">' +
          icon('search', 'i i-sm') +
          '<input class="input" placeholder="Search batch, household, agent…" aria-label="Search">' +
        '</div>' +
        '<button class="btn btn-icon btn-quiet" data-toast="No new alerts in the last hour" title="Notifications">' + icon('bell') + '</button>' +
        themeBtn() +
      '</header>';

    var wrap = document.createElement('div');
    wrap.className = 'shell-desk';
    wrap.innerHTML = sidebar + '<div style="min-width:0">' + topbar + '</div>';
    wrap.lastChild.appendChild(main);
    main.insertAdjacentHTML('beforeend', companyFoot());
    body.insertBefore(wrap, body.firstChild);

    var mb = wrap.querySelector('[data-menu]');
    if (mb) mb.addEventListener('click', function () { document.body.classList.toggle('nav-open'); });
    document.addEventListener('click', function (e) {
      if (document.body.classList.contains('nav-open') &&
          !e.target.closest('.sidebar') && !e.target.closest('[data-menu]')) {
        document.body.classList.remove('nav-open');
      }
    });
  }

  /* --- Phone shell -------------------------------------------------------- */
  function renderPhone(body, cfg, page, app) {
    var content = body.querySelector('.phone-body');
    var actionBar = body.querySelector('.action-bar');
    var title = body.getAttribute('data-title') || '';
    var sub = body.getAttribute('data-sub') || '';
    var back = body.getAttribute('data-back');
    var avatarName = app === 'agent' ? EC.data.me_agent.name : EC.data.me_household.name;

    var top =
      '<header class="phone-top">' +
        (back
          ? '<a class="btn btn-icon btn-quiet" href="' + back + '" aria-label="Back">' + icon('chevL') + '</a>'
          : '<span class="brand-mark" style="width:34px;height:34px">' + icon('leaf', 'i') + '</span>') +
        '<span class="grow" style="min-width:0">' +
          '<h1 class="truncate">' + title + '</h1>' +
          (sub ? '<div class="sub truncate">' + sub + '</div>' : '') +
        '</span>' +
        themeBtn() +
        '<a class="btn btn-icon btn-quiet" href="notifications.html" aria-label="Notifications">' + icon('bell') + '</a>' +
        '<span class="avatar avatar-sm ' + EC.variant(avatarName) + '">' + EC.initials(avatarName) + '</span>' +
      '</header>';

    var tabs =
      '<nav class="bottom-nav">' +
        cfg.tabs.map(function (t) {
          return '<a class="bn-link" href="' + t.href + '"' + (t.id === page ? ' aria-current="page"' : '') + '>' +
                 (t.badge ? '<span class="bn-badge">' + t.badge + '</span>' : '') +
                 icon(t.icon) + '<span>' + t.label + '</span></a>';
        }).join('') +
      '</nav>';

    var wrap = document.createElement('div');
    wrap.className = 'shell-phone';
    wrap.innerHTML = '<div class="phone">' + top + '</div>';
    var phone = wrap.firstChild;
    /* The footer belongs inside the scrolling body, not pinned below the tabs. */
    content.insertAdjacentHTML('beforeend', phoneFoot(app));
    phone.appendChild(content);
    if (actionBar) phone.appendChild(actionBar);
    phone.insertAdjacentHTML('beforeend', tabs);
    body.insertBefore(wrap, body.firstChild);
  }

  function boot() {
    var body = document.body;
    var site = body.getAttribute('data-site');
    var app = body.getAttribute('data-app');
    if (!site && (!app || !NAV[app])) return;
    var page = body.getAttribute('data-page');

    if (site) renderSite(body, site);
    else if (app === 'company') renderCompany(body, NAV.company, page);
    else renderPhone(body, NAV[app], page, app);

    EC.icons.hydrate(document);
    if (EC.charts) EC.charts.mount();
    if (EC.bindActions) EC.bindActions();

    /* Theme button icon reflects the state it switches TO. */
    function syncThemeIcons() {
      var dark = EC.theme() === 'dark';
      EC.$$('[data-theme-toggle]').forEach(function (b) {
        b.innerHTML = EC.icons.svg(dark ? 'sun' : 'moon', 'i');
      });
    }
    syncThemeIcons();
    EC.$$('[data-theme-toggle]').forEach(function (b) {
      b.addEventListener('click', function () { setTimeout(syncThemeIcons, 0); });
    });
  }

  EC.layout = { NAV: NAV, SITE: SITE, boot: boot };
  document.addEventListener('DOMContentLoaded', boot);
})(window);
