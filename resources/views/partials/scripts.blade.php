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
  /* ---------------------------------------------------------------- geo
     One handler for every "use my location" control in the app.

     A button carries data-ec-locate and, optionally, data-target naming the
     Livewire method to call. The component it belongs to is found by walking up
     to the nearest [wire:id], so the same button works on any screen. */
  function livewireIdFor(el) {
    var host = el.closest('[wire\\:id]');
    return host ? host.getAttribute('wire:id') : null;
  }

  function locate(btn) {
    var label = btn.querySelector('[data-ec-locate-label]') || btn;
    var original = label.textContent;
    var method = btn.getAttribute('data-target') || 'setCoords';
    var id = livewireIdFor(btn);

    if (!id) return;

    if (!navigator.geolocation) {
      if (window.EC) EC.toast('Geolocation is unavailable on this device', 'warn');
      return;
    }

    btn.disabled = true;
    label.textContent = 'Locating…';

    navigator.geolocation.getCurrentPosition(
      function (pos) {
        btn.disabled = false;
        label.textContent = original;
        // The trailing true marks this as a deliberate press rather than the
        // silent ping on load, so the component knows to confirm it.
        window.Livewire.find(id).call(
          method, pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy, true
        );
      },
      function (err) {
        btn.disabled = false;
        label.textContent = original;
        if (window.EC) {
          EC.toast(err && err.code === 1
            ? 'Location permission denied — your registered address will be used'
            : 'Could not get a location fix', 'warn');
        }
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
    );
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-ec-locate]');
    if (btn) { e.preventDefault(); locate(btn); }
  });

  /* Agent position ping.

     Screens that rank jobs by distance mark themselves with data-ec-position.
     Without this the agent's coordinates were whatever they were at signup, so
     every distance on the jobs board was measured from the wrong place. */
  function pingPosition() {
    var host = document.querySelector('[data-ec-position]');
    if (!host || !navigator.geolocation) return;

    var id = livewireIdFor(host);
    if (!id) return;

    navigator.geolocation.getCurrentPosition(
      function (pos) {
        var c = window.Livewire.find(id);
        if (c) c.call('pingPosition', pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
      },
      function () { /* denied or unavailable — the stored position still stands */ },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
    );
  }

  document.addEventListener('DOMContentLoaded', pingPosition);
  document.addEventListener('livewire:navigated', pingPosition);

  /* ----------------------------------------------------------- password eye
     Delegated so it covers every password field, including ones Livewire
     re-renders. The button reports its state, because a purely visual toggle
     tells a screen-reader user nothing about whether their password is on show. */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-ec-reveal]');
    if (!btn) return;

    e.preventDefault();

    var field = btn.closest('.field') || btn.parentElement;
    var input = field && field.querySelector('input[type="password"], input[data-ec-password]');
    if (!input) return;

    var show = input.getAttribute('type') === 'password';
    input.setAttribute('type', show ? 'text' : 'password');
    input.setAttribute('data-ec-password', '1');

    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    if (window.EC) btn.innerHTML = EC.icons.svg(show ? 'eyeOff' : 'eye', 'i i-sm');
  });

  window.addEventListener('toast', function (e) {
    var d = (e.detail && e.detail.length ? e.detail[0] : e.detail) || {};
    if (window.EC) EC.toast(d.message || 'Done', d.kind || 'ok');
  });
})();
</script>
@stack('scripts')
