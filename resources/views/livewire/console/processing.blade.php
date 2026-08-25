<div>
    <div class="page-head">
        <div><h1>Processing</h1>
            <p class="muted">What goes into the lines, and what comes back out.</p></div>
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
            <div class="card-head"><h3>Awaiting a line</h3><span class="badge">{{ $queue->count() }}</span></div>
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
        </div>

        <div class="card">
            <div class="card-head"><h3>Running now</h3><span class="badge badge-cyan">{{ $running->count() }}</span></div>
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
        </div>
    </section>
</div>
