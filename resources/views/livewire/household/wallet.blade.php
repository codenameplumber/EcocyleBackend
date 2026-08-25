<div class="col g-16">

    <section class="hero">
        <div class="hero-label">Available balance</div>
        <div class="hero-value figure-num">₦{{ number_format($balance, 2) }}</div>
        <div class="hero-foot">
            <div><div class="k">Pending</div><div class="v figure-num">₦{{ number_format($pending, 0) }}</div></div>
            <div><div class="k">Paid out</div><div class="v figure-num">₦{{ number_format($paidOut, 0) }}</div></div>
            <div><div class="k">Lifetime</div><div class="v figure-num">{{ number_format($lifetimeKg, 0) }} kg</div></div>
        </div>
    </section>

    {{-- How the payment is worked out --}}
    <section class="card card-flat">
        <div class="card-head"><h3>How your payment is worked out</h3></div>
        <div class="card-body">
            <div class="callout callout-ok">
                <i data-icon="scale" class="i i-sm"></i>
                <div>
                    <strong>Verified weight × category rate.</strong>
                    Only the facility scale counts, never the weight the agent declared.
                </div>
            </div>
            <div class="grid grid-keep-2 mt-14" style="gap:12px">
                <div style="padding:13px 14px;background:var(--primary-soft);border-radius:var(--r-sm)">
                    <div class="tiny" style="color:var(--primary)">You · 60%</div>
                    <div class="figure-num" style="font-size:20px;color:var(--primary);margin-top:2px">
                        ₦{{ number_format($earned, 0) }}
                    </div>
                    <div class="tiny dim mt-2">earned to date</div>
                </div>
                <div style="padding:13px 14px;background:var(--info-soft);border-radius:var(--r-sm)">
                    <div class="tiny" style="color:var(--info)">Agent · 40%</div>
                    <div class="figure-num" style="font-size:20px;color:var(--info);margin-top:2px">
                        ₦{{ number_format($agentPaid, 0) }}
                    </div>
                    <div class="tiny dim mt-2">for collection and transport</div>
                </div>
            </div>

            <div class="rule mt-16 mb-12"></div>
            <div class="tiny dim">Rates by category</div>
            <div class="row g-6 wrap mt-8">
                @foreach ($rates as $r)
                    <span class="badge">
                        <span class="dot" style="background:{{ $r->color }}"></span>
                        {{ $r->label }} ₦{{ (int) $r->rate_per_kg }}
                    </span>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Collections --}}
    <section class="card card-flat">
        <div class="card-head"><h3>Collections</h3><span class="badge">{{ $batches->total() }}</span></div>

        <div class="tabs" style="padding:0 12px">
            @foreach (['all' => 'All', 'available' => 'Open', 'verified' => 'Verified', 'processed' => 'Processed', 'rejected' => 'Rejected'] as $k => $label)
                <button class="tab" wire:click="setFilter('{{ $k }}')"
                        aria-selected="{{ $filter === $k ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>

        <ul class="list">
            @forelse ($batches as $b)
                <li>
                    <div class="item">
                        <span class="thumb">
                            <img src="{{ asset('assets/img/cat-' . $b->effectiveCategory()?->key . '.jpg') }}" alt="" loading="lazy">
                        </span>
                        <span class="grow" style="min-width:0">
                            <div class="item-title">
                                {{ $b->bestKnownKg() ? number_format($b->bestKnownKg(), 1) . ' kg' : '—' }}
                                · {{ $b->effectiveCategory()?->label }}
                            </div>
                            <div class="item-sub truncate">
                                {{ optional($b->requested_at)->format('j M') }} · {{ $b->shortCode() }}
                                @if ($b->grade) · Grade {{ $b->grade }} @endif
                            </div>
                        </span>
                        <span class="item-end">
                            <div class="strong figure-num">
                                {{ $b->household_share ? '₦' . number_format((float) $b->household_share, 0) : '—' }}
                            </div>
                            <span class="st {{ $b->status->cssClass() }}">{{ $b->status->label() }}</span>
                        </span>
                    </div>
                    @if ($b->status === \App\Enums\BatchStatus::Rejected)
                        <div style="padding:0 18px 12px">
                            <div class="callout callout-warn">
                                <i data-icon="alert" class="i i-sm"></i>
                                <div><strong>Rejected.</strong> {{ $b->rejection_reason }}</div>
                            </div>
                        </div>
                    @endif
                </li>
            @empty
                <li><div class="empty">
                    <span class="empty-icon"><i data-icon="history" class="i i-lg"></i></span>
                    <div class="strong">Nothing here</div>
                    <p class="small muted">No collections match this filter.</p>
                </div></li>
            @endforelse
        </ul>

        @if ($batches->hasPages())
            <div class="card-foot">{{ $batches->links() }}</div>
        @endif
    </section>

    {{-- Ledger --}}
    <section class="card card-flat">
        <div class="card-head"><h3>Transactions</h3><span class="tiny dim">newest first</span></div>
        <ul class="list">
            @forelse ($entries as $e)
                <li>
                    <div class="item">
                        <span class="qa-ic" style="width:32px;height:32px;background:var(--{{ $e->direction->value === 'credit' ? 'primary' : 'surface-3' }}-soft)">
                            <i data-icon="{{ $e->direction->value === 'credit' ? 'arrowDn' : 'arrowUp' }}" class="i i-sm"></i>
                        </span>
                        <span class="grow" style="min-width:0">
                            <div class="item-title truncate">{{ $e->memo ?? ucfirst($e->kind) }}</div>
                            <div class="item-sub">{{ $e->created_at->diffForHumans(short: true) }}</div>
                        </span>
                        <span class="item-end strong figure-num {{ $e->direction->value === 'credit' ? 't-pos' : '' }}">
                            {{ $e->direction->value === 'credit' ? '+' : '−' }}₦{{ number_format((float) $e->amount, 2) }}
                        </span>
                    </div>
                </li>
            @empty
                <li><div class="empty">
                    <span class="empty-icon"><i data-icon="wallet" class="i i-lg"></i></span>
                    <div class="strong">No transactions yet</div>
                    <p class="small muted">Money appears once a batch is verified at the facility.</p>
                </div></li>
            @endforelse
        </ul>
    </section>
</div>
