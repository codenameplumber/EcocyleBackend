<div>
    <div class="page-head">
        <div>
            <h1>Settlements</h1>
            <p class="muted">Nothing settles on a declaration. Verified weight only.</p>
        </div>
        <div class="row g-10 no-print">
            <button class="btn btn-ghost" wire:click="exportCsv">
                <i data-icon="download" class="i i-sm"></i> Export pending CSV
            </button>
            <button class="btn btn-primary" wire:click="runPayouts" wire:loading.attr="disabled">
                <i data-icon="wallet" class="i i-sm"></i>
                <span wire:loading.remove wire:target="runPayouts">Run payout batch</span>
                <span wire:loading wire:target="runPayouts">Working…</span>
            </button>
        </div>
    </div>

    <section class="grid grid-4 mb-16">
        <div class="card stat tone-green">
            <div class="stat-top"><span class="stat-label">Credited this month</span>
                <span class="stat-icon"><i data-icon="wallet" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">₦{{ number_format($settledMonth, 0) }}</div>
            <div class="stat-foot">to households and agents</div>
        </div>
        <div class="card stat tone-amber">
            <div class="stat-top"><span class="stat-label">Awaiting payout</span>
                <span class="stat-icon"><i data-icon="clock" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">₦{{ number_format($unpaidCredits, 0) }}</div>
            <div class="stat-foot">credited but not yet transferred</div>
        </div>
        <div class="card stat tone-blue">
            <div class="stat-top"><span class="stat-label">Pending payouts</span>
                <span class="stat-icon"><i data-icon="cash" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">₦{{ number_format($pendingTotal, 0) }}</div>
            <div class="stat-foot">queued for bank transfer</div>
        </div>
        <div class="card stat tone-cyan">
            <div class="stat-top"><span class="stat-label">Paid to date</span>
                <span class="stat-icon"><i data-icon="ok" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">₦{{ number_format($paidTotal, 0) }}</div>
            <div class="stat-foot">confirmed transfers</div>
        </div>
    </section>

    <section class="card mb-16">
        <div class="card-head"><h3>The 60/40 split</h3><span class="badge">how every batch settles</span></div>
        <div class="card-body">
            <div class="callout">
                <i data-icon="scale" class="i i-sm"></i>
                <div>
                    <div class="tiny dim">Verified weight × category rate</div>
                    <div class="strong figure-num" style="font-size:16px">
                        household {{ (int) (config('ecocycle.household_share') * 100) }}%
                        · agent {{ (int) (config('ecocycle.agent_share') * 100) }}%
                    </div>
                </div>
            </div>
            <p class="tiny dim mt-12">
                Money is never released against an estimate or a declaration. A payout collects every
                outstanding credit for one party and debits the ledger once, under an idempotency key —
                so a retried click cannot pay anybody twice.
            </p>
        </div>
    </section>

    <section class="card mb-16">
        <div class="card-head"><h3>Payout queue</h3><span class="badge">{{ $payouts->count() }}</span></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th>Reference</th><th>Party</th><th>Role</th><th class="num">Amount</th>
                    <th>Method</th><th>Status</th><th></th>
                </tr></thead>
                <tbody>
                @forelse ($payouts as $p)
                    <tr>
                        <td class="mono">{{ $p->reference }}</td>
                        <td>{{ $p->party?->name() ?? '—' }}</td>
                        <td class="small">{{ $p->partyLabel() }}</td>
                        <td class="num strong figure-num">₦{{ number_format((float) $p->amount, 2) }}</td>
                        <td class="small">{{ $p->method }}</td>
                        <td><span class="badge {{ $p->badgeClass() }}">{{ $p->status }}</span></td>
                        <td class="right">
                            @if ($p->status !== 'paid')
                                <button class="btn btn-sm btn-ghost" wire:click="markPaid({{ $p->id }})">Mark paid</button>
                            @else
                                <span class="tiny dim">{{ optional($p->paid_at)->format('j M') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">
                        <div class="empty">
                            <span class="empty-icon"><i data-icon="wallet" class="i i-lg"></i></span>
                            <div class="strong">No payouts yet</div>
                            <p class="small muted">Run a payout batch to collect outstanding credits.</p>
                        </div>
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><h3>Ledger</h3><span class="tiny dim">append-only · newest first</span></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th>When</th><th>Party</th><th>Kind</th><th>Batch</th>
                    <th class="num">Amount</th><th>Direction</th>
                </tr></thead>
                <tbody>
                @foreach ($recent as $e)
                    <tr>
                        <td class="small">{{ $e->created_at->format('j M, H:i') }}</td>
                        <td class="small">{{ $e->party?->name() ?? '—' }}</td>
                        <td class="small">{{ ucfirst($e->kind) }}</td>
                        <td class="mono tiny">{{ $e->batch?->shortCode() ?? '—' }}</td>
                        <td class="num strong figure-num {{ $e->direction->value === 'credit' ? 't-pos' : '' }}">
                            {{ $e->direction->value === 'credit' ? '+' : '−' }}₦{{ number_format((float) $e->amount, 2) }}
                        </td>
                        <td><span class="badge {{ $e->direction->value === 'credit' ? 'badge-green' : '' }}">{{ $e->direction->value }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
