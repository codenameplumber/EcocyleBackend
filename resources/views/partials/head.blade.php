<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? trim($__env->yieldContent('title')) ?: 'EcoCycle' }}</title>

{{-- The prototype's design system, carried over unchanged. --}}
<link rel="stylesheet" href="{{ asset('assets/css/base.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/components.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/layout.css') }}">

{{-- Additions made in this build, kept out of the three files above so those
     stay comparable with the prototype they came from. --}}
<link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">

{{-- Theme is applied before first paint so a dark-mode user never sees a flash. --}}
<script>
(function () {
  try {
    var t = localStorage.getItem('ec.theme');
    if (!t && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) t = 'dark';
    document.documentElement.setAttribute('data-theme', t || 'light');
  } catch (e) {}
})();
</script>
@livewireStyles
