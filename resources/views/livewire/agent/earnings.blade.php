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
    {{-- Find a settlement --}}
    <section class="card card-pad">
        <div class="field">
            <label class="label">Find a batch</label>
            <div class="input-group">
                <i data-icon="search" class="i i-sm"></i>
                <input class="input mono" wire:model.live.debounce.300ms="q" placeholder="EC-W-2026-…">
            </div>
            <span class="hint">Search your own collections by tag code or household name.</span>
        </div>
    </section>

    {{-- Payout schedule --}}
    <section class="card">
        <div class="card-head">
            <h3>Payout schedule</h3>
            <a class="btn btn-sm btn-quiet" href="{{ route('agent.profile') }}#payout">Change</a>
        </div>
        <div class="card-body">
            <dl>
                <div class="kv"><dt>Cycle</dt><dd>{{ $account['cycle'] }}</dd></div>
                <div class="kv">
                    <dt>Account</dt>
                    <dd>
                        @if ($account['bank'])
                            {{ $account['bank'] }} · {{ $account['account'] }}
                        @else
                            <a style="color:var(--primary);font-weight:600"
                               href="{{ route('agent.profile') }}#payout">Add an account →</a>
                        @endif
                    </dd>
                </div>
                <div class="kv">
                    <dt>Next payout</dt>
                    <dd>{{ $nextRun->format('l') }} · <span class="figure-num">₦{{ number_format($balance, 2) }}</span></dd>
                </div>
                <div class="kv">
                    <dt>Held for review</dt>
                    <dd class="{{ $heldTotal > 0 ? 't-warn strong' : 'dim' }} figure-num">₦{{ number_format($heldTotal, 2) }}</dd>
                </div>
            </dl>

            @if ($recentPayouts->isNotEmpty())
                <div class="divider mt-16 mb-12"></div>
                <div class="tiny dim mb-8">Recent payouts</div>
                @foreach ($recentPayouts as $p)
                    <div class="between" style="padding:5px 0" wire:key="apo-{{ $p->id }}">
                        <span class="small">
                            <span class="mono">{{ $p->reference }}</span>
                            <span class="dim">· {{ $p->created_at->format('j M') }}</span>
                        </span>
                        <span class="row g-8">
                            <span class="small strong figure-num">₦{{ number_format((float) $p->amount, 2) }}</span>
                            <span class="badge {{ $p->status === 'paid' ? 'badge-green' : ($p->status === 'held' ? 'badge-amber' : '') }}">{{ $p->status }}</span>
                        </span>
                    </div>
                @endforeach
            @endif

            <p class="tiny dim mt-14">
                Your share is 40% of every batch's verified value. It is credited the moment the gate
                scale reading is entered, and paid on the next run — a declared weight never moves money.
            </p>
        </div>
    </section>
</div>
