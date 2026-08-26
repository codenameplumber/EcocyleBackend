<div>
    <div class="page-head">
        <div>
            <h1>Integrity review</h1>
            <p class="muted">Every flag carries its evidence and can be appealed.</p>
        </div>
        <button class="btn btn-ghost" wire:click="$set('showRules', true)">
            <i data-icon="settings" class="i i-sm"></i> Detection rules
        </button>
    </div>

    <section class="grid grid-4 mb-16">
        <div class="card stat tone-red">
            <div class="stat-top"><span class="stat-label">Open cases</span>
                <span class="stat-icon"><i data-icon="alert" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ $openCount }}</div>
            <div class="stat-foot">awaiting a decision</div>
        </div>
        <div class="card stat tone-amber">
            <div class="stat-top"><span class="stat-label">Value at risk</span>
                <span class="stat-icon"><i data-icon="wallet" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">₦{{ number_format($valueAtRisk, 0) }}</div>
            <div class="stat-foot">across open cases</div>
        </div>
        <div class="card stat tone-violet">
            <div class="stat-top"><span class="stat-label">Confirmed</span>
                <span class="stat-icon"><i data-icon="shield" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ $confirmed }}</div>
            <div class="stat-foot">upheld after review</div>
        </div>
        <div class="card stat tone-green">
            <div class="stat-top"><span class="stat-label">Tolerance</span>
                <span class="stat-icon"><i data-icon="scale" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">±{{ config('ecocycle.variance_tolerance') }}<small>%</small></div>
            <div class="stat-foot">before a flag is raised</div>
        </div>
    </section>

    <section class="grid" style="grid-template-columns:360px minmax(0,1fr);gap:16px;align-items:start">
        <div class="card">
            <div class="card-head">
                <h3>{{ $filter === 'open' ? 'Open cases' : 'Cases' }}</h3>
                <span class="badge">{{ $cases->total() }}</span>
            </div>
            <div class="tabs" style="padding:0 12px">
                @foreach (['open' => 'Open', 'confirmed' => 'Confirmed', 'dismissed' => 'Dismissed', 'all' => 'All'] as $k => $label)
                    <button class="tab" wire:click="setFilter('{{ $k }}')"
                            aria-selected="{{ $filter === $k ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
            <ul class="list">
                @forelse ($cases as $c)
                    <li>
                        <button class="item" wire:click="select({{ $c->id }})"
                                @if ($flagId === $c->id) style="background:var(--primary-soft)" @endif>
                            <span class="qa-ic" style="width:32px;height:32px;background:var(--{{ $c->severity->value === 'high' ? 'danger' : 'warn' }}-soft);color:var(--{{ $c->severity->value === 'high' ? 'danger' : 'warn' }})">
                                <i data-icon="alert" class="i i-sm"></i>
                            </span>
                            <span class="grow" style="min-width:0">
                                <div class="item-title truncate">{{ $c->kindLabel() }}</div>
                                <div class="item-sub truncate">{{ $c->agent?->name() ?? '—' }}</div>
                            </span>
                            <span class="item-end">
                                <span class="badge {{ $c->severity->badgeClass() }}">{{ $c->severity->value }}</span>
                                <div class="tiny dim mt-4">{{ $c->created_at->diffForHumans(short: true) }}</div>
                            </span>
                        </button>
                    </li>
                @empty
                    <li><div class="empty">
                        <span class="empty-icon"><i data-icon="ok" class="i i-lg"></i></span>
                        <div class="strong">Nothing here</div>
                        <p class="small muted">No cases with this status.</p>
                    </div></li>
                @endforelse
            </ul>
            @if ($cases->hasPages())
                <div class="card-foot">{{ $cases->links() }}</div>
            @endif
        </div>

        @if ($this->flag)
            @php $f = $this->flag; $b = $f->batch; @endphp
            <div class="col g-16">
                <div class="card">
                    <div class="card-head">
                        <div>
                            <h3>{{ $f->kindLabel() }}</h3>
                            <div class="tiny dim mt-4">
                                Raised {{ $f->created_at->diffForHumans() }}
                                @if ($b) · batch <span class="mono">{{ $b->code }}</span> @endif
                            </div>
                        </div>
                        <div class="row g-8">
                            <span class="badge {{ $f->severity->badgeClass() }}">{{ $f->severity->value }} severity</span>
                            <span class="badge">{{ $f->status }}</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="callout callout-warn">
                            <i data-icon="alert" class="i i-sm"></i>
                            <div>{{ $f->detail }}</div>
                        </div>

                        @if ($b)
                            <div class="grid grid-3 mt-16" style="gap:14px">
                                <div class="card card-pad card-flat">
                                    <div class="tiny dim">Agent declared</div>
                                    <div class="stat-value figure-num" style="font-size:22px">
                                        {{ number_format($b->collected_kg ?? 0, 1) }} kg
                                    </div>
                                </div>
                                <div class="card card-pad card-flat">
                                    <div class="tiny dim">Scale verified</div>
                                    <div class="stat-value figure-num" style="font-size:22px">
                                        {{ number_format($b->verified_kg ?? 0, 1) }} kg
                                    </div>
                                </div>
                                <div class="card card-pad card-flat" style="border-color:var(--danger);background:var(--danger-soft)">
                                    <div class="tiny" style="color:var(--danger)">Variance</div>
                                    <div class="stat-value figure-num" style="font-size:22px;color:var(--danger)">
                                        {{ $b->variance_pct > 0 ? '+' : '' }}{{ number_format($b->variance_pct ?? 0, 1) }}%
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($b && $b->photo_path)
                    <div class="card">
                        <div class="card-head"><h3>Evidence</h3><span class="badge">attached at pickup</span></div>
                        <div class="media media-16x9 media-flat">
                            <img src="{{ Storage::url($b->photo_path) }}" alt="Pickup photograph">
                        </div>
                    </div>
                @endif

                @if ($evidence['count'] > 1)
                    <div class="card">
                        <div class="card-head">
                            <h3>Evidence</h3>
                            <span class="badge">{{ $evidence['count'] }} weighed batches</span>
                        </div>
                        <div class="card-body">
                            <svg class="chart" data-chart="area" data-height="170"
                                 data-values="{{ implode(',', $evidence['declared']) }}"
                                 data-compare="{{ implode(',', $evidence['verified']) }}"
                                 data-compare-color="var(--primary)"
                                 data-labels="{{ implode(',', $evidence['labels']) }}"></svg>
                            <div class="row g-20 wrap mt-10">
                                <span class="row g-6 tiny">
                                    <span style="width:10px;height:2px;background:var(--c1);display:inline-block"></span>
                                    Declared by the agent
                                </span>
                                <span class="row g-6 tiny">
                                    <span style="width:10px;height:2px;background:var(--primary);display:inline-block"></span>
                                    Verified at the gate
                                </span>
                            </div>
                            <p class="tiny dim mt-10">
                                One batch apart from the scale is noise. A line that sits consistently
                                above the other is the pattern this case exists to surface.
                            </p>
                        </div>
                    </div>
                @endif

                @if ($trail)
                    <div class="card">
                        <div class="card-head">
                            <h3>Location trail</h3>
                            <span class="badge badge-blue">GPS at each confirmation</span>
                        </div>
                        <x-map :pins="$trail" :height="260" :legend="false"
                               :note="$trailDistance !== null ? number_format($trailDistance, 0).' m apart' : null" />
                        <div class="card-body">
                            <p class="tiny dim">
                                Each pickup confirmation records coordinates. A confirmation more than
                                {{ $thresholds['gps_radius_m'] }} m from the household's registered position
                                raises a location flag on that batch.
                            </p>
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-head"><h3>Resolution</h3></div>
                    <div class="card-body">
                        <p class="small muted">
                            Confirming a case lowers the agent's integrity score and affects which jobs they
                            are offered. Dismissing it clears the flag and leaves the score untouched. Either
                            way the decision, the reviewer and the timestamp are recorded.
                        </p>
                    </div>
                    <div class="card-foot row" style="justify-content:flex-end">
                        <button class="btn btn-ghost" wire:click="resolve('dismissed')">Dismiss</button>
                        <button class="btn btn-danger" wire:click="resolve('confirmed')">Confirm the flag</button>
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body">
                    <div class="empty">
                        <span class="empty-icon"><i data-icon="shield" class="i i-lg"></i></span>
                        <div class="strong">Nothing flagged</div>
                        <p class="small muted">Every batch is inside the variance tolerance.</p>
                    </div>
                </div>
            </div>
        @endif
    </section>

    {{-- The detector registry, read straight off IntegrityService::SIGNALS, so
         this grid cannot claim a check the code does not run. --}}
    <section class="card mt-16">
        <div class="card-head">
            <h3>What the system watches for</h3>
            <span class="badge badge-violet">{{ collect($signals)->where('live', true)->count() }} of {{ count($signals) }} running</span>
        </div>
        <div class="card-body">
            <div class="grid grid-4">
                @foreach ($signals as $sig)
                    <div class="card card-pad" style="box-shadow:none{{ $sig['live'] ? '' : ';opacity:.58' }}">
                        <div class="between">
                            <span class="qa-ic" style="width:30px;height:30px;background:var(--danger-soft);color:var(--danger)">
                                <i data-icon="{{ $sig['icon'] }}" class="i i-sm"></i>
                            </span>
                            @if ($sig['live'])
                                <span class="badge badge-green"><span class="dot"></span>{{ $flagCounts[$sig['key']] ?? 0 }}</span>
                            @else
                                <span class="badge">planned</span>
                            @endif
                        </div>
                        <div class="small strong mt-8">{{ $sig['name'] }}</div>
                        <div class="tiny dim">{{ $sig['detail'] }}</div>
                    </div>
                @endforeach
            </div>
            <p class="tiny dim mt-14">
                Four detectors run on every batch as it is weighed; the badge on each is how many cases it
                has raised. The greyed four describe patterns the pilot's data does not yet support, and are
                listed so the gap is visible rather than implied.
            </p>
        </div>
    </section>

    {{-- Detection thresholds --}}
    @if ($showRules)
        <div class="scrim" wire:click.self="$set('showRules', false)">
            <div class="modal" role="dialog" aria-modal="true">
                <div class="card-head">
                    <h3>Detection thresholds</h3>
                    <button class="btn btn-icon btn-quiet" wire:click="$set('showRules', false)" aria-label="Close">
                        <i data-icon="close"></i>
                    </button>
                </div>
                <div class="card-body col g-14">
                    <div class="field">
                        <label class="label">Weight variance tolerance</label>
                        <div class="input-group">
                            <input class="input" value="{{ $thresholds['variance_tolerance'] }}" readonly>
                            <span class="input-suffix">%</span>
                        </div>
                        <span class="hint">Gaps wider than this raise a case. Settlement is unaffected — money already follows the gate scale.</span>
                    </div>
                    <div class="field">
                        <label class="label">GPS radius</label>
                        <div class="input-group">
                            <input class="input" value="{{ $thresholds['gps_radius_m'] }}" readonly>
                            <span class="input-suffix">m</span>
                        </div>
                        <span class="hint">Distance from the registered address beyond which a confirmation is questioned.</span>
                    </div>
                    <div class="field">
                        <label class="label">Volume spike multiplier</label>
                        <div class="input-group">
                            <input class="input" value="{{ $thresholds['spike_multiple'] }}" readonly>
                            <span class="input-suffix">&times;</span>
                        </div>
                        <span class="hint">Against the household's rolling baseline of its last eight verified batches.</span>
                    </div>
                    <div class="field">
                        <label class="label">Rejection run</label>
                        <div class="input-group">
                            <input class="input" value="{{ $thresholds['rejection_run'] }}" readonly>
                            <span class="input-suffix">in a row</span>
                        </div>
                    </div>
                    <div class="callout">
                        <i data-icon="info" class="i i-sm"></i>
                        <div>
                            These are read from <span class="mono">config/ecocycle.php</span> and
                            <span class="mono">IntegrityService</span>. Editing a threshold from this screen
                            would let an operator quietly widen the tolerance on their own cases, so it is
                            deliberately a deployment change.
                        </div>
                    </div>
                </div>
                <div class="card-foot row" style="justify-content:flex-end">
                    <button class="btn btn-ghost" wire:click="$set('showRules', false)">Close</button>
                </div>
            </div>
        </div>
    @endif
</div>
