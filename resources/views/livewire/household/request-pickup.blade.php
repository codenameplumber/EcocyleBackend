<div class="col g-16">

    {{-- Pickup point. Agents are sent to these coordinates, not to the written
         address, so it is worth showing the household exactly where the pin is
         -- and letting them correct it before an agent drives to the wrong one. --}}
    <section class="card">
        <div class="card-head">
            <h3>Pickup point</h3>
            @if ($located)
                <span class="badge badge-green"><i data-icon="pin" class="i i-sm"></i> From your device</span>
            @else
                <span class="badge">Registered address</span>
            @endif
        </div>
        <div class="card-body">
            <x-map :pins="$pins" :height="170" :legend="false" />

            <div class="row g-10 mt-12">
                <i data-icon="pin" class="i i-sm" style="color:var(--primary)"></i>
                <span class="grow" style="min-width:0">
                    <div class="small strong truncate">{{ $household->address ?: $household->area?->name }}</div>
                    <div class="tiny dim mono">
                        {{ $lat !== null ? number_format($lat, 5) : '—' }},
                        {{ $lng !== null ? number_format($lng, 5) : '—' }}
                        @if ($accuracy)<span class="dim"> · ±{{ number_format($accuracy) }} m</span>@endif
                    </div>
                </span>
            </div>

            <div class="row g-10 mt-14">
                <button class="btn btn-ghost grow" type="button" data-ec-locate
                        data-target="setCoords">
                    <i data-icon="nav" class="i i-sm"></i>
                    <span data-ec-locate-label>Use my current location</span>
                </button>
                @if ($located)
                    <button class="btn btn-quiet" type="button" wire:click="useRegisteredPoint">Reset</button>
                @endif
            </div>

            <p class="tiny dim mt-10">
                Agents nearby are ranked by how far they are from this pin, so setting it
                where the bags actually are gets you a closer collector.
            </p>
        </div>
    </section>

    {{-- How this compares to what you normally send --}}
    @php($norm = max(1.0, $household->averageKg() ?: $household->predictedKg()))
    <section class="card card-pad">
        <div class="between mb-6">
            <span class="tiny dim">This pickup against your usual {{ number_format($norm, 1) }} kg</span>
            <span class="small strong figure-num">{{ number_format($kg, 1) }} kg</span>
        </div>
        <div class="bar {{ $kg > $norm * 2 ? 'amber' : '' }}">
            <span style="width:{{ min(100, $kg / max(0.1, $norm * 2) * 100) }}%"></span>
        </div>
        @if ($kg > $norm * 2.5)
            <p class="tiny dim mt-6">
                Well above your usual. That is fine — the facility scale settles it either way — but
                an unusually large load is flagged for review, so expect a slower verification.
            </p>
        @endif
    </section>

    {{-- Prediction prompt --}}
    <div class="ai-card">
        <div class="between mb-8">
            <span class="ai-tag"><i data-icon="sparkles" class="i i-sm"></i> Suggested</span>
        </div>
        <div class="strong" style="color:var(--text)">
            We estimate about {{ number_format($household->predictedKg(), 1) }} kg this time
        </div>
        <p class="small muted mt-4">
            From your last eight collections and a household of {{ $household->size }}.
            Adjust it if this pickup is unusual — the facility scale is what settles anyway.
        </p>
    </div>

    {{-- Category --}}
    <section class="card">
        <div class="media media-21x9 media-flat media-scrim">
            <img src="{{ asset('assets/img/story-facility.jpg') }}" alt="Separated bins ready for collection" loading="lazy">
            <div class="media-cap">
                <div class="tiny">Separate what you can — clean streams are graded higher and pay more</div>
            </div>
        </div>
        <div class="card-head"><h3>What are you disposing of?</h3></div>
        <div class="card-body">
            <div class="opt-grid">
                @foreach ($categories as $c)
                    <label class="opt">
                        <input type="radio" wire:model.live="categoryId" value="{{ $c->id }}">
                        <span class="row g-6">
                            <span style="width:8px;height:8px;border-radius:2px;background:{{ $c->color }}"></span>
                            <span class="opt-name">{{ $c->label }}</span>
                        </span>
                        <span class="opt-desc">₦{{ (int) $c->rate_per_kg }} / kg</span>
                    </label>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Volume --}}
    <section class="card">
        <div class="card-head">
            <h3>Roughly how much?</h3>
            <span class="badge">≈ {{ max(1, (int) round($kg / 5)) }} bags</span>
        </div>
        <div class="card-body">
            <div class="between">
                <span class="stat-value figure-num">{{ number_format($kg, 1) }}<small>kg</small></span>
                <span class="right">
                    <div class="tiny dim">Your share (60%)</div>
                    <div class="strong figure-num" style="font-size:17px;color:var(--primary)">
                        ₦{{ number_format($this->estimate['share'], 2) }}
                    </div>
                </span>
            </div>
            <input type="range" wire:model.live="kg" min="2" max="60" step="0.5"
                   style="width:100%;margin-top:14px;accent-color:var(--primary)">
            <div class="between tiny dim"><span>2 kg</span><span>60 kg</span></div>

            <div class="callout mt-16">
                <i data-icon="scale" class="i i-sm"></i>
                <div>
                    <strong>This is an estimate only.</strong>
                    Payment is calculated from the weight the facility scale verifies, not from this figure.
                </div>
            </div>
        </div>
    </section>

    {{-- Window --}}
    <section class="card">
        <div class="card-head"><h3>When suits you?</h3></div>
        <div class="card-body col g-14">
            <div class="row g-8 scroll-x">
                @foreach (['today' => 'Today', 'tomorrow' => 'Tomorrow', 'later' => 'In 2 days'] as $k => $label)
                    <button type="button" class="chip" wire:click="$set('day', '{{ $k }}')"
                            aria-pressed="{{ $day === $k ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="opt-grid">
                @foreach (['Morning' => '8 AM – 12 PM', 'Afternoon' => '12 – 4 PM', 'Evening' => '4 – 8 PM', 'Any time' => 'Fastest match'] as $w => $desc)
                    <label class="opt">
                        <input type="radio" wire:model.live="window" value="{{ $w }}">
                        <span class="opt-name">{{ $w }}</span>
                        <span class="opt-desc">{{ $desc }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Note. This used to be a second "Pickup point" card duplicating the one
         above it, with an Update button wired to a function that did not exist. --}}
    <section class="card">
        <div class="card-head"><h3>Anything the agent should know?</h3></div>
        <div class="card-body">
            <div class="field">
                <label class="label" for="pickup-note">Note for the agent (optional)</label>
                <textarea class="textarea" id="pickup-note" wire:model="note"
                          placeholder="e.g. Blue gate, bags are behind the generator house"></textarea>
            </div>
        </div>
    </section>

    {{-- Summary --}}
    <section class="card">
        <div class="card-head"><h3>Summary</h3></div>
        <div class="card-body">
            <dl>
                <div class="kv"><dt>Category</dt>
                    <dd>{{ $categories->firstWhere('id', $categoryId)?->label }}</dd></div>
                <div class="kv"><dt>Estimated weight</dt><dd class="figure-num">{{ number_format($kg, 1) }} kg</dd></div>
                <div class="kv"><dt>Rate</dt><dd class="figure-num">₦{{ number_format($this->estimate['rate'], 0) }} / kg</dd></div>
                <div class="kv"><dt>Estimated batch value</dt><dd class="figure-num">₦{{ number_format($this->estimate['value'], 2) }}</dd></div>
                <div class="kv"><dt><span class="strong">You receive (60%)</span></dt>
                    <dd class="strong figure-num" style="color:var(--primary)">₦{{ number_format($this->estimate['share'], 2) }}</dd></div>
            </dl>
        </div>
        <div class="card-foot row" style="justify-content:flex-end">
            <a class="btn btn-ghost" href="{{ route('app') }}" wire:navigate>Cancel</a>
            <button class="btn btn-primary grow" wire:click="submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submit">Confirm request</span>
                <span wire:loading wire:target="submit">Sending…</span>
            </button>
        </div>
    </section>
    {{-- Request sent --}}
    @if ($this->created)
        <div class="scrim">
            <div class="modal" role="dialog" aria-modal="true">
                <div class="card-body center" style="padding:28px 22px">
                    <span class="qa-ic" style="width:60px;height:60px;margin:0 auto;border-radius:18px;background:var(--primary-soft);color:var(--primary)">
                        <i data-icon="ok" class="i i-xl"></i>
                    </span>
                    <h2 class="mt-16">Request sent</h2>
                    <p class="small muted mt-4">
                        Nearby agents can see it now. You will be notified the moment one accepts.
                    </p>

                    <div class="qr-tag mt-20" style="text-align:left">
                        {!! app(\App\Services\QrService::class)->svg(url('/b/' . $this->created->code)) !!}
                        <div>
                            <div class="tiny dim">Batch tag</div>
                            <div class="mono strong small">{{ $this->created->code }}</div>
                            <p class="tiny dim mt-8">
                                The agent scans this at pickup. It stays attached through inspection
                                and processing.
                            </p>
                        </div>
                    </div>

                    <div class="row g-10 mt-20">
                        <a class="btn btn-ghost grow" href="{{ route('app') }}" wire:navigate>Done</a>
                        <a class="btn btn-primary grow" href="{{ route('app.track') }}" wire:navigate>Track it</a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
