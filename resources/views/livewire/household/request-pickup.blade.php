<div class="col g-16">

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

    {{-- Pickup point --}}
    <section class="card">
        <div class="card-head">
            <h3>Pickup point</h3>
            <button class="btn btn-sm btn-quiet" onclick="ecPickupLocate(this)">Update</button>
        </div>
        <div class="card-body">
            <div class="row g-10">
                <i data-icon="pin" class="i i-sm" style="color:var(--primary)"></i>
                <span class="grow">
                    <div class="small strong">{{ $household->address ?? 'Address not set' }}</div>
                    <div class="tiny dim mono">
                        {{ $lat ? number_format($lat, 5) : '—' }}, {{ $lng ? number_format($lng, 5) : '—' }}
                    </div>
                </span>
            </div>
            <div class="field mt-14">
                <label class="label">Note for the agent (optional)</label>
                <textarea class="textarea" wire:model="note"
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
</div>

@push('scripts')
<script>
function ecPickupLocate(btn) {
    if (!navigator.geolocation) { EC.toast('Geolocation unavailable', 'warn'); return; }
    navigator.geolocation.getCurrentPosition(function (pos) {
        window.Livewire.find(btn.closest('[wire\\:id]').getAttribute('wire:id'))
            .call('setCoords', pos.coords.latitude, pos.coords.longitude);
    }, function () { EC.toast('Permission denied', 'warn'); }, { enableHighAccuracy: true, timeout: 8000 });
}
</script>
@endpush
