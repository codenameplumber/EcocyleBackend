<div>
    <div class="page-head">
        <div>
            <h1>Weighbridge</h1>
            <p class="muted">One vehicle, one crossing, one net weight — apportioned across the load on board.</p>
        </div>
        <div class="row g-10 no-print">
            <span class="badge badge-amber">
                <i data-icon="truck" class="i i-sm"></i>
                {{ $arrivals->count() }} inbound · {{ number_format($waitingKg, 0) }} kg declared
            </span>
            <span class="badge">
                <i data-icon="inspect" class="i i-sm"></i>
                {{ $awaiting }} awaiting inspection
            </span>
        </div>
    </div>

    <section class="grid" style="grid-template-columns:340px minmax(0,1fr);gap:16px;align-items:start">

        {{-- Inbound vehicles --}}
        <div class="col g-16">
            <div class="card">
                <div class="card-head"><h3>Inbound</h3><span class="badge">{{ $arrivals->count() }}</span></div>
                <ul class="list">
                    @forelse ($arrivals as $agentId => $row)
                        <li>
                            <button class="item" wire:click="select({{ $agentId }})"
                                    @if ($this->agentId === $agentId) style="background:var(--primary-soft)" @endif>
                                <span class="avatar avatar-sm {{ $row['agent']->user->avatarVariant() }}">
                                    {{ $row['agent']->user->initials() }}
                                </span>
                                <span class="grow" style="min-width:0">
                                    <div class="item-title truncate">{{ $row['agent']->name() }}</div>
                                    <div class="item-sub truncate mono">
                                        {{ $row['agent']->plate ?? $row['agent']->code }} · {{ $row['count'] }} batches
                                    </div>
                                </span>
                                <span class="item-end">
                                    <span class="figure-num strong">{{ number_format($row['declared'], 1) }} kg</span>
                                    <div class="tiny dim mt-4">{{ optional($row['since'])->diffForHumans(short: true) }}</div>
                                </span>
                            </button>
                        </li>
                    @empty
                        <li>
                            <div class="empty">
                                <span class="empty-icon"><i data-icon="ok" class="i i-lg"></i></span>
                                <div class="strong">Gate is clear</div>
                                <p class="small muted">No vehicle is on the road with a load.</p>
                            </div>
                        </li>
                    @endforelse
                </ul>
            </div>

            {{-- Today's crossings --}}
            <div class="card">
                <div class="card-head">
                    <h3>Weighed today</h3>
                    <span class="badge badge-green figure-num">{{ number_format($todayNetKg, 0) }} kg net</span>
                </div>
                <ul class="list">
                    @forelse ($today as $d)
                        <li>
                            <div class="item" style="cursor:default">
                                <span class="grow" style="min-width:0">
                                    <div class="item-title truncate mono">{{ $d->code }}</div>
                                    <div class="item-sub truncate">
                                        {{ $d->agent?->name() ?? '—' }} · {{ $d->batches_count }} batches · {{ $d->weighbridge }}
                                    </div>
                                </span>
                                <span class="item-end">
                                    <span class="figure-num strong">{{ number_format((float) $d->net_kg, 1) }} kg</span>
                                    <div class="tiny dim mt-4">{{ optional($d->weighed_at)->format('H:i') }}</div>
                                </span>
                            </div>
                        </li>
                    @empty
                        <li><div class="empty"><p class="small muted">Nothing has crossed the scale today.</p></div></li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- Workstation --}}
        @if ($this->agent && $this->onBoard->isNotEmpty())
            @php $a = $this->agent; $net = $this->netKg; $vv = $this->vehicleVariance; @endphp
            <div class="col g-16">

                {{-- Vehicle --}}
                <div class="card">
                    <div class="card-head">
                        <div>
                            <h3>{{ $a->name() }}</h3>
                            <div class="tiny dim mt-4">
                                <span class="mono">{{ $a->plate ?? $a->code }}</span> · {{ $a->vehicle_type }}
                                · capacity {{ number_format((float) $a->capacity_kg, 0) }} kg
                                · integrity {{ number_format((float) $a->integrity_score, 0) }}
                            </div>
                        </div>
                        <span class="badge">{{ $this->onBoard->count() }} batches on board</span>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-3" style="gap:14px">
                            <div class="card card-pad card-flat">
                                <div class="tiny dim">Agent declared</div>
                                <div class="stat-value figure-num" style="font-size:22px">
                                    {{ number_format($this->declaredKg, 1) }} kg
                                </div>
                            </div>
                            <div class="card card-pad card-flat">
                                <div class="tiny dim">Gross − tare</div>
                                <div class="stat-value figure-num" style="font-size:22px">
                                    {{ is_numeric($grossKg) ? number_format((float) $grossKg, 1) : '—' }}
                                    <span class="tiny dim">− {{ number_format((float) ($tareKg ?: 0), 1) }}</span>
                                </div>
                            </div>
                            <div class="card card-pad card-flat" style="border-color:var(--primary-line);background:var(--primary-soft)">
                                <div class="tiny" style="color:var(--primary)">Net on the scale</div>
                                <div class="stat-value figure-num" style="font-size:22px;color:var(--primary)">
                                    {{ $net !== null ? number_format($net, 1) . ' kg' : '—' }}
                                </div>
                            </div>
                        </div>

                        @if ($vv !== null)
                            <div class="callout mt-14 {{ abs($vv) > config('ecocycle.variance_tolerance') ? 'callout-warn' : '' }}">
                                <i data-icon="scale" class="i i-sm"></i>
                                <div>
                                    <div class="tiny dim">Vehicle variance against declaration</div>
                                    <div class="strong figure-num" style="font-size:16px">
                                        {{ $vv > 0 ? '+' : '' }}{{ number_format($vv, 2) }}%
                                        <span class="small muted">
                                            @if (abs($vv) > config('ecocycle.variance_tolerance'))
                                                — beyond the {{ config('ecocycle.variance_tolerance') }}% tolerance, this will flag on inspection
                                            @else
                                                — within tolerance
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- The crossing --}}
                <div class="card">
                    <div class="card-head"><h3>Record the crossing</h3></div>
                    <div class="card-body">
                        <div class="grid grid-3" style="gap:14px;align-items:end">
                            <label class="field">
                                <span class="label">Gross weight (kg)</span>
                                <input class="input figure-num" type="number" step="0.1" min="0"
                                       wire:model.live.debounce.300ms="grossKg" placeholder="0.0">
                                @error('grossKg') <span class="tiny" style="color:var(--danger)">{{ $message }}</span> @enderror
                            </label>
                            <label class="field">
                                <span class="label">Tare (kg)</span>
                                <input class="input figure-num" type="number" step="0.1" min="0"
                                       wire:model.live.debounce.300ms="tareKg" placeholder="0.0">
                                @error('tareKg') <span class="tiny" style="color:var(--danger)">{{ $message }}</span> @enderror
                            </label>
                            <label class="field">
                                <span class="label">Weighbridge</span>
                                <select class="input" wire:model="weighbridge">
                                    @foreach ($weighbridges as $wb)
                                        <option value="{{ $wb }}">{{ $wb }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="row wrap g-10 mt-16">
                            <button class="btn btn-ghost" wire:click="takeReading">
                                <i data-icon="scale" class="i i-sm"></i> Take reading
                            </button>
                            <div class="grow"></div>
                            <button class="btn btn-primary" wire:click="weighIn"
                                    wire:loading.attr="disabled" wire:target="weighIn"
                                    @disabled($net === null || $net <= 0)>
                                <i data-icon="check" class="i i-sm"></i>
                                Weigh in {{ $this->onBoard->count() }} batches
                            </button>
                        </div>

                        <p class="tiny dim mt-12">
                            The net is apportioned across the load in proportion to what was declared per
                            batch. Nothing is valued here — each batch moves to the inspection queue, and
                            the inspector's reading is what settles.
                        </p>
                    </div>
                </div>

                {{-- Apportionment --}}
                <div class="card">
                    <div class="card-head">
                        <h3>How the net lands</h3>
                        @if ($net !== null && $net > 0)
                            <span class="badge">preview</span>
                        @endif
                    </div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Batch</th>
                                    <th>Household</th>
                                    <th>Stream</th>
                                    <th class="num">Declared</th>
                                    <th class="num">Apportioned</th>
                                    <th class="num">Variance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->split as $row)
                                    @php $b = $row['batch']; $v = $row['variance']; @endphp
                                    <tr>
                                        <td class="mono">{{ $b->shortCode() }}</td>
                                        <td class="truncate">{{ $b->household->name() }}</td>
                                        <td><span class="st {{ $b->status->cssClass() }}">{{ $b->category?->label }}</span></td>
                                        <td class="num figure-num">{{ number_format((float) $b->collected_kg, 1) }}</td>
                                        <td class="num figure-num strong">{{ number_format($row['kg'], 2) }}</td>
                                        <td class="num figure-num {{ $v !== null && abs($v) > config('ecocycle.variance_tolerance') ? 't-neg' : 'dim' }}">
                                            {{ $v === null ? '—' : ($v > 0 ? '+' : '') . number_format($v, 2) . '%' }}
                                        </td>
                                    </tr>
                                @empty
                                    @foreach ($this->onBoard as $b)
                                        <tr>
                                            <td class="mono">{{ $b->shortCode() }}</td>
                                            <td class="truncate">{{ $b->household->name() }}</td>
                                            <td><span class="st {{ $b->status->cssClass() }}">{{ $b->category?->label }}</span></td>
                                            <td class="num figure-num">{{ number_format((float) $b->collected_kg, 1) }}</td>
                                            <td class="num dim">—</td>
                                            <td class="num dim">—</td>
                                        </tr>
                                    @endforeach
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="strong">Total</td>
                                    <td class="num figure-num strong">{{ number_format($this->declaredKg, 1) }}</td>
                                    <td class="num figure-num strong">{{ $net !== null ? number_format($net, 2) : '—' }}</td>
                                    <td class="num figure-num {{ $vv !== null && abs($vv) > config('ecocycle.variance_tolerance') ? 't-neg' : 'dim' }}">
                                        {{ $vv === null ? '—' : ($vv > 0 ? '+' : '') . number_format($vv, 2) . '%' }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @else
            <div class="card card-pad">
                <div class="empty">
                    <span class="empty-icon"><i data-icon="truck" class="i i-lg"></i></span>
                    <div class="strong">No vehicle on the plate</div>
                    <p class="small muted" style="max-width:38ch">
                        A load appears here once an agent marks it in transit. Nothing crosses the scale
                        until it has left the last doorstep.
                    </p>
                </div>
            </div>
        @endif
    </section>
</div>
