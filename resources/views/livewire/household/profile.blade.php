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

    @if ($pins)
        <section class="card" style="overflow:hidden">
            <div class="card-head"><h3>Where we collect from</h3><span class="badge">registered pin</span></div>
            <x-map :pins="$pins" :height="150" :legend="false" />
            <div class="card-body">
                <p class="tiny dim">
                    Agents are matched and routed on this pin, not on the written address.
                    If it is wrong, set it again from the request screen while you are at home.
                </p>
            </div>
        </section>
    @endif

    {{-- Collection preferences --}}
    <section class="card">
        <div class="card-head"><h3>Collection preferences</h3></div>
        <div class="card-body col g-14">
            <div class="field">
                <label class="label">Usual waste category</label>
                <div class="opt-grid">
                    @foreach ($categories as $c)
                        <label class="opt" wire:key="cat-{{ $c->id }}">
                            <input type="radio" name="usual-category" value="{{ $c->id }}"
                                   wire:click="setCategory({{ $c->id }})"
                                   @checked($default_category_id === $c->id)>
                            <span class="opt-name">{{ $c->label }}</span>
                            <span class="opt-desc figure-num">₦{{ (int) $c->rate_per_kg }}/kg</span>
                        </label>
                    @endforeach
                </div>
                <span class="hint">This is what a new request is pre-filled with. The inspector's grading always wins over it.</span>
            </div>

            @foreach ([
                'remind_when_due' => 'Remind me when a pickup is due',
                'allow_agent_call' => 'Let agents call me before arrival',
                'auto_request' => 'Auto-request on my usual schedule',
            ] as $key => $label)
                <label class="switch" wire:key="pref-{{ $key }}">
                    <input type="checkbox" wire:click="toggle('{{ $key }}')" @checked($prefs[$key] ?? false)>
                    <span class="track"></span>
                    <span class="small">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </section>

    {{-- Everything else --}}
    <x-payout-account :account="$account" :banks="$banks" :cycles="$cycles"
                      :show-payout="$showPayout" :payout-auto="$payoutAuto" />

    <section class="card">
        <div class="card-head"><h3>Account</h3></div>
        <ul class="list">
            <li>
                <a class="item" href="{{ route('app.notifications') }}" wire:navigate>
                    <i data-icon="bell" class="i i-sm dim"></i>
                    <span class="grow item-title">Notifications</span>
                    <i data-icon="chevR" class="i i-sm dim"></i>
                </a>
            </li>
            <li>
                <a class="item" href="{{ route('app.history') }}" wire:navigate>
                    <i data-icon="history" class="i i-sm dim"></i>
                    <span class="grow item-title">Collection history</span>
                    <i data-icon="chevR" class="i i-sm dim"></i>
                </a>
            </li>
            <li>
                <a class="item" href="{{ route('app.wallet') }}" wire:navigate>
                    <i data-icon="wallet" class="i i-sm dim"></i>
                    <span class="grow item-title">Wallet and payouts</span>
                    <i data-icon="chevR" class="i i-sm dim"></i>
                </a>
            </li>
            <li>
                <a class="item" href="mailto:ops@ecocycle.ng">
                    <i data-icon="mail" class="i i-sm dim"></i>
                    <span class="grow item-title">Contact the facility</span>
                    <i data-icon="chevR" class="i i-sm dim"></i>
                </a>
            </li>
        </ul>
    </section>

    {{-- Notifications --}}
    <section class="card">
        <div class="card-head">
            <h3>Notifications</h3>
            <a class="small strong" style="color:var(--primary)" href="{{ route('app.notifications') }}" wire:navigate>Open feed</a>
        </div>
        <div class="card-body col g-14">
            @foreach ([
                'notify_accepted' => 'Agent accepted my request',
                'notify_verified' => 'Batch verified at the facility',
                'notify_credited' => 'Payment credited',
                'notify_weekly' => 'Weekly impact summary',
            ] as $key => $label)
                <label class="switch" wire:key="note-{{ $key }}">
                    <input type="checkbox" wire:click="toggle('{{ $key }}')" @checked($prefs[$key] ?? false)>
                    <span class="track"></span>
                    <span class="small">{{ $label }}</span>
                </label>
            @endforeach
            <p class="tiny dim">
                These control email. Every event still lands in the in-app feed, because the feed is
                the record of what happened to your waste, not a marketing channel.
            </p>
        </div>
    </section>
</div>
