<div>
    <div class="page-head">
        <div>
            <h1>Waste pipeline</h1>
            <p class="muted">Every batch by state. Columns follow the state machine.</p>
        </div>
    </div>

    <section class="board">
        @foreach ($columns as $col)
            <div class="board-col">
                <div class="board-head">
                    <h4>{{ $col['status']->label() }}</h4>
                    <span class="badge">{{ $col['count'] }}</span>
                </div>
                <div style="padding:8px 13px;border-bottom:1px solid var(--border)">
                    <span class="tiny dim figure-num">{{ number_format($col['kg'], 1) }} kg</span>
                </div>
                <div class="board-list">
                    @forelse ($col['batches'] as $b)
                        <div class="board-card" wire:key="pb-{{ $b->id }}"
                             wire:click="show({{ $b->id }})" style="cursor:pointer">
                            <div class="between">
                                <span class="mono tiny">{{ $b->shortCode() }}</span>
                                <span class="tiny dim">{{ $b->updated_at->diffForHumans(short: true) }}</span>
                            </div>
                            <div class="small strong truncate">{{ $b->household->name() }}</div>
                            <div class="between">
                                <span class="tiny dim">{{ $b->effectiveCategory()?->label }}</span>
                                <span class="tiny strong figure-num">
                                    {{ $b->bestKnownKg() ? number_format($b->bestKnownKg(), 1) . ' kg' : '—' }}
                                </span>
                            </div>
                            @if ($b->agent)
                                <div class="tiny dim truncate">{{ $b->agent->name() }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="tiny dim center" style="padding:16px 0">Empty</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </section>

    <p class="tiny dim mt-16">
        Columns follow <span class="mono">BatchStatus</span> exactly. Because every stage is a real state
        rather than a label, this board is the database rather than a report drawn over it.
    </p>
    {{-- Batch record --}}
    @if ($this->openBatch)
        <div class="scrim" wire:click.self="close">
            <div class="modal" role="dialog" aria-modal="true">
                <x-batch-sheet :batch="$this->openBatch" />
            </div>
        </div>
    @endif
</div>
