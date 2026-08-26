<div class="col g-16">
    <section class="card card-flat">
        <div class="card-body">
            <div class="row g-12">
                <span class="avatar avatar-lg {{ auth()->user()->avatarVariant() }}">{{ auth()->user()->initials() }}</span>
                <span class="grow">
                    <div class="item-title">{{ auth()->user()->name }}</div>
                    <div class="item-sub mono">{{ $agent->code }} · {{ $agent->vehicle_type }} · {{ $agent->capacity_kg }} kg</div>
                </span>
                @if ($agent->kyc_verified_at)
                    <span class="badge badge-green"><i data-icon="shield" class="i i-sm"></i> KYC</span>
                @endif
            </div>
            <div class="grid grid-keep-3 mt-16" style="gap:12px">
                <div><div class="strong figure-num">{{ $trips }}</div><div class="tiny dim">Trips</div></div>
                <div><div class="strong figure-num">{{ number_format($lifetime, 1) }} kg</div><div class="tiny dim">Lifetime</div></div>
                <div><div class="strong figure-num">₦{{ number_format($earned, 0) }}</div><div class="tiny dim">Earned</div></div>
            </div>
        </div>
    </section>

    <section class="card card-flat">
        <div class="card-head">
            <h3>Integrity score</h3>
            <span class="badge {{ $agent->integrity_score >= 90 ? 'badge-green' : 'badge-amber' }}">
                {{ $agent->integrity_score >= 90 ? 'Good standing' : 'Under review' }}
            </span>
        </div>
        <div class="card-body">
            <div class="row g-16">
                <svg style="width:104px;flex:none" data-chart="ring"
                     data-value="{{ (int) $agent->integrity_score }}" data-label="score"></svg>
                <div class="grow col g-8">
                    <div class="between small"><span class="muted">Weight accuracy</span>
                        <span class="strong figure-num">{{ $accuracy }}%</span></div>
                    <div class="between small"><span class="muted">Rejected batches</span>
                        <span class="strong figure-num">{{ $rejected }} of {{ $trips + $rejected }}</span></div>
                    <div class="between small"><span class="muted">Open flags</span>
                        <span class="strong figure-num">{{ $flags }}</span></div>
                    <div class="between small"><span class="muted">Rating</span>
                        <span class="strong figure-num">{{ $agent->rating }} ★</span></div>
                </div>
            </div>
            <div class="callout {{ $agent->integrity_score >= 90 ? 'callout-ok' : 'callout-warn' }} mt-14">
                <i data-icon="{{ $agent->integrity_score >= 90 ? 'ok' : 'alert' }}" class="i i-sm"></i>
                <div>
                    {{ $agent->integrity_score >= 90
                        ? 'Your declarations track the facility scale closely, which keeps you in the priority matching pool.'
                        : 'Your declared weights are drifting from the scale. Recheck your weighing before each pickup.' }}
                </div>
            </div>
        </div>
    </section>

    <section class="card card-flat">
        <div class="card-head"><h3>Vehicle and capacity</h3></div>
        <div class="card-body col g-14">
            <div class="field">
                <label class="label">Full name</label>
                <input class="input" wire:model="name">
                @error('name') <span class="hint t-neg">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="label">Phone number</label>
                <input class="input" wire:model="phone">
                @error('phone') <span class="hint t-neg">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="label">Vehicle type</label>
                <select class="select" wire:model="vehicle_type">
                    <option>Tricycle</option><option>Motorcycle</option><option>Pickup van</option><option>On foot</option>
                </select>
            </div>
            <div class="grid grid-keep-2" style="gap:12px">
                <div class="field">
                    <label class="label">Capacity</label>
                    <div class="input-group">
                        <input class="input" type="number" wire:model="capacity_kg">
                        <span class="input-suffix">kg</span>
                    </div>
                    @error('capacity_kg') <span class="hint t-neg">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="label">Plate</label>
                    <input class="input mono" wire:model="plate">
                </div>
            </div>
            <div class="field">
                <label class="label">Operating area</label>
                <input class="input" wire:model="operating_area">
            </div>
            <button class="btn btn-primary btn-block" wire:click="save">
                <span wire:loading.remove wire:target="save">Save changes</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </section>

    {{-- Availability --}}
    <section class="card">
        <div class="card-head"><h3>Availability</h3></div>
        <div class="card-body col g-14">
            <div class="row g-8 wrap">
                @foreach ([1 => 'M', 2 => 'T', 3 => 'W', 4 => 'T', 5 => 'F', 6 => 'S', 7 => 'S'] as $n => $letter)
                    <button class="chip" wire:key="day-{{ $n }}" wire:click="toggleDay({{ $n }})"
                            aria-pressed="{{ in_array($n, $days, true) ? 'true' : 'false' }}">{{ $letter }}</button>
                @endforeach
            </div>

            <div class="grid grid-2" style="gap:12px">
                <div class="field">
                    <label class="label">From</label>
                    <input class="input" type="time" wire:model.blur="from" wire:change="saveHours">
                    @error('from') <span class="hint t-neg">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="label">Until</label>
                    <input class="input" type="time" wire:model.blur="until" wire:change="saveHours">
                    @error('until') <span class="hint t-neg">{{ $message }}</span> @enderror
                </div>
            </div>

            @foreach ([
                'accept_offers' => 'Offer me jobs outside my area when they pay well',
                'weekend_shifts' => 'Include me in weekend rounds',
            ] as $key => $label)
                <label class="switch" wire:key="ap-{{ $key }}">
                    <input type="checkbox" wire:click="toggle('{{ $key }}')" @checked($prefs[$key] ?? false)>
                    <span class="track"></span>
                    <span class="small">{{ $label }}</span>
                </label>
            @endforeach

            <p class="tiny dim">
                Availability shapes which jobs you are offered. It does not stop you taking anything
                on the marketplace — an open job is open to every agent with capacity.
            </p>
        </div>
    </section>

    {{-- Notifications --}}
    <x-payout-account :account="$account" :banks="$banks" :cycles="$cycles"
                      :show-payout="$showPayout" :payout-auto="$payoutAuto" />

    <section class="card">
        <div class="card-head">
            <h3>Notifications</h3>
            <a class="small strong" style="color:var(--primary)" href="{{ route('agent.notifications') }}" wire:navigate>Open feed</a>
        </div>
        <div class="card-body col g-14">
            @foreach ([
                'notify_new_jobs' => 'New jobs near me',
                'notify_verified' => 'Batch verified at the gate',
                'notify_payouts' => 'Payout processed',
            ] as $key => $label)
                <label class="switch" wire:key="an-{{ $key }}">
                    <input type="checkbox" wire:click="toggle('{{ $key }}')" @checked($prefs[$key] ?? false)>
                    <span class="track"></span>
                    <span class="small">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </section>

    {{-- Documents --}}
    <section class="card">
        <div class="card-head">
            <h3>Documents</h3>
            <span class="badge {{ $agent->kyc_verified_at ? 'badge-green' : 'badge-amber' }}">
                {{ $agent->kyc_verified_at ? 'KYC verified' : 'KYC pending' }}
            </span>
        </div>
        <ul class="list">
            @foreach ($docs as $key => $doc)
                <li>
                    <div class="item">
                        <i data-icon="{{ ['id' => 'shield', 'address' => 'pin', 'vehicle' => 'truck', 'scale' => 'scale'][$key] ?? 'file' }}"
                           class="i i-sm"
                           style="color:var({{ $doc['status'] === 'verified' ? '--success' : ($doc['status'] === 'missing' ? '--danger' : '--warn') }})"></i>
                        <span class="grow" style="min-width:0">
                            <div class="item-title">{{ $doc['label'] }}</div>
                            @if ($doc['note'])<div class="item-sub truncate">{{ $doc['note'] }}</div>@endif
                        </span>
                        <span class="badge {{ $doc['status'] === 'verified' ? 'badge-green' : ($doc['status'] === 'missing' ? 'badge-red' : 'badge-amber') }}">
                            {{ ucfirst($doc['status']) }}
                        </span>
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="card-body">
            <p class="tiny dim">
                Documents are verified by the facility team by hand during the pilot. Uploading
                replacements from the app is not built yet — send them to
                <a href="mailto:ops@ecocycle.ng" style="color:var(--primary)">ops@ecocycle.ng</a>.
            </p>
        </div>
    </section>
</div>
