<div>
    <div class="page-head">
        <div>
            <h1>Batch registry</h1>
            <p class="muted">Every tagged batch, doorstep to processing output.</p>
        </div>
    </div>

    <section class="grid grid-4 mb-16">
        <div class="card stat tone-green">
            <div class="stat-top"><span class="stat-label">Batches</span>
                <span class="stat-icon"><i data-icon="package" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ number_format($batches->total()) }}</div>
            <div class="stat-foot">matching the current filter</div>
        </div>
        <div class="card stat tone-cyan">
            <div class="stat-top"><span class="stat-label">Verified weight</span>
                <span class="stat-icon"><i data-icon="scale" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ number_format($totalKg, 1) }}<small>kg</small></div>
            <div class="stat-foot">all time, on the scale</div>
        </div>
        <div class="card stat tone-amber">
            <div class="stat-top"><span class="stat-label">Outside tolerance</span>
                <span class="stat-icon"><i data-icon="alert" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ $flagged }}</div>
            <div class="stat-foot">declared vs verified over {{ config('ecocycle.variance_tolerance') }}%</div>
        </div>
        <div class="card stat tone-violet">
            <div class="stat-top"><span class="stat-label">Rejected</span>
                <span class="stat-icon"><i data-icon="ban" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ \App\Models\WasteBatch::where('status', 'REJECTED')->count() }}</div>
            <div class="stat-foot">paid to nobody</div>
        </div>
    </section>

    <section class="card">
        <div class="card-head">
            <h3>All batches</h3>
            <div class="row g-10 wrap">
                <div class="input-group" style="width:230px">
                    <i data-icon="search" class="i i-sm"></i>
                    <input class="input" wire:model.live.debounce.400ms="q" placeholder="Batch, household or agent…">
                </div>
                <select class="select" wire:model.live="status" style="width:auto">
                    <option value="">All states</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
                <select class="select" wire:model.live="category" style="width:auto">
                    <option value="">All categories</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}">{{ $c->label }}</option>
                    @endforeach
                </select>
                <button class="chip" wire:click="$toggle('varianceOnly')"
                        aria-pressed="{{ $varianceOnly ? 'true' : 'false' }}">Variance &gt; {{ config('ecocycle.variance_tolerance') }}%</button>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th>Batch</th><th>Household</th><th>Agent</th><th>Category</th>
                    <th class="num">Expected</th><th class="num">Declared</th><th class="num">Verified</th>
                    <th class="num">Variance</th><th>Status</th><th class="num">Value</th>
                </tr></thead>
                <tbody>
                @forelse ($batches as $b)
                    <tr>
                        <td class="mono">{{ $b->shortCode() }}</td>
                        <td>
                            <div class="row g-8">
                                <span class="avatar avatar-sm {{ $b->household->user->avatarVariant() }}">
                                    {{ $b->household->user->initials() }}
                                </span>
                                <span class="small">{{ $b->household->name() }}</span>
                            </div>
                        </td>
                        <td class="small">{{ $b->agent?->name() ?? '—' }}</td>
                        <td>
                            <span class="row g-8">
                                <span class="thumb thumb-sm">
                                    <img src="{{ asset('assets/img/cat-' . $b->effectiveCategory()?->key . '.jpg') }}" alt="" loading="lazy">
                                </span>
                                <span class="small">{{ $b->effectiveCategory()?->label }}</span>
                            </span>
                        </td>
                        <td class="num figure-num">{{ $b->expected_kg ? number_format($b->expected_kg, 1) : '—' }}</td>
                        <td class="num figure-num">{{ $b->collected_kg ? number_format($b->collected_kg, 1) : '—' }}</td>
                        <td class="num figure-num strong">{{ $b->verified_kg ? number_format($b->verified_kg, 1) : '—' }}</td>
                        <td class="num">
                            @if ($b->variance_pct !== null)
                                <span class="{{ $b->varianceIsOutOfTolerance() ? 't-neg strong' : 'dim' }}">
                                    {{ $b->variance_pct > 0 ? '+' : '' }}{{ number_format($b->variance_pct, 1) }}%
                                </span>
                            @else — @endif
                        </td>
                        <td><span class="st {{ $b->status->cssClass() }}">{{ $b->status->label() }}</span></td>
                        <td class="num figure-num">{{ $b->value_naira ? '₦' . number_format((float) $b->value_naira, 0) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10">
                        <div class="empty">
                            <span class="empty-icon"><i data-icon="search" class="i i-lg"></i></span>
                            <div class="strong">No batches match</div>
                            <p class="small muted">Try clearing a filter.</p>
                        </div>
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-foot">{{ $batches->links() }}</div>
    </section>
</div>
