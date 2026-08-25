<!DOCTYPE html>
<html lang="en">
<head>@include('partials.head')</head>
<body>

<div class="auth-wrap">
    <aside class="auth-aside">
        <a class="row g-10" href="{{ url('/') }}" style="color:#fff">
            <span class="brand-mark" style="background:rgba(255,255,255,.16)"><i data-icon="leaf"></i></span>
            <span class="brand-name">EcoCycle</span>
        </a>

        <div>
            <h2 style="font-size:29px">Every kilogram accounted for, from the doorstep to the digester.</h2>
            <p class="mt-16" style="opacity:.86;font-size:15px;max-width:44ch">
                One sign-in for households, collection agents and facility staff.
                Your role decides what you see.
            </p>
            <div class="row g-24 mt-32">
                <div>
                    <div style="font-size:24px;font-weight:600">{{ number_format(\App\Models\Household::count()) }}</div>
                    <div class="tiny" style="opacity:.75">Households</div>
                </div>
                <div>
                    <div style="font-size:24px;font-weight:600">{{ number_format(\App\Models\Agent::count()) }}</div>
                    <div class="tiny" style="opacity:.75">Agents</div>
                </div>
                <div>
                    <div style="font-size:24px;font-weight:600">{{ number_format(\App\Models\WasteBatch::sum('verified_kg'), 1) }} kg</div>
                    <div class="tiny" style="opacity:.75">Verified</div>
                </div>
            </div>
        </div>

        <p class="tiny" style="opacity:.6">Lagos · Lagos State · Nigeria</p>
    </aside>

    <main class="auth-form">
        <div class="auth-box" style="width:min(440px,100%)">
            <div class="between">
                <a class="row g-8 small muted" href="{{ url('/') }}"><i data-icon="chevL" class="i i-sm"></i>Back</a>
                <button class="btn btn-icon btn-quiet" data-theme-toggle aria-label="Toggle theme"><i data-icon="moon"></i></button>
            </div>

            {{ $slot ?? '' }}
            @yield('content')
        </div>
    </main>
</div>

@include('partials.scripts')
</body>
</html>
