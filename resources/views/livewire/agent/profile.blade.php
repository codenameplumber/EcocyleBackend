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
        <div class="card-head"><h3>Vehicle and contact</h3></div>
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
</div>
