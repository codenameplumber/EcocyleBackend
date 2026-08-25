<div class="col g-16">

    {{-- Shift status --}}
    <section class="hero">
        <div class="between">
            <div>
                <div class="hero-label">Earned today</div>
                <div class="hero-value figure-num">₦{{ number_format($earnedToday, 2) }}</div>
            </div>
            <button wire:click="toggleOnline" class="col g-6" style="align-items:flex-end;background:none">
                <span class="tiny" style="opacity:.85;color:#fff">{{ $agent->is_online ? 'Online' : 'Offline' }}</span>
                <span class="track" style="background:{{ $agent->is_online ? 'rgba(255,255,255,.9)' : 'rgba(255,255,255,.32)' }};
                       width:40px;height:23px;border-radius:999px;position:relative;display:block">
                    <span style="position:absolute;top:2.5px;{{ $agent->is_online ? 'right:2.5px' : 'left:2.5px' }};
                          width:18px;height:18px;border-radius:50%;background:{{ $agent->is_online ? 'var(--primary)' : '#fff' }}"></span>
                </span>
            </button>
        </div>
        <div class="hero-foot">
            <div><div class="k">Jobs today</div><div class="v figure-num">{{ $jobsToday }}</div></div>
            <div><div class="k">This week</div><div class="v figure-num">{{ number_format($weekKg, 1) }} kg</div></div>
            <div><div class="k">Balance</div><div class="v figure-num">₦{{ number_format($balance, 0) }}</div></div>
        </div>
    </section>

    {{-- Load on board --}}
    <section class="card card-flat">
        <div class="card-head">
            <h3>Load on board</h3>
            <span class="badge {{ $onBoard / max(1, $agent->capacity_kg) > 0.85 ? 'badge-red' : 'badge-amber' }}">
                {{ (int) round($onBoard / max(1, $agent->capacity_kg) * 100) }}% full
            </span>
        </div>
        <div class="card-body">
            <div class="row g-16">
                <svg style="width:104px;flex:none" data-chart="ring"
                     data-value="{{ (int) round($onBoard / max(1, $agent->capacity_kg) * 100) }}"
                     data-label="of {{ $agent->capacity_kg }} kg"></svg>
                <div class="grow col g-8">
                    <div class="between small"><span class="muted">On board</span>
                        <span class="strong figure-num">{{ number_format($onBoard, 1) }} kg</span></div>
                    <div class="between small"><span class="muted">Remaining</span>
                        <span class="strong figure-num">{{ number_format($remaining, 1) }} kg</span></div>
                    <div class="between small"><span class="muted">Batches held</span>
                        <span class="strong figure-num">{{ $held }}</span></div>
                    <a class="btn btn-soft btn-sm mt-4" href="{{ route('agent.route') }}" wire:navigate>Head to facility</a>
                </div>
            </div>
        </div>
        <div class="media media-21x9 media-flat media-scrim">
            <img src="{{ asset('assets/img/vehicle-tricycle.jpg') }}" alt="Registered vehicle" loading="lazy">
            <div class="media-cap">
                <div class="tiny">Registered vehicle · {{ $agent->vehicle_type }} · {{ $agent->capacity_kg }} kg
                    @if ($agent->plate) · {{ $agent->plate }} @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Current job --}}
    @if ($current)
        <section class="card">
            <div class="card-head">
                <div class="row g-8">
                    <span style="width:7px;height:7px;border-radius:50%;background:var(--primary)"></span>
                    <h3>Current job</h3>
                </div>
                <span class="st {{ $current->status->cssClass() }}">{{ $current->status->label() }}</span>
            </div>
            <div class="card-body">
                <div class="row g-12">
                    <span class="avatar {{ $current->household->user->avatarVariant() }}">
                        {{ $current->household->user->initials() }}
                    </span>
                    <span class="grow">
                        <div class="item-title">{{ $current->household->name() }}</div>
                        <div class="item-sub">
                            {{ $current->household->area?->name }} ·
                            {{ $current->collected_kg ? 'declared ' . number_format($current->collected_kg, 1) . ' kg'
                               : 'est. ' . number_format($current->expected_kg, 1) . ' kg' }}
                        </div>
                    </span>
                    @if ($current->household->user->phone)
                        <a class="btn btn-icon btn-ghost" href="tel:{{ preg_replace('/\s/', '', $current->household->user->phone) }}">
                            <i data-icon="phone" class="i i-sm"></i>
                        </a>
                    @endif
                </div>
                <a class="btn btn-primary btn-block mt-14" href="{{ route('agent.pickup', $current) }}" wire:navigate>
                    Continue pickup
                </a>
            </div>
        </section>
    @endif

    {{-- Nearby jobs --}}
    <section>
        <div class="sec-head mb-12">
            <h3>Jobs near you</h3>
            <a href="{{ route('agent.jobs') }}" wire:navigate>See all</a>
        </div>
        <div class="col g-10">
            @forelse ($nearby as $j)
                @include('livewire.agent.partials.job-card', ['j' => $j, 'remaining' => $remaining])
            @empty
                <div class="empty">
                    <span class="empty-icon"><i data-icon="boxes" class="i i-lg"></i></span>
                    <div class="strong">No open jobs</div>
                    <p class="small muted">Nothing on the marketplace right now.</p>
                </div>
            @endforelse
        </div>
    </section>
</div>
