<!DOCTYPE html>
<html lang="en">
<head>@include('partials.head')</head>
<body class="editorial">
<div class="site-wrap">
    <header class="site-head">
        <a class="brand" href="{{ url('/') }}" style="height:auto;padding:0">
            <span class="brand-mark"><i data-icon="leaf"></i></span>
            <span class="brand-name">EcoCycle</span>
        </a>
        <div class="grow"></div>
        <button class="btn btn-icon btn-quiet" data-theme-toggle aria-label="Toggle theme"><i data-icon="moon"></i></button>
    </header>
    <main class="section">
        <div class="container-narrow">{{ $slot ?? '' }}</div>
    </main>
</div>
@include('partials.scripts')
</body>
</html>
