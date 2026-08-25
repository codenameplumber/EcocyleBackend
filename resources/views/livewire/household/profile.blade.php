<div class="col g-16">
    <section class="card card-flat">
        <div class="card-body">
            <div class="row g-12">
                <span class="avatar avatar-lg {{ auth()->user()->avatarVariant() }}">{{ auth()->user()->initials() }}</span>
                <span class="grow">
                    <div class="item-title">{{ auth()->user()->name }}</div>
                    <div class="item-sub mono">{{ $household->code }} · {{ $household->area?->name }}</div>
                </span>
                <span class="badge badge-green"><span class="dot"></span>{{ ucfirst($household->status) }}</span>
            </div>
            <div class="grid grid-keep-3 mt-16" style="gap:12px">
                <div><div class="strong figure-num">{{ $batchCount }}</div><div class="tiny dim">Batches</div></div>
                <div><div class="strong figure-num">{{ number_format($lifetimeKg, 1) }} kg</div><div class="tiny dim">Contributed</div></div>
                <div><div class="strong figure-num">{{ $household->rating }} ★</div><div class="tiny dim">Rating</div></div>
            </div>
        </div>
    </section>

    <section class="card card-flat">
        <div class="card-head"><h3>Household details</h3></div>
        <div class="card-body col g-14">
            <div class="field">
                <label class="label">Full name</label>
                <input class="input" wire:model="name">
                @error('name') <span class="hint t-neg">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="label">Phone number</label>
                <input class="input" wire:model="phone">
                <span class="hint">How an agent reaches you at the gate.</span>
                @error('phone') <span class="hint t-neg">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="label">Email</label>
                <input class="input" value="{{ auth()->user()->email }}" disabled>
                <span class="hint">Your sign-in address.</span>
            </div>
            <div class="field">
                <label class="label">Address</label>
                <input class="input" wire:model="address">
            </div>
            <div class="field">
                <label class="label">Area</label>
                <select class="select" wire:model="area_id">
                    @foreach ($areas as $a)
                        <option value="{{ $a->id }}">{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-keep-2" style="gap:12px">
                <div class="field">
                    <label class="label">Household size</label>
                    <input class="input" type="number" wire:model="size">
                </div>
                <div class="field">
                    <label class="label">Frequency</label>
                    <select class="select" wire:model="frequency">
                        <option>Weekly</option><option>Twice/week</option><option>Bi-weekly</option>
                    </select>
                </div>
            </div>
            <div class="field">
                <label class="label">Usual waste category</label>
                <select class="select" wire:model="default_category_id">
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}">{{ $c->label }} — ₦{{ (int) $c->rate_per_kg }}/kg</option>
                    @endforeach
                </select>
            </div>

            <div class="tiny dim">
                Coordinates
                <span class="mono">{{ $household->lat ? number_format($household->lat, 5) : '—' }},
                {{ $household->lng ? number_format($household->lng, 5) : '—' }}</span>
                — agents match on these, not on the written address.
            </div>

            <button class="btn btn-primary btn-block" wire:click="save">
                <span wire:loading.remove wire:target="save">Save changes</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </section>
</div>
