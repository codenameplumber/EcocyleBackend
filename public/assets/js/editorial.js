/* ==========================================================================
   EcoCycle — editorial.js
   Public-site enhancement layer. Framework-free, matching the three helpers the
   prototype already shipped.

   Every behaviour here is additive: if this file fails to load, or the reader
   has asked for reduced motion, the page still renders complete and readable.
   Nothing below is required to see the content.
   ========================================================================== */
(function () {
  'use strict';

  var reduced = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* --- Scroll progress + sticky CTA ------------------------------------- */
  function scrollUi() {
    var bar = document.querySelector('.scroll-rail > i');
    var dock = document.querySelector('.cta-dock');
    var hero = document.querySelector('.cine');
    if (!bar && !dock) return;

    var ticking = false;

    function paint() {
      ticking = false;
      var doc = document.documentElement;
      var max = doc.scrollHeight - doc.clientHeight;

      if (bar) bar.style.width = (max > 0 ? (doc.scrollTop / max) * 100 : 0) + '%';

      // The dock is the hero's CTAs following the reader down. It has no reason
      // to exist while the real ones are still on screen.
      if (dock && hero) {
        dock.classList.toggle('is-up', doc.scrollTop > hero.offsetHeight * 0.9);
      }
    }

    addEventListener('scroll', function () {
      if (!ticking) { ticking = true; requestAnimationFrame(paint); }
    }, { passive: true });

    paint();
  }

  /* --- Reveal on scroll --------------------------------------------------
     The class is added by JS, so markup that never meets an observer is simply
     visible. Elements are un-observed once shown: a reveal that re-runs on the
     way back up reads as a glitch, not an effect. */
  function reveal() {
    var nodes = document.querySelectorAll('[data-reveal]');
    if (!nodes.length) return;

    if (reduced || !('IntersectionObserver' in window)) return;

    nodes.forEach(function (el) { el.classList.add('ed-reveal'); });

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        var stagger = parseInt(e.target.getAttribute('data-reveal'), 10);
        e.target.style.setProperty('--d', (stagger > 0 ? stagger : 0) + 'ms');
        e.target.classList.add('is-in');
        io.unobserve(e.target);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.06 });

    nodes.forEach(function (el) { io.observe(el); });
  }

  /* --- Count-up ----------------------------------------------------------
     Counts to the figure already rendered in the markup, never to one fetched
     here — the server's number is the number, the animation only delays it. */
  function countUp() {
    var nodes = document.querySelectorAll('[data-countup]');
    if (!nodes.length) return;

    if (reduced || !('IntersectionObserver' in window)) return;

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        run(e.target);
        io.unobserve(e.target);
      });
    }, { threshold: 0.4 });

    nodes.forEach(function (el) { io.observe(el); });

    function run(el) {
      var target = parseFloat(el.getAttribute('data-countup'));
      if (!isFinite(target)) return;

      var dp = parseInt(el.getAttribute('data-decimals') || '0', 10);
      var suffix = el.getAttribute('data-suffix') || '';
      var final = el.textContent;
      var t0 = null;
      var dur = 1150;

      function frame(ts) {
        if (t0 === null) t0 = ts;
        var p = Math.min(1, (ts - t0) / dur);
        var eased = 1 - Math.pow(1 - p, 3);          // easeOutCubic
        var v = target * eased;

        el.textContent = v.toLocaleString('en-NG', {
          minimumFractionDigits: dp, maximumFractionDigits: dp
        }) + suffix;

        if (p < 1) requestAnimationFrame(frame);
        else el.textContent = final;                 // land on the server's own string
      }

      requestAnimationFrame(frame);
    }
  }

  /* --- Pointer sheen on the bento cards ---------------------------------- */
  function sheen() {
    if (reduced) return;
    if (!matchMedia('(hover: hover) and (pointer: fine)').matches) return;

    document.querySelectorAll('.bento-card').forEach(function (card) {
      card.addEventListener('pointermove', function (e) {
        var r = card.getBoundingClientRect();
        card.style.setProperty('--mx', ((e.clientX - r.left) / r.width) * 100 + '%');
        card.style.setProperty('--my', ((e.clientY - r.top) / r.height) * 100 + '%');
      });
    });
  }

  function boot() { scrollUi(); reveal(); countUp(); sheen(); }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
