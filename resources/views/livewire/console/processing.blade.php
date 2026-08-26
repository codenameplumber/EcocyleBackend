<div>
    <div class="page-head">
        <div><h1>Processing</h1>
            <p class="muted">What goes into the lines, and what comes back out.</p></div>
        <button class="btn btn-primary" wire:click="openStart">
            <i data-icon="plus" class="i i-sm"></i> Start a run
        </button>
    </div>

    <section class="grid grid-4 mb-16">
        <div class="card stat tone-green">
            <div class="stat-top"><span class="stat-label">Processed today</span>
                <span class="stat-icon"><i data-icon="factory" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ number_format($doneToday, 1) }}<small>kg</small></div>
            <div class="stat-foot">output recorded</div>
        </div>
        <div class="card stat tone-cyan">
            <div class="stat-top"><span class="stat-label">Processed to date</span>
                <span class="stat-icon"><i data-icon="recycle" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ number_format($totalDone, 1) }}<small>kg</small></div>
            <div class="stat-foot">all time</div>
        </div>
        <div class="card stat tone-amber">
            <div class="stat-top"><span class="stat-label">Queued feedstock</span>
                <span class="stat-icon"><i data-icon="boxes" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ $queue->count() }}</div>
            <div class="stat-foot">verified, awaiting a line</div>
        </div>
        <div class="card stat tone-violet">
            <div class="stat-top"><span class="stat-label">On the lines</span>
                <span class="stat-icon"><i data-icon="pulse" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ $running->count() }}</div>
            <div class="stat-foot">currently processing</div>
        </div>
    </section>

    {{-- Lines and today's output --}}
    <section class="grid mb-16" style="grid-template-columns:1.5fr 1fr;gap:16px;align-items:start">
        <div class="card">
            <div class="card-head">
                <h3>Lines</h3>
                <span class="badge {{ $running_lines ? 'badge-green' : '' }}">{{ $running_lines }} running</span>
            </div>
            <div class="card-body col g-14">
                @foreach ($lines as $l)
                    <div>
                        <div class="between mb-4">
                            <span class="row g-8 small strong">
                                <span class="badge {{ $l->badgeClass() }}" style="min-width:0">{{ $l->status }}</span>
                                {{ $l->name }}
                            </span>
                            <span class="small strong figure-num">{{ $l->utilisation }}%</span>
                        </div>
                        <div class="bar {{ $l->utilisation > 85 ? 'amber' : '' }}"><span style="width:{{ $l->utilisation }}%"></span></div>
                        <div class="tiny dim mt-4">
                            {{ number_format($l->capacity_kg_day) }} kg/day · {{ $l->output_desc }}
                            @if ($l->note) · {{ $l->note }} @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>Output today</h3><span class="badge">by stream</span></div>
            <div class="card-body">
                @php($tot = max(0.001, $output['total_kg']))
                <div class="col g-14">
                    <div>
                        <div class="between mb-6">
                            <span class="row g-8 small"><i data-icon="flame" class="i i-sm" style="color:var(--warn)"></i>Biogas</span>
                            <span class="small strong figure-num">{{ number_format($output['biogas_m3'], 0) }} m³</span>
                        </div>
                        <div class="bar amber"><span style="width:{{ min(100, $output['digestate_kg'] / $tot * 260) }}%"></span></div>
                    </div>
                    <div>
                        <div class="between mb-6">
                            <span class="row g-8 small"><i data-icon="recycle" class="i i-sm" style="color:var(--primary)"></i>Recovered recyclables</span>
                            <span class="small strong figure-num">{{ number_format($output['recovered_kg'] / 1000, 2) }} t</span>
                        </div>
                        <div class="bar"><span style="width:{{ min(100, $output['recovered_kg'] / $tot * 100) }}%"></span></div>
                    </div>
                    <div>
                        <div class="between mb-6">
                            <span class="row g-8 small"><i data-icon="droplet" class="i i-sm" style="color:var(--cyan)"></i>Digestate fertiliser</span>
                            <span class="small strong figure-num">{{ number_format($output['digestate_kg'] / 1000, 2) }} t</span>
                        </div>
                        <div class="bar blue"><span style="width:{{ min(100, $output['digestate_kg'] / $tot * 100) }}%"></span></div>
                    </div>
                    <div>
                        <div class="between mb-6">
                            <span class="row g-8 small"><i data-icon="ban" class="i i-sm" style="color:var(--danger)"></i>Residual to landfill</span>
                            <span class="small strong figure-num">{{ number_format($output['residual_kg'] / 1000, 2) }} t</span>
                        </div>
                        <div class="bar red"><span style="width:{{ min(100, $output['residual_kg'] / $tot * 100) }}%"></span></div>
                    </div>
                </div>

                <div class="divider mt-16 mb-16"></div>
                <div class="grid grid-2" style="gap:12px">
                    <div>
                        <div class="tiny dim">Diversion rate</div>
                        <div class="strong figure-num" style="font-size:19px">{{ number_format($output['diversion_pct'], 1) }}%</div>
                    </div>
                    <div>
                        <div class="tiny dim">CO₂e avoided</div>
                        <div class="strong figure-num" style="font-size:19px">{{ number_format($output['co2e_kg'] / 1000, 2) }} t</div>
                    </div>
                </div>

                @if ($output['total_kg'] <= 0.01)
                    <p class="tiny dim mt-12">Nothing has been fed to a line today yet. These read from closed runs, so they fill in as the day goes.</p>
                @endif
            </div>
        </div>
    </section>

    {{-- Capacity outlook --}}
    @if ($capacity)
        <section class="grid mb-16" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px">
            @foreach ($capacity as $i)
                <x-insight :insight="$i" />
            @endforeach
        </section>
    @endif

    {{-- Throughput --}}
    <section class="card mb-16">
        <div class="card-head">
            <h3>Throughput and yield</h3>
            <div class="row g-10">
                <span class="badge badge-violet">{{ number_format($throughput['yield'], 1) }}% yield</span>
                <div class="btn-group">
                    @foreach ([14 => '14 days', 90 => '90 days'] as $d => $label)
                        <button wire:click="setWindow({{ $d }})" aria-selected="{{ $window === $d ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="card-body">
            <svg class="chart" data-chart="area" data-height="220"
                 data-values="{{ implode(',', $throughput['input']) }}"
                 data-compare="{{ implode(',', $throughput['output']) }}"
                 data-compare-color="var(--c2)"
                 data-labels="{{ implode(',', $throughput['labels']) }}"></svg>
            <p class="tiny dim mt-10">
                Solid is feedstock in, dashed is product out. The gap is process loss —
                moisture off the digester and contamination pulled on the sorting line.
            </p>
        </div>
    </section>

    {{-- Runs --}}
    <section class="card mb-16">
        <div class="card-head"><h3>Processing runs</h3><span class="tiny dim">Most recent first</span></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th>Run</th><th>Line</th><th>Feedstock</th><th class="num">Batches</th>
                    <th class="num">Input</th><th class="num">Yield</th><th>Output</th><th>Status</th>
                </tr></thead>
                <tbody>
                    @forelse ($runs as $r)
                        <tr>
                            <td class="mono small">{{ $r->code }}</td>
                            <td class="small">{{ $r->line?->name ?? '—' }}</td>
                            <td class="small">{{ $r->line?->category?->label ?? 'Mixed' }}</td>
                            <td class="num">{{ $r->batches_count }}</td>
                            <td class="num strong figure-num">{{ number_format((float) $r->input_kg, 1) }} kg</td>
                            <td class="num figure-num">{{ $r->yield_pct ? number_format((float) $r->yield_pct, 1) . '%' : '—' }}</td>
                            <td class="small">
                                @if ($r->output_kg)
                                    <span class="strong figure-num">{{ number_format((float) $r->output_kg, 1) }} kg</span>
                                    <span class="dim">· {{ $r->output_desc }}</span>
                                @else
                                    <span class="dim">in progress</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $r->badgeClass() }}">{{ $r->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8">
                            <div class="empty">
                                <span class="empty-icon"><i data-icon="factory" class="i i-lg"></i></span>
                                <div class="strong">No runs yet</div>
                                <p class="small dim">A run opens the first time a verified batch is fed to a line.</p>
                            </div>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Line cards --}}
    <section class="grid mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr));gap:16px">
        @foreach ($lines as $l)
            <div class="card card-flat" style="overflow:hidden{{ $l->status === 'offline' ? ';opacity:.74' : '' }}">
                <div class="media media-16x9 media-flat">
                    <img src="{{ asset('assets/img/' . ($l->status === 'offline' ? 'facility-exterior' : ($l->category?->key === 'organic' ? 'facility-digester' : ($l->category?->key === 'plastic' ? 'cat-plastic' : 'facility-sorting'))) . '.jpg') }}"
                         alt="" loading="lazy">
                </div>
                <div class="card-body" style="padding:14px">
                    <div class="between">
                        <h3 style="font-size:14.5px">{{ $l->name }}</h3>
                        <span class="badge {{ $l->badgeClass() }}">{{ $l->status }}</span>
                    </div>
                    <div class="figure-num mt-8" style="font-size:16px">{{ number_format($l->capacity_kg_day) }} kg/day</div>
                    <div class="tiny dim mt-2 mb-10">{{ $l->output_desc }}</div>
                    <div class="bar {{ $l->utilisation > 85 ? 'amber' : '' }}"><span style="width:{{ $l->utilisation }}%"></span></div>
                    <div class="tiny dim mt-6">{{ $l->utilisation ? $l->utilisation . '% of capacity' : $l->note }}</div>
                </div>
            </div>
        @endforeach
    </section>

    <section class="grid" style="grid-template-columns:1fr 1fr;gap:16px;align-items:start">
        <div class="card">
            <div class="card-head"><h3>Awaiting a line</h3><span class="badge">{{ $queue->total() }}</span></div>
            <ul class="list">
                @forelse ($queue as $b)
                    <li><div class="item">
                        <span class="thumb"><img src="{{ asset('assets/img/cat-' . $b->effectiveCategory()?->key . '.jpg') }}" alt="" loading="lazy"></span>
                        <span class="grow" style="min-width:0">
                            <div class="item-title truncate mono">{{ $b->shortCode() }}</div>
                            <div class="item-sub truncate">{{ $b->household->name() }} · {{ number_format($b->verified_kg, 1) }} kg</div>
                        </span>
                        <button class="btn btn-sm btn-primary" wire:click="startRun({{ $b->id }})">Start</button>
                    </div></li>
                @empty
                    <li><div class="empty"><span class="empty-icon"><i data-icon="ok" class="i i-lg"></i></span>
                        <div class="strong">Nothing queued</div>
                        <p class="small muted">Every verified batch is on a line.</p></div></li>
                @endforelse
            </ul>
            @if ($queue->hasPages())
                <div class="card-foot">{{ $queue->links() }}</div>
            @endif
        </div>

        <div class="card">
            <div class="card-head"><h3>Running now</h3><span class="badge badge-cyan">{{ $running->total() }}</span></div>
            <ul class="list">
                @forelse ($running as $b)
                    <li><div class="item">
                        <span class="thumb"><img src="{{ asset('assets/img/cat-' . $b->effectiveCategory()?->key . '.jpg') }}" alt="" loading="lazy"></span>
                        <span class="grow" style="min-width:0">
                            <div class="item-title truncate mono">{{ $b->shortCode() }}</div>
                            <div class="item-sub truncate">
                                started {{ optional($b->processing_at)->diffForHumans(short: true) }} · {{ number_format($b->verified_kg, 1) }} kg
                            </div>
                        </span>
                        <button class="btn btn-sm btn-ghost" wire:click="complete({{ $b->id }})">Complete</button>
                    </div></li>
                @empty
                    <li><div class="empty"><span class="empty-icon"><i data-icon="factory" class="i i-lg"></i></span>
                        <div class="strong">Lines idle</div>
                        <p class="small muted">Start a run from the queue.</p></div></li>
                @endforelse
            </ul>
            @if ($running->hasPages())
                <div class="card-foot">{{ $running->links() }}</div>
            @endif
        </div>
    </section>
    {{-- Start a processing run --}}
    @if ($showStart)
        <div class="scrim" wire:click.self="$set('showStart', false)">
            <div class="modal" role="dialog" aria-modal="true">
                <div class="card-head">
                    <h3>Start a processing run</h3>
                    <button class="btn btn-icon btn-quiet" wire:click="$set('showStart', false)" aria-label="Close">
                        <i data-icon="close"></i>
                    </button>
                </div>
                <div class="card-body col g-14">
                    <div class="field">
                        <label class="label">Line</label>
                        <select class="select" wire:model="runLineId">
                            @foreach ($lines as $l)
                                <option value="{{ $l->id }}" @disabled($l->status === 'offline')>
                                    {{ $l->name }}@if ($l->status === 'offline') — offline @endif
                                </option>
                            @endforeach
                        </select>
                        <span class="hint">The stream decides the line in practice; this records which one took it.</span>
                    </div>

                    <div class="field">
                        <label class="label">Feedstock</label>
                        <select class="select" wire:model.live="runCategoryId">
                            <option value="">Everything verified ({{ number_format($queuedByStream->sum('kg'), 1) }} kg queued)</option>
                            @foreach ($queuedByStream as $q)
                                <option value="{{ $q->id }}">{{ $q->label }} ({{ number_format($q->kg, 1) }} kg queued)</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label class="label">Target input</label>
                        <div class="input-group">
                            <input class="input figure-num" type="number" step="0.1" wire:model="runTargetKg">
                            <span class="input-suffix">kg</span>
                        </div>
                        <span class="hint">Batches are fed oldest first until this is reached.</span>
                    </div>

                    <div class="callout callout-info">
                        <i data-icon="info" class="i i-sm"></i>
                        <div>
                            Every batch entering this run keeps its tag, so the output stays attributable
                            back to the households that contributed it.
                        </div>
                    </div>
                </div>
                <div class="card-foot row" style="justify-content:flex-end">
                    <button class="btn btn-ghost" wire:click="$set('showStart', false)">Cancel</button>
                    <button class="btn btn-primary" wire:click="startBatchRun">
                        <span wire:loading.remove wire:target="startBatchRun">Start run</span>
                        <span wire:loading wire:target="startBatchRun">Feeding the line…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
