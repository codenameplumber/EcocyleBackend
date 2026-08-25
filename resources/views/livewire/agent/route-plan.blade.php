<div class="col g-16">
    <section class="card card-flat">
        <div class="card-body">
            <div class="grid grid-keep-3" style="gap:12px">
                <div>
                    <div class="stat-value figure-num" style="font-size:20px">{{ $distance }}<small>km</small></div>
                    <div class="tiny dim">Total distance</div>
                </div>
                <div>
                    <div class="stat-value figure-num" style="font-size:20px">{{ number_format($toCollect, 1) }}<small>kg</small></div>
                    <div class="tiny dim">To collect</div>
                </div>
                <div>
                    <div class="stat-value figure-num" style="font-size:20px">{{ $stops->count() }}</div>
                    <div class="tiny dim">Stops</div>
                </div>
            </div>
        </div>
    </section>

    <section class="card card-flat">
        <div class="card-head">
            <h3>Stops</h3>
            @php $pct = (int) round(($agent->loadOnBoardKg() + $toCollect) / max(1, $agent->capacity_kg) * 100); @endphp
            <span class="badge {{ $pct > 100 ? 'badge-red' : 'badge-amber' }}">{{ $pct }}% loaded</span>
        </div>
        <ul class="list">
            @forelse ($stops as $s)
                <li>
                    <a class="item" href="{{ route('agent.pickup', $s) }}" wire:navigate>
                        <span class="qa-ic" style="width:30px;height:30px">{{ $loop->iteration }}</span>
                        <span class="grow" style="min-width:0">
                            <div class="item-title truncate">{{ $s->household->name() }}</div>
                            <div class="item-sub truncate">
                                {{ $s->household->area?->name }} ·
                                {{ number_format($s->collected_kg ?? $s->expected_kg ?? 0, 1) }} kg
                            </div>
                        </span>
                        <span class="item-end">
                            <div class="strong figure-num">
                                ₦{{ number_format(($s->collected_kg ?? $s->expected_kg ?? 0) * (float) ($s->category?->rate_per_kg ?? 0) * 0.4, 0) }}
                            </div>
                            <span class="st {{ $s->status->cssClass() }}">{{ $s->status->label() }}</span>
                        </span>
                    </a>
                </li>
            @empty
                <li>
                    <div class="empty">
                        <span class="empty-icon"><i data-icon="route" class="i i-lg"></i></span>
                        <div class="strong">No stops planned</div>
                        <p class="small muted">Accept a job and it appears on your route.</p>
                        <a class="btn btn-primary mt-8" href="{{ route('agent.jobs') }}" wire:navigate>Find jobs</a>
                    </div>
                </li>
            @endforelse
        </ul>
        @if ($stops->isNotEmpty())
            <div class="card-foot between">
                <span class="small strong">Final drop — {{ config('ecocycle.facility.name') }}</span>
                <span class="tiny dim">Ojota</span>
            </div>
        @endif
    </section>

    @if (count($load))
        <section class="card-ruled">
            <div class="block-head">
                <h3>Load through the route</h3>
                <span class="tiny dim">against {{ $agent->capacity_kg }} kg</span>
            </div>
            <svg class="chart" data-chart="bars" data-height="120" data-values="{{ implode(',', $load) }}"></svg>
            <p class="tiny dim mt-8">
                Cumulative weight on board at each stop. If it crosses your capacity, deliver before that stop.
            </p>
        </section>
    @endif
</div>
