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

    <div class="row g-10">
        <button class="btn btn-primary grow" wire:click="openWithdraw" @disabled($balance <= 0)>
            <i data-icon="download" class="i i-sm"></i> Withdraw
        </button>
        <button class="btn btn-ghost grow" data-toast="A statement is emailed from the facility on request">
            <i data-icon="link" class="i i-sm"></i> Statement
        </button>
    </div>

    {{-- Monthly earnings --}}
    <section class="card">
        <div class="card-head">
            <h3>Monthly earnings</h3>
            @if ($monthly['delta'] !== null)
                <span class="badge {{ $monthly['delta'] >= 0 ? 'badge-green' : 'badge-amber' }}">
                    {{ $monthly['delta'] >= 0 ? '+' : '' }}{{ number_format($monthly['delta'], 1) }}%
                </span>
            @else
                <span class="badge">8 months</span>
            @endif
        </div>
        <div class="card-body">
            <svg class="chart" data-chart="area" data-height="170"
                 data-values="{{ implode(',', $monthly['values']) }}"
                 data-labels="{{ implode(',', $monthly['labels']) }}"></svg>
        </div>
    </section>

    {{-- Payout method --}}
    <section class="card">
        <div class="card-head">
            <h3>Payout method</h3>
            <a class="btn btn-sm btn-quiet" href="{{ route('app.profile') }}#payout">Change</a>
        </div>
        <div class="card-body">
            @if ($account['bank'])
                <div class="row g-12">
                    <span class="qa-ic v2" style="background:var(--info-soft);color:var(--info)">
                        <i data-icon="cash" class="i i-sm"></i>
                    </span>
                    <span class="grow" style="min-width:0">
                        <div class="small strong">{{ $account['bank'] }} · {{ $account['account'] }}</div>
                        <div class="tiny dim">{{ auth()->user()->name }} · settles within 24 hours of a payment run</div>
                    </span>
                    <span class="badge badge-green">Default</span>
                </div>
                <div class="tiny dim mt-14">
                    {{ $account['auto']
                        ? 'Paid out automatically once the balance passes ₦' . number_format($account['threshold'], 0) . '.'
                        : 'Manual — press Withdraw to open a payout.' }}
                </div>
            @else
                <div class="callout callout-warn">
                    <i data-icon="alert" class="i i-sm"></i>
                    <div><strong>No payout account on file.</strong> Add one before requesting a withdrawal.</div>
                </div>
                {{-- Withdraw refuses without an account, so the refusal has to
                     lead somewhere rather than just stating the problem. --}}
                <a class="btn btn-primary btn-block mt-12" href="{{ route('app.profile') }}#payout">
                    <i data-icon="plus" class="i i-sm"></i> Add a payout account
                </a>
            @endif

            @if ($payouts->isNotEmpty())
                <div class="divider mt-16 mb-12"></div>
                <div class="tiny dim mb-8">Recent payouts</div>
                @foreach ($payouts as $p)
                    <div class="between" style="padding:5px 0" wire:key="po-{{ $p->id }}">
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
    {{-- Withdraw --}}
    @if ($showWithdraw)
        <div class="scrim" wire:click.self="$set('showWithdraw', false)">
            <div class="modal" role="dialog" aria-modal="true">
                <div class="card-head">
                    <h3>Withdraw</h3>
                    <button class="btn btn-icon btn-quiet" wire:click="$set('showWithdraw', false)" aria-label="Close">
                        <i data-icon="close"></i>
                    </button>
                </div>
                <div class="card-body col g-14">
                    <div class="grid grid-2" style="gap:12px">
                        <div>
                            <div class="tiny dim">Available</div>
                            <div class="stat-value figure-num" style="font-size:20px">₦{{ number_format($balance, 2) }}</div>
                        </div>
                        <div>
                            <div class="tiny dim">Still pending</div>
                            <div class="stat-value figure-num" style="font-size:20px">₦{{ number_format($pending, 2) }}</div>
                        </div>
                    </div>

                    <div class="field">
                        <label class="label">Amount</label>
                        <div class="input-group">
                            <span class="input-prefix">₦</span>
                            <input class="input figure-num" value="{{ number_format($balance, 2, '.', '') }}" readonly>
                        </div>
                        <span class="hint">
                            Payouts settle the whole available balance. Splitting one would leave credits
                            half-attached, and the ledger has no way to express that.
                        </span>
                    </div>

                    <div class="field">
                        <label class="label">Destination</label>
                        <select class="select" disabled>
                            <option>
                                {{ $account['bank'] ? $account['bank'].' · '.$account['account'] : 'No account on file' }}
                            </option>
                        </select>
                        <span class="hint">Change this from your profile.</span>
                    </div>

                    <div class="callout">
                        <i data-icon="info" class="i i-sm"></i>
                        <div class="tiny">
                            This opens a payout for the next payment run. The money leaves EcoCycle when
                            the facility marks it paid — usually within 24 hours.
                        </div>
                    </div>
                </div>
                <div class="card-foot row" style="justify-content:flex-end">
                    <button class="btn btn-ghost" wire:click="$set('showWithdraw', false)">Cancel</button>
                    <button class="btn btn-primary" wire:click="requestPayout" @disabled(! $account['bank'])>
                        <span wire:loading.remove wire:target="requestPayout">Withdraw ₦{{ number_format($balance, 2) }}</span>
                        <span wire:loading wire:target="requestPayout">Opening…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
