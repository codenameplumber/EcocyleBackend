<!DOCTYPE html>
<html lang="en">
<head>@include('partials.head')</head>
<body class="editorial">
<div class="site-wrap">
  <header class="site-head">
    <a class="brand" href="/" style="height:auto;padding:0">
      <span class="brand-mark"><i data-icon="leaf"></i></span>
      <span class="brand-name">EcoCycle</span>
    </a>
    <div class="grow"></div>
    <button class="btn btn-icon btn-quiet" data-theme-toggle aria-label="Toggle theme"><i data-icon="moon"></i></button>
    @auth
      <a class="btn btn-primary btn-sm" href="{{ auth()->user()->role->home() }}">Open my dashboard</a>
    @else
      <a class="btn btn-ghost btn-sm" href="{{ route('login') }}">Sign in</a>
      <a class="btn btn-primary btn-sm" href="{{ route('register') }}">Get started</a>
    @endauth
  </header>

  <section class="hero-band">
    <div class="container">
      <div class="ed-split">
        <div>
          <span class="eyebrow">Waste-to-value infrastructure</span>
          <h1 class="display mt-14" style="font-size:var(--t-4xl)">Waste is not disposal.<br>It is a supply chain.</h1>
          <p class="lede mt-20 measure">
            Households generate it. Independent agents move it. The facility turns it into biogas,
            fertiliser and recovered material. EcoCycle is the record that runs underneath all three —
            so every kilogram is traced, and everybody is paid on the weight the scale actually recorded.
          </p>
          <div class="row wrap g-12 mt-24">
            <a class="btn btn-primary btn-lg" href="{{ route('register') }}">Get started</a>
            <a class="btn btn-ghost btn-lg" href="{{ route('login') }}">Sign in</a>
          </div>
        </div>
        <figure class="fig">
          <div class="media media-4x3 media-round">
            <img src="{{ asset('assets/img/hero-collection.jpg') }}" alt="A collection round in Lagos">
          </div>
          <figcaption class="fig-cap">
            Independent collectors already move most of this material. EcoCycle gives the movement a record.
          </figcaption>
        </figure>
      </div>

      <div class="ed-figures mt-40">
        <div><div class="n">{{ number_format(\App\Models\Household::count()) }}</div><div class="tiny dim mt-4">Registered households</div></div>
        <div><div class="n">{{ number_format(\App\Models\Agent::count()) }}</div><div class="tiny dim mt-4">Collection agents</div></div>
        <div><div class="n">{{ number_format(\App\Models\WasteBatch::sum('verified_kg'), 1) }}<span style="font-size:.5em"> kg</span></div><div class="tiny dim mt-4">Verified to date</div></div>
        <div><div class="n">₦{{ number_format(\App\Models\LedgerEntry::where('direction','credit')->sum('amount')) }}</div><div class="tiny dim mt-4">Settled to the chain</div></div>
      </div>
    </div>
  </section>
</div>
@include('partials.scripts')
</body>
</html>
