<!DOCTYPE html>
<html lang="en">
<head>@include('partials.head')</head>
<body data-app="{{ $app ?? 'household' }}">

@php
    $app  = $app ?? 'household';
    $page = $page ?? '';
    $user = auth()->user();

    $tabs = $app === 'agent'
        ? [
            ['today',   'agent',           'home',   'Today',    null],
            ['jobs',    'agent.jobs',      'boxes',  'Jobs',     \App\Models\WasteBatch::forMarketplace()->count()],
            ['route',   'agent.route',     'route',  'Route',    null],
            ['earn',    'agent.earnings',  'cash',   'Earnings', null],
            ['profile', 'agent.profile',   'user',   'Profile',  null],
        ]
        : [
            ['home',    'app',             'home',   'Home',     null],
            ['request', 'app.request',     'plus',   'Request',  null],
            ['track',   'app.track',       'pin',    'Track',    $user?->household?->batches()->open()->count()],
            ['wallet',  'app.wallet',      'wallet', 'Wallet',   null],
            ['profile', 'app.profile',     'user',   'Profile',  null],
        ];
@endphp

<div class="shell-phone">
    <div class="phone">
        <header class="phone-top">
            @if (! empty($back))
                <a class="btn btn-icon btn-quiet" href="{{ $back }}" wire:navigate aria-label="Back"><i data-icon="chevL"></i></a>
            @else
                <span class="brand-mark" style="width:34px;height:34px"><i data-icon="leaf" class="i"></i></span>
            @endif
            <span class="grow" style="min-width:0">
                <h1 class="truncate">@yield('heading', $heading ?? 'EcoCycle')</h1>
                @if (! empty($sub))<div class="sub truncate">{{ $sub }}</div>@endif
            </span>
            <button class="btn btn-icon btn-quiet" data-theme-toggle aria-label="Toggle theme"><i data-icon="moon"></i></button>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-icon btn-quiet" title="Sign out"><i data-icon="logout"></i></button>
            </form>
            <span class="avatar avatar-sm {{ $user->avatarVariant() }}">{{ $user->initials() }}</span>
        </header>

        <div class="phone-body">
            {{ $slot ?? '' }}
            @yield('content')

            <footer class="phone-foot">
                <div class="row g-8">
                    <span class="foot-dot"></span>
                    <span>{{ $app === 'agent' ? 'Agent support' : 'Household support' }} · 0800 123 4567</span>
                </div>
                <div class="dim">© {{ now()->year }} EcoCycle Limited · Lagos, Lagos State</div>
            </footer>
        </div>

        @if (! empty($actionBar))
            <div class="action-bar">{{ $actionBar }}</div>
        @endif

        <nav class="bottom-nav">
            @foreach ($tabs as [$id, $route, $icon, $label, $badge])
                <a class="bn-link" href="{{ route($route) }}" wire:navigate
                   @if ($page === $id) aria-current="page" @endif>
                    @if ($badge)<span class="bn-badge">{{ $badge }}</span>@endif
                    <i data-icon="{{ $icon }}"></i><span>{{ $label }}</span>
                </a>
            @endforeach
        </nav>
    </div>
</div>

@include('partials.scripts')
</body>
</html>
