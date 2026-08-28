<div>
    <div class="page-head">
        <div>
            <h1>Waste pipeline</h1>
            <p class="muted">Every batch by state. Open a row to see the record and move it on.</p>
        </div>
        <div class="row g-10 no-print">
            <span class="badge">{{ number_format($shownKg, 0) }} kg shown</span>
            <span class="badge badge-amber">{{ $openCount }} open</span>
        </div>
    </div>

    {{-- Filters. The counts are the point: they say where the work has piled up
         before you decide what to open. --}}
    <section class="card card-pad mb-16">
        <div class="row wrap g-8">
            <button class="chip" wire:click="setFilter('open')"
                    aria-pressed="{{ $filter === 'open' ? 'true' : 'false' }}">
                Open <span class="dim">{{ $openCount }}</span>
            </button>
            <button class="chip" wire:click="setFilter('all')"
                    aria-pressed="{{ $filter === 'all' ? 'true' : 'false' }}">All</button>

            <span class="sep dim">·</span>

            @foreach ($states as $s)
                @php $c = $counts[$s->value] ?? null; @endphp
                <button class="chip" wire:click="setFilter('{{ $s->value }}')"
                        aria-pressed="{{ $filter === $s->value ? 'true' : 'false' }}">
                    {{ $s->label() }} <span class="dim">{{ $c?->c ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        <div class="field mt-14">
            <input class="input" type="search" wire:model.live.debounce.300ms="search"
                   placeholder="Search a batch code, household or agent…">
        </div>
    </section>

    {{-- The table --}}
    <section class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Batch</th>
                        <th>Household</th>
                        <th>Agent</th>
                        <th>Stream</th>
                        <th class="num">Weight</th>
                        <th>Status</th>
                        <th class="num">Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($batches as $b)
                        <tr wire:key="pb-{{ $b->id }}" wire:click="show({{ $b->id }})" style="cursor:pointer">
                            <td class="mono">{{ $b->shortCode() }}</td>
                            <td class="truncate">{{ $b->household?->name() ?? '—' }}</td>
                            <td class="truncate">{{ $b->agent?->name() ?? '—' }}</td>
                            <td class="truncate">{{ $b->effectiveCategory()?->label ?? '—' }}</td>
                            <td class="num figure-num">
                                {{ $b->bestKnownKg() ? number_format($b->bestKnownKg(), 1) : '—' }}
                                @if ($b->verified_kg)
                                    <span class="tiny dim">verified</span>
                                @elseif ($b->collected_kg)
                                    <span class="tiny dim">declared</span>
                                @elseif ($b->expected_kg)
                                    <span class="tiny dim">expected</span>
                                @endif
                            </td>
                            <td><span class="st {{ $b->status->cssClass() }}">{{ $b->status->label() }}</span></td>
                            <td class="num tiny dim">{{ $b->updated_at->diffForHumans(short: true) }}</td>
                            <td class="num">
                                <button class="btn btn-sm btn-quiet" wire:click.stop="show({{ $b->id }})">
                                    Open
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty">
                                    <span class="empty-icon"><i data-icon="package" class="i i-lg"></i></span>
                                    <div class="strong">Nothing matches</div>
                                    <p class="small muted">
                                        {{ $search !== '' ? 'No batch matches that search.' : 'No batch is in this state.' }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($batches->hasPages())
            <div class="card-body">{{ $batches->links() }}</div>
        @endif
    </section>

    <p class="tiny dim mt-16">
        States follow <span class="mono">BatchStatus</span> exactly, and so do the moves offered on a
        record — they are read from <span class="mono">allowedNext()</span>, so nothing on this screen
        can put a batch somewhere the state machine would refuse.
    </p>

    {{-- Batch record, with the moves attached --}}
    @if ($this->openBatch)
        @php $b = $this->openBatch; @endphp
        <div class="scrim" wire:click.self="close">
            <div class="modal modal-wide" role="dialog" aria-modal="true">
                <x-batch-sheet :batch="$b" />

                <div class="modal-actions card-body">
                    <div class="between mb-10">
                        <span class="label">Move this batch</span>
                        <span class="st {{ $b->status->cssClass() }}">{{ $b->status->label() }}</span>
                    </div>

                    @if ($this->nextStates)
                        {{-- A rejection has to say why; the reason is stored on the
                             batch and shown to the household, so it cannot be blank. --}}
                        @if (collect($this->nextStates)->contains(\App\Enums\BatchStatus::Rejected))
                            <div class="field mb-12">
                                <label class="label" for="pipeline-note">Reason / note</label>
                                <input class="input" id="pipeline-note" wire:model="note"
                                       placeholder="Required when rejecting">
                            </div>
                        @endif

                        <div class="row wrap g-8">
                            @foreach ($this->nextStates as $next)
                                @if ($next === \App\Enums\BatchStatus::Verified)
                                    <a class="btn btn-primary" href="{{ route('console.inspection') }}" wire:navigate>
                                        <i data-icon="scale" class="i i-sm"></i> Weigh on the bench
                                    </a>
                                @else
                                    <button class="btn {{ $next->isTerminal() ? 'btn-ghost' : 'btn-primary' }}"
                                            wire:click="moveTo('{{ $next->value }}')"
                                            wire:loading.attr="disabled">
                                        <i data-icon="{{ $next->icon() }}" class="i i-sm"></i>
                                        {{ $next->label() }}
                                    </button>
                                @endif
                            @endforeach
                        </div>

                        <p class="tiny dim mt-12">
                            Every move is validated by the state machine and writes a
                            <span class="mono">batch_events</span> row naming you. Verification is not
                            offered here — settlement is computed from a scale reading, so it belongs
                            on the inspection bench.
                        </p>
                    @else
                        <div class="callout">
                            <i data-icon="ok" class="i i-sm"></i>
                            <div>
                                <strong>{{ $b->status->label() }} is final.</strong>
                                This batch has nowhere further to go.
                            </div>
                        </div>
                    @endif

                    <div class="row mt-14" style="justify-content:flex-end">
                        <button class="btn btn-ghost" wire:click="close">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
