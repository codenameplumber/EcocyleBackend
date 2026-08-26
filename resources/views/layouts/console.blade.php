<!DOCTYPE html>
<html lang="en">
<head>@include('partials.head')</head>
<body data-app="company">

@php
    $nav = [
        'Operations' => [
            ['overview',   'console',            'grid',    'Overview',       null],
            ['pipeline',   'console.pipeline',   'route',   'Waste pipeline', \App\Models\WasteBatch::open()->count()],
            ['batches',    'console.batches',    'package', 'Batches',        null],
        ],
        'Facility' => [
            ['gate',       'console.gate',       'truck',   'Weighbridge',    \App\Models\WasteBatch::where('status', 'IN_TRANSIT')->count()],
            ['inspection', 'console.inspection', 'inspect', 'Inspection',     \App\Models\WasteBatch::awaitingInspection()->count()],
            ['processing', 'console.processing', 'factory', 'Processing',     null],
        ],
        'Network' => [
            ['households', 'console.households', 'house',   'Households',     null],
            ['agents',     'console.agents',     'truck',   'Agents',         \App\Models\Agent::where('is_online', true)->count()],
        ],
        'Finance' => [
            ['payments',   'console.payments',   'wallet',  'Settlements',    null],
        ],
        'Intelligence' => [
            ['analytics',  'console.analytics',  'chart',   'Analytics',      null],
            ['integrity',  'console.integrity',  'shield',  'Integrity',      \App\Models\IntegrityFlag::open()->count()],
        ],
    ];
    $page = $page ?? '';
@endphp

<div class="shell-desk">
    <aside class="sidebar">
        <a class="brand" href="{{ route('console') }}">
            <span class="brand-mark"><i data-icon="leaf"></i></span>
            <span>
                <span class="brand-name">EcoCycle</span><br>
                <span class="brand-sub">Facility Console</span>
            </span>
        </a>

        <nav class="nav">
            @foreach ($nav as $group => $items)
                <div class="nav-group">
                    <span class="eyebrow">{{ $group }}</span>
                    @foreach ($items as [$id, $route, $icon, $label, $count])
                        <a class="nav-link" href="{{ route($route) }}" wire:navigate
                           @if ($page === $id) aria-current="page" @endif>
                            <i data-icon="{{ $icon }}"></i>
                            <span class="grow truncate">{{ $label }}</span>
                            @if ($count)
                                <span class="nav-count {{ in_array($id, ['gate','inspection','integrity']) ? 'hot' : '' }}">{{ $count }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>

        <div class="sidebar-foot">
            <div class="row">
                <span class="avatar avatar-sm {{ auth()->user()->avatarVariant() }}">{{ auth()->user()->initials() }}</span>
                <span class="grow" style="min-width:0">
                    <span class="small strong truncate" style="display:block">{{ auth()->user()->name }}</span>
                    <span class="tiny dim">{{ auth()->user()->role->label() }}</span>
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-icon btn-quiet" title="Sign out"><i data-icon="logout" class="i i-sm"></i></button>
                </form>
            </div>
        </div>
    </aside>

    <div style="min-width:0">
        <header class="topbar">
            <button class="btn btn-icon btn-quiet menu-btn" data-menu><i data-icon="menu"></i></button>
            <h1>@yield('heading', $heading ?? 'Console')</h1>
            <div class="grow"></div>
            <button class="btn btn-icon btn-quiet" data-toast="No new alerts in the last hour" title="Notifications">
                <i data-icon="bell"></i>
            </button>
            <button class="btn btn-icon btn-quiet" data-theme-toggle aria-label="Toggle theme"><i data-icon="moon"></i></button>
        </header>

        <main class="main">
            {{ $slot ?? '' }}
            @yield('content')

            <footer class="app-foot no-print">
                <span class="row g-6"><span class="foot-dot"></span>All systems operational</span>
                <span class="sep">·</span>
                <span>{{ config('ecocycle.facility.name') }}</span>
                <span class="sep">·</span>
                <span>Intake 06:00–18:00 Mon–Sat</span>
                <span class="grow"></span>
                <span>© {{ now()->year }} EcoCycle Limited</span>
            </footer>
        </main>
    </div>
</div>

<script>
document.addEventListener('click', function (e) {
    if (e.target.closest('[data-menu]')) { document.body.classList.toggle('nav-open'); return; }
    if (document.body.classList.contains('nav-open') && !e.target.closest('.sidebar')) {
        document.body.classList.remove('nav-open');
    }
});
</script>
@include('partials.scripts')
</body>
</html>
