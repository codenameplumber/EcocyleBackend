<div>
    <div class="page-head">
        <div>
            <h1>Analytics and intelligence</h1>
            <p class="muted">Plain-language answers computed from your own figures.</p>
        </div>
        <div class="row g-10 no-print">
            <span class="badge {{ $aiEnabled ? 'badge-violet' : 'badge-amber' }}">
                {{ $aiEnabled ? 'Claude connected' : 'No API key — local answers' }}
            </span>
        </div>
    </div>

    {{-- Question box --}}
    <section class="card mb-16">
        <div class="card-head">
            <div class="row g-8">
                <i data-icon="sparkles" class="i i-sm" style="color:var(--violet)"></i>
                <h3>Ask about the network</h3>
            </div>
            <span class="badge badge-violet">Natural language</span>
        </div>
        <div class="card-body">
            <form wire:submit="ask" class="row g-10">
                <div class="input-group grow">
                    <i data-icon="search" class="i i-sm"></i>
                    <input class="input" wire:model="question" placeholder="What will we receive next week?">
                </div>
                <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="ask">Ask</span>
                    <span wire:loading wire:target="ask">Thinking…</span>
                </button>
            </form>

            <div class="row g-6 wrap mt-12">
                @foreach ($suggestions as $s)
                    <button class="chip" wire:click="useSuggestion('{{ addslashes($s) }}')">{{ $s }}</button>
                @endforeach
            </div>

            @if ($answer)
                <div class="ai-card mt-16">
                    <div class="between mb-8">
                        <span class="ai-tag">
                            <i data-icon="sparkles" class="i i-sm"></i>
                            {{ $answer['generated'] ? 'Generated answer' : 'Computed answer' }}
                        </span>
                        <span class="tiny dim">{{ $answer['source'] }}</span>
                    </div>
                    <p style="color:var(--text);line-height:1.6">{{ $answer['answer'] }}</p>
                    @unless ($answer['generated'])
                        <p class="tiny dim mt-8">
                            No Claude key is configured, so this was assembled locally from the same figures.
                            Add <span class="mono">ANTHROPIC_API_KEY</span> to <span class="mono">.env</span> for
                            full natural-language answers.
                        </p>
                    @endunless
                </div>
            @endif
        </div>
    </section>

    <section class="grid mb-16" style="grid-template-columns:1fr 1fr;gap:16px">
        <div class="card">
            <div class="card-head"><h3>Intake, last 7 days</h3><span class="badge">kg received</span></div>
            <div class="card-body">
                <svg class="chart" data-chart="bars" data-height="190"
                     data-values="{{ $intake->implode(',') }}"
                     data-labels="{{ $labels->implode(',') }}"></svg>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3>Where the margin comes from</h3>
                <span class="badge badge-amber">strategy</span>
            </div>
            <div class="card-body">
                @forelse ($margin as $m)
                    @php
                        $kgPct = $m->kg / $marginKg * 100;
                        $valPct = $m->value / $marginNgn * 100;
                    @endphp
                    <div style="padding:7px 0">
                        <div class="between mb-4">
                            <span class="row g-8 small">
                                <span style="width:9px;height:9px;border-radius:2px;background:{{ $m->color }}"></span>
                                {{ $m->label }}
                            </span>
                            <span class="tiny dim">
                                {{ number_format($kgPct, 1) }}% of tonnage ·
                                <span class="strong" style="color:var(--text)">{{ number_format($valPct, 1) }}% of value</span>
                            </span>
                        </div>
                        <div class="bar"><span style="width:{{ $valPct }}%;background:{{ $m->color }}"></span></div>
                    </div>
                @empty
                    <p class="small muted">Nothing settled yet.</p>
                @endforelse

                <div class="callout callout-warn mt-14">
                    <i data-icon="alert" class="i i-sm"></i>
                    <div>
                        Tonnage and margin are not the same league table. Organics fill the digester and do
                        the environmental work; the high-rate streams are what actually pay for the round.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><h3>What the model is allowed to know</h3><span class="badge">transparency</span></div>
        <div class="card-body">
            <p class="small muted">
                The model never touches the database. Aggregates are computed in SQL first and handed over as
                a fixed set of figures, with an instruction to use only those numbers and to say what is
                missing rather than estimate. That is why an answer here cannot invent a figure — and why it
                still works, in reduced form, with no API key at all.
            </p>
        </div>
    </section>
</div>
