<div>
    <div class="page-head">
        <div>
            <h1>Inspection bay</h1>
            <p class="muted">Weigh, grade, accept or reject. Settlement runs on the figure entered here.</p>
        </div>
        <div class="row g-10 no-print">
            <span class="badge badge-amber">
                <i data-icon="clock" class="i i-sm"></i>
                {{ $queue->count() }} queued · {{ number_format($queueKg, 0) }} kg
            </span>
        </div>
    </div>

    <section class="grid" style="grid-template-columns:340px minmax(0,1fr);gap:16px;align-items:start">

        {{-- Queue --}}
        <div class="card">
            <div class="card-head"><h3>Queue</h3><span class="badge">{{ $queue->count() }}</span></div>
            <ul class="list">
                @forelse ($queue as $q)
                    <li>
                        <button class="item" wire:click="select({{ $q->id }})"
                                @if ($batchId === $q->id) style="background:var(--primary-soft)" @endif>
                            <span class="avatar avatar-sm {{ $q->household->user->avatarVariant() }}">
                                {{ $q->household->user->initials() }}
                            </span>
                            <span class="grow" style="min-width:0">
                                <div class="item-title truncate mono">{{ $q->shortCode() }}</div>
                                <div class="item-sub truncate">
                                    {{ $q->household->name() }} · {{ number_format($q->collected_kg ?? $q->expected_kg, 1) }} kg
                                </div>
                            </span>
                            <span class="item-end">
                                <span class="st {{ $q->status->cssClass() }}">{{ $q->status->label() }}</span>
                                <div class="tiny dim mt-4">{{ optional($q->received_at)->diffForHumans(short: true) }}</div>
                            </span>
                        </button>
                    </li>
                @empty
                    <li>
                        <div class="empty">
                            <span class="empty-icon"><i data-icon="ok" class="i i-lg"></i></span>
                            <div class="strong">Queue is clear</div>
                            <p class="small muted">Nothing is waiting to be weighed.</p>
                        </div>
                    </li>
                @endforelse
            </ul>
        </div>

        {{-- Workstation --}}
        @if ($this->batch)
            @php $b = $this->batch; $p = $this->preview; @endphp
            <div class="col g-16">

                {{-- Header + the three weights --}}
                <div class="card">
                    <div class="card-head">
                        <div>
                            <h3 class="mono">{{ $b->code }}</h3>
                            <div class="tiny dim mt-4">
                                {{ $b->household->name() }} · {{ $b->household->area?->name }}
                                · delivered by {{ $b->agent?->name() ?? '—' }}
                            </div>
                        </div>
                        <div class="row g-8">
                            <span class="st {{ $b->status->cssClass() }}">{{ $b->status->label() }}</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-3" style="gap:14px">
                            <div class="card card-pad card-flat">
                                <div class="tiny dim">Household expected</div>
                                <div class="stat-value figure-num" style="font-size:22px">
                                    {{ $b->expected_kg ? number_format($b->expected_kg, 1) . ' kg' : '—' }}
                                </div>
                            </div>
                            <div class="card card-pad card-flat">
                                <div class="tiny dim">Agent declared</div>
                                <div class="stat-value figure-num" style="font-size:22px">
                                    {{ $b->collected_kg ? number_format($b->collected_kg, 1) . ' kg' : '—' }}
                                </div>
                            </div>
                            <div class="card card-pad card-flat" style="border-color:var(--primary-line);background:var(--primary-soft)">
                                <div class="tiny" style="color:var(--primary)">Scale reading</div>
                                <div class="stat-value figure-num" style="font-size:22px;color:var(--primary)">
                                    {{ is_numeric($verifiedKg) ? number_format((float) $verifiedKg, 1) . ' kg' : '—' }}
                                </div>
                            </div>
                        </div>

                        @if ($p && $p['variance'] !== null)
                            @php $bad = abs($p['variance']) > config('ecocycle.variance_tolerance'); @endphp
                            <div class="callout {{ $bad ? 'callout-warn' : 'callout-ok' }} mt-14">
                                <i data-icon="{{ $bad ? 'alert' : 'ok' }}" class="i i-sm"></i>
                                <div>
                                    <strong>{{ $p['variance'] > 0 ? '+' : '' }}{{ number_format($p['variance'], 1) }}% against the agent declaration.</strong>
                                    {{ $bad
                                        ? 'This exceeds the ' . config('ecocycle.variance_tolerance') . '% tolerance and will be logged against the agent\'s integrity score.'
                                        : 'Within tolerance. No flag raised.' }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Weigh + grade --}}
                <div class="grid" style="grid-template-columns:1fr 1fr;gap:16px">
                    <div class="card">
                        <div class="card-head">
                            <h3>Weigh</h3>
                            <span class="badge badge-green"><span class="dot"></span>Scale connected</span>
                        </div>
                        <div class="card-body">
                            <div class="field">
                                <label class="label">Verified weight</label>
                                <div class="input-group">
                                    <i data-icon="scale" class="i i-sm"></i>
                                    <input class="input mono" type="number" step="0.1" placeholder="0.0"
                                           wire:model.live.debounce.400ms="verifiedKg">
                                    <span class="input-suffix">kg</span>
                                </div>
                                <span class="hint">Read from the platform scale and entered by the inspector.</span>
                            </div>
                            <button class="btn btn-ghost btn-block mt-12" wire:click="takeReading">
                                <i data-icon="refresh" class="i i-sm"></i> Take a reading
                            </button>
                            <div class="field mt-14">
                                <label class="label">Tare / container weight</label>
                                <div class="input-group">
                                    <input class="input" wire:model="tareKg">
                                    <span class="input-suffix">kg</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-head"><h3>Grade and category</h3></div>
                        <div class="card-body col g-14">
                            <div class="field">
                                <label class="label">Confirmed category</label>
                                <select class="select" wire:model.live="categoryId">
                                    @foreach ($categories as $c)
                                        <option value="{{ $c->id }}">{{ $c->label }} — ₦{{ (int) $c->rate_per_kg }}/kg</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="label">Quality grade</label>
                                <div class="opt-grid" style="grid-template-columns:repeat(4,1fr)">
                                    @foreach (['A' => 'Clean', 'B' => 'Minor', 'C' => 'Mixed', 'D' => 'Reject'] as $g => $desc)
                                        <label class="opt" style="align-items:center">
                                            <input type="radio" wire:model.live="grade" value="{{ $g }}">
                                            <span class="opt-name">{{ $g }}</span>
                                            <span class="opt-desc">{{ $desc }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Contamination noted</label>
                                <div class="row g-6 wrap">
                                    @foreach ($contaminationOptions as $opt)
                                        <button type="button" class="chip"
                                                wire:click="toggleContamination('{{ $opt }}')"
                                                aria-pressed="{{ in_array($opt, $contamination, true) ? 'true' : 'false' }}">
                                            {{ $opt }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Settlement preview --}}
                <div class="card">
                    <div class="card-head">
                        <h3>Settlement preview</h3>
                        <span class="badge">{{ $p ? 'Based on the verified weight' : 'Awaiting weight' }}</span>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-3" style="gap:14px">
                            <div>
                                <div class="tiny dim">Batch value</div>
                                <div class="stat-value figure-num" style="font-size:20px">
                                    {{ $p ? '₦' . number_format($p['value'], 2) : '—' }}
                                </div>
                            </div>
                            <div>
                                <div class="tiny dim">Household · 60%</div>
                                <div class="stat-value figure-num" style="font-size:20px;color:var(--primary)">
                                    {{ $p ? '₦' . number_format($p['household'], 2) : '—' }}
                                </div>
                            </div>
                            <div>
                                <div class="tiny dim">Agent · 40%</div>
                                <div class="stat-value figure-num" style="font-size:20px;color:var(--info)">
                                    {{ $p ? '₦' . number_format($p['agent'], 2) : '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-foot row" style="justify-content:flex-end">
                        <button class="btn btn-danger" wire:click="$set('showReject', true)">
                            <i data-icon="ban" class="i i-sm"></i> Reject batch
                        </button>
                        <button class="btn btn-primary" wire:click="accept" wire:loading.attr="disabled">
                            <i data-icon="check" class="i i-sm"></i>
                            <span wire:loading.remove wire:target="accept">Accept and settle</span>
                            <span wire:loading wire:target="accept">Settling…</span>
                        </button>
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body">
                    <div class="empty">
                        <span class="empty-icon"><i data-icon="inspect" class="i i-lg"></i></span>
                        <div class="strong">Nothing to inspect</div>
                        <p class="small muted">Batches appear here once an agent delivers them to the gate.</p>
                    </div>
                </div>
            </div>
        @endif
    </section>

    {{-- Reject sheet --}}
    @if ($showReject && $this->batch)
        <div class="scrim" wire:click.self="$set('showReject', false)">
            <div class="modal" role="dialog" aria-modal="true">
                <div class="card-head">
                    <h3>Reject batch</h3>
                    <button class="btn btn-icon btn-quiet" wire:click="$set('showReject', false)"><i data-icon="close"></i></button>
                </div>
                <div class="card-body col g-14">
                    <div class="callout callout-err">
                        <i data-icon="alert" class="i i-sm"></i>
                        <div>Rejecting pays neither the household nor the agent. Both are notified with the reason.</div>
                    </div>
                    <div class="field">
                        <label class="label">Reason</label>
                        <select class="select" wire:model="rejectionReason">
                            @foreach ($rejectionReasons as $r)
                                <option value="{{ $r }}">{{ $r }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="label">Inspector note</label>
                        <textarea class="textarea" wire:model="inspectorNote" placeholder="What was found, and where"></textarea>
                    </div>
                </div>
                <div class="card-foot row" style="justify-content:flex-end">
                    <button class="btn btn-ghost" wire:click="$set('showReject', false)">Cancel</button>
                    <button class="btn btn-danger" wire:click="reject">Reject batch</button>
                </div>
            </div>
        </div>
    @endif
</div>
