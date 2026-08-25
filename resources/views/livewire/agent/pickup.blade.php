<div class="col g-16">

    {{-- Stage rail --}}
    <div class="steps">
        @foreach (['Travel' => 'nav', 'Arrive' => 'pin', 'Tag' => 'qr', 'Weigh' => 'scale', 'Done' => 'check'] as $label => $icon)
            @php $i = $loop->iteration; @endphp
            <div class="step {{ $stage > $i ? 'done' : '' }} {{ $stage === $i ? 'active' : '' }}">
                <span class="step-dot"><i data-icon="{{ $icon }}" class="i i-sm"></i></span>
                <span class="step-label">{{ $label }}</span>
            </div>
        @endforeach
    </div>

    {{-- 1 · Travel --}}
    @if ($stage === 1)
        <section class="card card-flat">
            <div class="card-head"><h3>Household</h3><span class="badge">{{ $household->rating }} ★</span></div>
            <div class="card-body">
                <div class="row g-12">
                    <span class="avatar avatar-lg {{ $household->user->avatarVariant() }}">{{ $household->user->initials() }}</span>
                    <span class="grow">
                        <div class="item-title">{{ $household->name() }}</div>
                        <div class="item-sub">{{ $household->address ?? $household->area?->name }}</div>
                        <div class="tiny dim mono mt-2">
                            {{ $batch->pickup_lat ? number_format($batch->pickup_lat, 5) : '—' }},
                            {{ $batch->pickup_lng ? number_format($batch->pickup_lng, 5) : '—' }}
                        </div>
                    </span>
                </div>

                @if ($batch->note)
                    <div class="callout mt-14">
                        <i data-icon="info" class="i i-sm"></i>
                        <div>{{ $batch->note }}</div>
                    </div>
                @endif

                <div class="row g-10 mt-14">
                    @if ($household->user->phone)
                        <a class="btn btn-ghost grow" href="tel:{{ preg_replace('/\s/', '', $household->user->phone) }}">
                            <i data-icon="phone" class="i i-sm"></i> Call
                        </a>
                    @endif
                    <a class="btn btn-ghost grow" target="_blank" rel="noopener"
                       href="https://www.google.com/maps/dir/?api=1&destination={{ $batch->pickup_lat }},{{ $batch->pickup_lng }}">
                        <i data-icon="nav" class="i i-sm"></i> Directions
                    </a>
                </div>
            </div>
        </section>

        <section class="card card-flat">
            <div class="card-head"><h3>What to expect</h3></div>
            <div class="card-body">
                <dl>
                    <div class="kv"><dt>Estimated weight</dt>
                        <dd class="figure-num">{{ number_format($batch->expected_kg ?? 0, 1) }} kg</dd></div>
                    <div class="kv"><dt>Category</dt><dd>{{ $batch->category?->label }}</dd></div>
                    <div class="kv"><dt>Your capacity left</dt>
                        <dd class="figure-num">{{ number_format($agent->remainingCapacityKg(), 1) }} kg</dd></div>
                    <div class="kv"><dt><span class="strong">Your share (40%)</span></dt>
                        <dd class="strong figure-num">
                            ₦{{ number_format(($batch->expected_kg ?? 0) * (float) ($batch->category?->rate_per_kg ?? 0) * 0.4, 2) }}
                        </dd></div>
                </dl>
            </div>
        </section>
    @endif

    {{-- 2 · Arrive --}}
    @if ($stage === 2)
        <section class="card card-flat">
            <div class="card-body center" style="padding:34px 20px">
                <span class="qa-ic" style="width:60px;height:60px;margin:0 auto;border-radius:18px">
                    <i data-icon="pin" class="i i-xl"></i>
                </span>
                <h2 class="mt-16">You have arrived</h2>
                <p class="small muted mt-4">Confirm you are at {{ $household->name() }}'s pickup point.</p>
            </div>
        </section>
    @endif

    {{-- 3 · Tag --}}
    @if ($stage === 3)
        <section class="card card-flat">
            <div class="card-head"><h3>Tag the batch</h3><span class="badge badge-violet">QR</span></div>
            <div class="card-body">
                <div class="qr-tag">
                    {!! $qr !!}
                    <div>
                        <div class="tiny dim">Batch tag</div>
                        <div class="mono strong small">{{ $batch->code }}</div>
                        <div class="tiny dim mt-8">{{ $household->name() }} · {{ $household->area?->name }}</div>
                    </div>
                </div>
                <p class="tiny dim mt-12">
                    Attach this tag to the load. Everything from here — the gate weight, the grade and both
                    payments — is recorded against this code.
                </p>
            </div>
        </section>
    @endif

    {{-- 4 · Weigh --}}
    @if ($stage === 4)
        <section class="card card-flat">
            <div class="card-head"><h3>Weigh and declare</h3></div>
            <div class="card-body col g-14">
                <div class="field">
                    <label class="label">Declared weight</label>
                    <div class="input-group">
                        <i data-icon="scale" class="i i-sm"></i>
                        <input class="input mono" type="number" step="0.1" wire:model.live.debounce.400ms="kg">
                        <span class="input-suffix">kg</span>
                    </div>
                    @error('kg') <span class="hint t-neg">{{ $message }}</span> @enderror
                </div>

                @if ($this->variance !== null)
                    @php $bad = abs($this->variance) >= 12; @endphp
                    <div class="callout {{ $bad ? 'callout-warn' : 'callout-ok' }}">
                        <i data-icon="{{ $bad ? 'alert' : 'ok' }}" class="i i-sm"></i>
                        <div>
                            <strong>{{ $this->variance > 0 ? '+' : '' }}{{ $this->variance }}% against the household estimate.</strong>
                            {{ $bad
                                ? 'Large gaps are reviewed at the facility. Add a note explaining the difference.'
                                : 'Within the expected range.' }}
                        </div>
                    </div>
                @endif

                <div class="field">
                    <label class="label">Confirmed category</label>
                    <select class="select" wire:model.live="categoryId">
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->label }} — ₦{{ (int) $c->rate_per_kg }}/kg</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label class="label">Photograph of the load</label>
                    <input type="file" class="input" accept="image/*" capture="environment" wire:model="photo">
                    <span class="hint">Used as evidence at inspection, and to pre-estimate composition.</span>
                    @error('photo') <span class="hint t-neg">{{ $message }}</span> @enderror
                </div>

                <div wire:loading wire:target="photo" class="callout">
                    <i data-icon="refresh" class="i i-sm"></i><div>Uploading and analysing…</div>
                </div>

                @if ($classification)
                    <div class="ai-card">
                        <div class="between mb-8">
                            <span class="ai-tag"><i data-icon="sparkles" class="i i-sm"></i> Estimated composition</span>
                            <span class="confidence">
                                {{ $classification['confidence'] }}%
                                <span class="bar bar-sm" style="width:52px">
                                    <span style="width:{{ $classification['confidence'] }}%;background:var(--violet)"></span>
                                </span>
                            </span>
                        </div>
                        <div class="mixbar">
                            @foreach ($classification['mix'] as $key => $pct)
                                <span style="width:{{ $pct }}%;background:var(--c{{ (crc32($key) % 6) + 1 }})"></span>
                            @endforeach
                        </div>
                        <div class="row g-8 wrap mt-8">
                            @foreach ($classification['mix'] as $key => $pct)
                                <span class="tiny dim">{{ ucfirst($key) }} {{ $pct }}%</span>
                            @endforeach
                        </div>
                        <p class="tiny muted mt-8">
                            Suggested: <strong>{{ ucfirst($classification['suggested']) }}</strong>.
                            {{ $classification['generated']
                                ? 'The facility inspector remains the final authority.'
                                : 'No AI key configured — this is a placeholder estimate.' }}
                        </p>
                    </div>
                @endif

                <div class="field">
                    <label class="label">Note (optional)</label>
                    <textarea class="textarea" wire:model="note" placeholder="Anything the inspector should know"></textarea>
                </div>
            </div>
        </section>
    @endif

    {{-- 5 · Done --}}
    @if ($stage === 5)
        <section class="card card-flat">
            <div class="card-body center">
                <span class="qa-ic" style="width:60px;height:60px;margin:0 auto;border-radius:18px">
                    <i data-icon="check" class="i i-xl"></i>
                </span>
                <h2 class="mt-16">Pickup complete</h2>
                <p class="small muted mt-4">The batch is on board and the household has been notified.</p>
                <dl class="mt-20" style="text-align:left">
                    <div class="kv"><dt>Batch tag</dt><dd class="mono">{{ $batch->code }}</dd></div>
                    <div class="kv"><dt>Declared weight</dt>
                        <dd class="figure-num">{{ number_format($batch->collected_kg ?? 0, 1) }} kg</dd></div>
                    <div class="kv"><dt>Category</dt><dd>{{ $batch->category?->label }}</dd></div>
                    <div class="kv"><dt>Estimated share</dt>
                        <dd class="strong figure-num">
                            ₦{{ number_format(($batch->collected_kg ?? 0) * (float) ($batch->category?->rate_per_kg ?? 0) * 0.4, 2) }}
                        </dd></div>
                    <div class="kv"><dt>Load on board</dt>
                        <dd class="figure-num">{{ number_format($onBoard, 1) }} of {{ $agent->capacity_kg }} kg</dd></div>
                </dl>
                <div class="callout callout-info mt-16" style="text-align:left">
                    <i data-icon="info" class="i i-sm"></i>
                    <div>Payment is released after the facility verifies the weight — usually within a few hours of delivery.</div>
                </div>
            </div>
        </section>

        <div class="row g-10">
            <a class="btn btn-ghost grow" href="{{ route('agent.jobs') }}" wire:navigate>Find another job</a>
            <button class="btn btn-primary grow" wire:click="depart">Head to facility</button>
        </div>
    @endif

    {{-- Controls --}}
    @if ($stage < 5)
        <div class="row g-10">
            @if ($stage > 1)
                <button class="btn btn-ghost" wire:click="back">Back</button>
            @endif
            @if ($stage === 4)
                <button class="btn btn-primary grow" wire:click="confirm" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="confirm">Confirm pickup</span>
                    <span wire:loading wire:target="confirm">Recording…</span>
                </button>
            @else
                <button class="btn btn-primary grow" wire:click="next">
                    {{ ['I have arrived', 'Confirm arrival', 'Tag applied', 'Continue'][$stage - 1] ?? 'Continue' }}
                </button>
            @endif
        </div>
    @endif
</div>
