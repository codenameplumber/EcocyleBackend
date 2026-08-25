{{--
  Only the three framework-free helpers the prototype already had.
  layout.js is deliberately NOT loaded: Blade renders the shell server-side now,
  and layout.js would try to build a second one.
--}}
<script src="{{ asset('assets/js/icons.js') }}"></script>
<script src="{{ asset('assets/js/core.js') }}"></script>
<script src="{{ asset('assets/js/charts.js') }}"></script>

@livewireScripts

<script>
(function () {
  function paint() {
    if (window.EC) {
      EC.icons.hydrate(document);
      EC.bindActions();
      if (EC.charts) EC.charts.mount();
      syncThemeGlyph();
    }
  }

  function syncThemeGlyph() {
    if (!window.EC) return;
    var dark = EC.theme() === 'dark';
    EC.$$('[data-theme-toggle]').forEach(function (b) {
      b.innerHTML = EC.icons.svg(dark ? 'sun' : 'moon', 'i');
    });
  }

  document.addEventListener('DOMContentLoaded', paint);

  // Livewire swaps DOM on every round trip, so icons and charts must be
  // re-hydrated after each update or they vanish from the replaced markup.
  document.addEventListener('livewire:navigated', paint);
  document.addEventListener('livewire:initialized', function () {
    if (window.Livewire && Livewire.hook) {
      Livewire.hook('morph.updated', function () { paint(); });
      Livewire.hook('commit', function (payload) {
        if (payload && payload.respond) payload.respond(function () { setTimeout(paint, 0); });
      });
    }
  });

  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-theme-toggle]')) setTimeout(syncThemeGlyph, 0);
  });

  // Livewire components dispatch 'toast'; the prototype already knows how to
  // draw one, so the server just says what happened.
  window.addEventListener('toast', function (e) {
    var d = (e.detail && e.detail.length ? e.detail[0] : e.detail) || {};
    if (window.EC) EC.toast(d.message || 'Done', d.kind || 'ok');
  });
})();
</script>
@stack('scripts')
