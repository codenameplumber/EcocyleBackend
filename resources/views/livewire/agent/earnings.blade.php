<div class="col g-16">
    <section class="hero">
        <div class="hero-label">Available to cash out</div>
        <div class="hero-value figure-num">₦{{ number_format($balance, 2) }}</div>
        <div class="hero-foot">
            <div><div class="k">Pending</div><div class="v figure-num">₦{{ number_format($pending, 0) }}</div></div>
            <div><div class="k">Earned</div><div class="v figure-num">₦{{ number_format($earned, 0) }}</div></div>
            <div><div class="k">Trips</div><div class="v figure-num">{{ $trips }}</div></div>
        </div>
    </section>

    <button class="btn btn-primary btn-block" wire:click="cashOut" wire:loading.attr="disabled">
        <i data-icon="download" class="i i-sm"></i>
        <span wire:loading.remove wire:target="cashOut">Cash out ₦{{ number_format($balance, 2) }}</span>
        <span wire:loading wire:target="cashOut">Queueing…</span>
    </button>

    <section class="card-ruled">
        <div class="block-head"><h3>Efficiency</h3><span class="badge badge-green">{{ $accuracy }}% accurate</span></div>
        <div class="col g-12">
            <div>
                <div class="between mb-4"><span class="tiny dim">Weight accuracy</span>
                    <span class="small strong figure-num">{{ $accuracy }}%</span></div>
                <div class="bar {{ $accuracy < 95 ? 'amber' : '' }}"><span style="width:{{ $accuracy }}%"></span></div>
            </div>
            <div>
                <div class="between mb-4"><span class="tiny dim">Integrity score</span>
                    <span class="small strong figure-num">{{ $agent->integrity_score }}</span></div>
                <div class="bar"><span style="width:{{ $agent->integrity_score }}%"></span></div>
            </div>
        </div>
        <p class="tiny dim mt-12">
            Weight accuracy compares what you declare against what the facility scale verifies.
            Above 95% keeps you in the priority matching pool.
        </p>
    </section>

    <section class="card-ruled">
        <div class="block-head"><h3>Last 12 weeks</h3><span class="tiny dim">kg verified</span></div>
        <svg class="chart" data-chart="bars" data-height="130" data-values="{{ $weeks->implode(',') }}"></svg>
    </section>

    <section class="card card-flat">
        <div class="card-head"><h3>Recent batches</h3><span class="tiny dim">your 40% share</span></div>
        <ul class="list">
            @forelse ($recent as $b)
                <li><div class="item">
                    <span class="thumb"><img src="{{ asset('assets/img/cat-' . $b->effectiveCategory()?->key . '.jpg') }}" alt="" loading="lazy"></span>
                    <span class="grow" style="min-width:0">
                        <div class="item-title truncate">{{ $b->household->name() }} · {{ $b->effectiveCategory()?->label }}</div>
                        <div class="item-sub truncate">{{ $b->shortCode() }} · verified {{ number_format($b->verified_kg, 1) }} kg</div>
                    </span>
                    <span class="item-end">
                        <div class="strong figure-num">₦{{ number_format((float) $b->agent_share, 0) }}</div>
                        <span class="st {{ $b->status->cssClass() }}">{{ $b->status->label() }}</span>
                    </span>
                </div></li>
            @empty
                <li><div class="empty"><span class="empty-icon"><i data-icon="cash" class="i i-lg"></i></span>
                    <div class="strong">No verified batches yet</div>
                    <p class="small muted">Earnings appear once the facility weighs a delivery.</p></div></li>
            @endforelse
        </ul>
    </section>

    @if ($payouts->isNotEmpty())
        <section class="card card-flat">
            <div class="card-head"><h3>Payouts</h3></div>
            <ul class="list">
                @foreach ($payouts as $p)
                    <li><div class="item">
                        <span class="grow"><div class="item-title mono">{{ $p->reference }}</div>
                            <div class="item-sub">{{ $p->method }} · {{ $p->created_at->format('j M') }}</div></span>
                        <span class="item-end">
                            <div class="strong figure-num">₦{{ number_format((float) $p->amount, 2) }}</div>
                            <span class="badge {{ $p->badgeClass() }}">{{ $p->status }}</span>
                        </span>
                    </div></li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
