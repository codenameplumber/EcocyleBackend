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

        {{-- Registering and signing in are different moments and want different
             copy, so the aside is addressed by the component rather than fixed. --}}
        <div>
            @if (($aside ?? 'signin') === 'register')
                <h2 style="font-size:29px;line-height:1.2;letter-spacing:-.03em">Register once. The network does the rest.</h2>
                <ul class="mt-24 col g-14">
                    @foreach ([
                        ['<strong>Households</strong> earn 60% of every verified batch.'],
                        ['<strong>Agents</strong> keep 40% and choose their own transport.'],
                        ['Settlement is automatic once the facility verifies the weight.'],
                    ] as [$line])
                        <li class="row-top g-10">
                            <i data-icon="ok" class="i i-sm" style="margin-top:2px"></i>
                            <span style="font-size:14px;opacity:.9">{!! $line !!}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <h2 style="font-size:29px;line-height:1.2;letter-spacing:-.03em">Every kilogram accounted for, from the doorstep to the digester.</h2>
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
                        <div style="font-size:24px;font-weight:600">{{ number_format(\App\Models\WasteBatch::sum('verified_kg') / 1000, 1) }} t</div>
                        <div class="tiny" style="opacity:.75">Verified</div>
                    </div>
                </div>
            @endif
        </div>

        <p class="tiny" style="opacity:.6">
            {{ ($aside ?? 'signin') === 'register' ? 'Registration takes about two minutes.' : 'Lagos · Lagos State · Nigeria' }}
        </p>
    </aside>

    <main class="auth-form">
        <div class="auth-box" style="width:min(440px,100%)">
            <div class="between">
                <a class="row g-8 small muted" href="{{ $back ?? url('/') }}">
                    <i data-icon="chevL" class="i i-sm"></i>{{ $backLabel ?? 'Back' }}
                </a>
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
