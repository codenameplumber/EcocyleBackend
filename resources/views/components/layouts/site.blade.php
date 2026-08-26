@props(['title' => 'EcoCycle', 'page' => null, 'description' => null])
<!DOCTYPE html>
<html lang="en">
<head>
  @include('partials.head')
  @isset($description)<meta name="description" content="{{ $description }}">@endisset

  {{-- Public-site only. The console and the two phone apps never load this. --}}
  <link rel="stylesheet" href="{{ asset('assets/css/editorial.css') }}">
</head>
<body class="editorial">

{{-- Reading position. Decorative, so it is hidden from the accessibility tree. --}}
<div class="scroll-rail" aria-hidden="true"><i></i></div>

<div class="site-wrap">

  {{-- Header. One copy; four pages used to each keep their own and drift apart. --}}
  <header class="site-head">
    <a class="brand" href="{{ route('home') }}" style="height:auto;padding:0">
      <span class="brand-mark"><i data-icon="leaf"></i></span>
      <span class="brand-name">EcoCycle</span>
    </a>

    <nav class="site-nav">
      @foreach ([
        ['about', route('about'), 'About'],
        ['how', route('home').'#pipeline', 'How it works'],
        ['apps', route('home').'#apps', 'Apps'],
        ['facilities', route('facilities'), 'Facilities'],
        ['location', route('location'), 'Location'],
      ] as [$id, $href, $label])
        <a href="{{ $href }}" @if(($page ?? null) === $id) aria-current="page" @endif>{{ $label }}</a>
      @endforeach
    </nav>

    <div class="grow"></div>
    <button class="btn btn-icon btn-quiet" data-theme-toggle aria-label="Toggle theme"><i data-icon="moon"></i></button>

    @auth
      <a class="btn btn-primary btn-sm" href="{{ auth()->user()->role->home() }}">Open my dashboard</a>
      <form method="POST" action="{{ route('logout') }}" style="display:inline">@csrf
        <button class="btn btn-ghost btn-sm" type="submit">Sign out</button>
      </form>
    @else
      <a class="btn btn-ghost btn-sm" href="{{ route('login') }}">Sign in</a>
      <a class="btn btn-primary btn-sm" href="{{ route('register') }}">Get started</a>
    @endauth
  </header>

  <main class="site-main">
    {{ $slot }}
  </main>

  <footer class="site-foot">
    <div class="container">
      <div class="foot-top">

        <div class="foot-col">
          <div class="row g-10">
            <span class="brand-mark"><i data-icon="leaf" class="i i-sm"></i></span>
            <span class="brand-name" style="font-size:16px">EcoCycle</span>
          </div>
          <p class="small muted mt-12" style="max-width:34ch">
            Waste-to-value infrastructure. Households, agents and processors on one verified record.
          </p>
          <div class="row g-8 mt-16">
            <span class="foot-dot"></span>
            <span class="tiny dim">All systems operational</span>
          </div>
        </div>

        @foreach ([
          ['Product', [
            ['Household app', '/app'], ['Agent app', '/agent'], ['Facility console', '/console'],
            ['Intelligence', '/console/analytics'], ['Integrity review', '/console/integrity'],
          ]],
          ['Operations', [
            ['Waste pipeline', '/console/pipeline'], ['Inspection bay', '/console/inspection'],
            ['Processing lines', '/console/processing'], ['Settlements', '/console/payments'],
            ['Batch registry', '/console/batches'],
          ]],
        ] as [$heading, $items])
          <div class="foot-col">
            <h4>{{ $heading }}</h4>
            <ul>@foreach ($items as [$label, $href])<li><a href="{{ $href }}">{{ $label }}</a></li>@endforeach</ul>
          </div>
        @endforeach

        <div class="foot-col">
          <h4>Company</h4>
          <ul>
            <li><a href="{{ route('about') }}">About us</a></li>
            <li><a href="{{ route('facilities') }}">Facilities</a></li>
            <li><a href="{{ route('location') }}">Location</a></li>
            <li><a href="{{ route('register') }}">Become an agent</a></li>
            <li><a href="{{ route('register') }}">Register a household</a></li>
          </ul>
        </div>

        <div class="foot-col">
          <h4>Lagos Facility</h4>
          <ul>
            <li>Plot 14, Ojota Industrial Layout</li>
            <li>Lagos, Lagos State</li>
            <li><a href="tel:+2348001234567">0800 123 4567</a></li>
            <li><a href="mailto:ops@ecocycle.ng">ops@ecocycle.ng</a></li>
          </ul>
          <p class="tiny dim mt-10">Intake 06:00–18:00, Mon–Sat</p>
        </div>

      </div>

      <div class="foot-note mb-16">
        <strong style="color:var(--text-2)">Pilot deployment.</strong>
        Figures on this site are read live from the operating record — households, verified
        weights and settlements are counted as they happen, not illustrated. Photography is
        openly licensed; per-image credits are in
        <a href="{{ asset('assets/img/CREDITS.md') }}" style="color:var(--primary)">assets/img/CREDITS.md</a>.
      </div>

      <div class="foot-bottom">
        <span>© {{ date('Y') }} EcoCycle Limited</span>
        <span class="sep dim">·</span><span>RC 1948220</span>
        <div class="grow"></div>
        <nav class="foot-legal">
          <a href="#" data-toast="Terms are not part of the pilot">Terms</a>
          <a href="#" data-toast="Privacy notice is not part of the pilot">Privacy</a>
          <a href="#" data-toast="Collection terms are not part of the pilot">Collection terms</a>
          <a href="#" data-toast="Payout schedule is not part of the pilot">Payout schedule</a>
        </nav>
        <span class="sep dim">·</span><span>English (Nigeria) · ₦ NGN</span>
      </div>
    </div>
  </footer>

</div>

@include('partials.scripts')
<script src="{{ asset('assets/js/editorial.js') }}" defer></script>
</body>
</html>
