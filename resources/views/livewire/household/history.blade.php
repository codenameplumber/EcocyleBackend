<div>
    {{-- Summary. Every figure is the household's own lifetime total, not a window. --}}
    <section class="card">
        <div class="card-body">
            <div class="grid grid-3" style="gap:12px">
                <div>
                    <div class="stat-value" style="font-size:20px">{{ number_format($totalKg, 0) }}<small>kg</small></div>
                    <div class="tiny dim">Total verified</div>
                </div>
                <div>
                    <div class="stat-value" style="font-size:20px">{{ number_format($totalCount) }}</div>
                    <div class="tiny dim">Batches</div>
                </div>
                <div>
                    <div class="stat-value figure-num" style="font-size:20px">₦{{ number_format($totalEarn, 0) }}</div>
                    <div class="tiny dim">Earned</div>
                </div>
            </div>

            @if (count($trend) > 1)
                <div class="divider mt-16 mb-16"></div>
                <div class="between mb-8">
                    <span class="tiny dim">Verified weight per collection</span>
                    <span class="tiny dim">Last {{ count($trend) }}</span>
                </div>
                <svg class="chart" data-chart="bars" data-height="120"
                     data-values="{{ implode(',', $trend) }}"
                     data-labels="{{ implode(',', range(1, count($trend))) }}"
                     data-color="var(--primary)"></svg>
            @endif
        </div>
    </section>

    <div class="row g-8 scroll-x">
        @foreach ([
            'all' => 'All',
            'open' => 'In progress',
            \App\Enums\BatchStatus::Processed->value => 'Processed',
            \App\Enums\BatchStatus::Verified->value => 'Verified',
            \App\Enums\BatchStatus::Rejected->value => 'Rejected',
        ] as $key => $label)
            <button class="chip" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                    wire:click="setFilter('{{ $key }}')">{{ $label }}</button>
        @endforeach
    </div>

    <section class="card">
        <div class="card-head">
            <h3>History</h3>
            <span class="tiny dim">{{ $batches->total() }} {{ Str::plural('batch', $batches->total()) }}</span>
        </div>

        <ul class="list">
            @forelse ($batches as $b)
                @php
                    $kg = $b->verified_kg ?? $b->collected_kg ?? $b->expected_kg;
                    $paid = $b->status === \App\Enums\BatchStatus::Rejected ? 0 : (float) $b->household_share;
                @endphp
                <li>
                    <button class="item" wire:click="show({{ $b->id }})" wire:key="b-{{ $b->id }}">
                        <span class="qa-ic" style="width:34px;height:34px;background:var(--surface-3);color:var(--text-2)">
                            <i data-icon="{{ $b->status->icon() }}" class="i i-sm"></i>
                        </span>
                        <span class="grow" style="min-width:0">
                            <div class="item-title">
                                {{ number_format((float) $kg, 1) }} kg · {{ $b->effectiveCategory()?->label ?? '—' }}
                            </div>
                            <div class="item-sub">
                                {{ $b->requested_at?->format('j M, H:i') }} · {{ Str::afterLast($b->code, '-') }}
                            </div>
                        </span>
                        <span class="item-end">
                            <div class="strong figure-num {{ $paid ? '' : 'dim' }}">{{ $paid ? '₦'.number_format($paid, 0) : '—' }}</div>
                            <span class="st {{ $b->status->cssClass() }}">{{ $b->status->label() }}</span>
                        </span>
                    </button>
                </li>
            @empty
                <li>
                    <div class="empty">
                        <span class="empty-icon"><i data-icon="package" class="i i-lg"></i></span>
                        <div class="strong">Nothing here</div>
                        <p class="small dim" style="max-width:280px">No batches match this filter.</p>
                    </div>
                </li>
            @endforelse
        </ul>

        @if ($batches->hasPages())
            <div class="card-foot">{{ $batches->links() }}</div>
        @endif
    </section>

    @if ($rejected > 0)
        <div class="callout callout-warn">
            <i data-icon="alert" class="i i-sm"></i>
            <div>
                <strong>{{ $rejected }} {{ Str::plural('batch', $rejected) }} rejected.</strong>
                Rejected batches earn nothing and repeated rejections pause an account.
                Keep construction debris, nappies and liquids out of organic bags.
            </div>
        </div>
    @endif

    {{-- Detail sheet: the full audit trail for one batch, straight off batch_events. --}}
    @if ($openBatch)
        <div class="scrim" wire:click.self="close">
            <div class="modal card" role="dialog" aria-modal="true">
                <div class="card-head">
                    <h3>{{ $openBatch->code }}</h3>
                    <button class="btn btn-icon btn-quiet" wire:click="close" aria-label="Close"><i data-icon="close"></i></button>
                </div>
                <div class="card-body">
                    <div class="between mb-16">
                        <span class="st {{ $openBatch->status->cssClass() }}">{{ $openBatch->status->label() }}</span>
                        <span class="strong figure-num">
                            @if ($openBatch->status === \App\Enums\BatchStatus::Rejected)
                                <span class="dim">No payout</span>
                            @else
                                ₦{{ number_format((float) $openBatch->household_share, 2) }}
                            @endif
                        </span>
                    </div>

                    <dl>
                        <div class="kv"><dt>Stream</dt><dd>{{ $openBatch->effectiveCategory()?->label ?? '—' }}</dd></div>
                        <div class="kv"><dt>Expected</dt><dd class="figure-num">{{ $openBatch->expected_kg ? number_format((float) $openBatch->expected_kg, 1).' kg' : '—' }}</dd></div>
                        <div class="kv"><dt>Declared by agent</dt><dd class="figure-num">{{ $openBatch->collected_kg ? number_format((float) $openBatch->collected_kg, 1).' kg' : '—' }}</dd></div>
                        <div class="kv"><dt>Verified at the gate</dt><dd class="figure-num strong">{{ $openBatch->verified_kg ? number_format((float) $openBatch->verified_kg, 1).' kg' : 'Not yet weighed' }}</dd></div>
                        @if ($openBatch->grade)
                            <div class="kv"><dt>Grade</dt><dd>{{ $openBatch->grade }}</dd></div>
                        @endif
                        @if ($openBatch->rate_applied)
                            <div class="kv"><dt>Rate applied</dt><dd class="figure-num">₦{{ number_format((float) $openBatch->rate_applied, 2) }}/kg</dd></div>
                            <div class="kv"><dt>Batch value</dt><dd class="figure-num">₦{{ number_format((float) $openBatch->value_naira, 2) }}</dd></div>
                        @endif
                        <div class="kv"><dt>Agent</dt><dd>{{ $openBatch->agent?->user?->name ?? 'Unassigned' }}</dd></div>
                    </dl>

                    @if ($openBatch->rejection_reason)
                        <div class="callout callout-err mt-14">
                            <i data-icon="alert" class="i i-sm"></i>
                            <div><strong>Rejected at inspection.</strong> {{ $openBatch->rejection_reason }}</div>
                        </div>
                    @endif

                    <div class="divider mt-16 mb-14"></div>
                    <div class="tiny dim mb-10">Audit trail</div>
                    <div class="timeline">
                        @foreach ($openBatch->events as $e)
                            @php($to = \App\Enums\BatchStatus::tryFrom($e->to_status))
                            <div class="tl-item done">
                                <div class="tl-dot"><i data-icon="{{ $to?->icon() ?? 'check' }}" class="i i-sm"></i></div>
                                <div>
                                    <div class="tl-title">{{ $to?->label() ?? $e->to_status }}</div>
                                    @if ($e->actor_role)<div class="tl-meta">by the {{ $e->actor_role }}</div>@endif
                                    <div class="tl-time">{{ $e->created_at->format('j M Y, H:i') }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <a class="btn btn-ghost btn-block mt-16" href="/b/{{ $openBatch->code }}" wire:navigate>
                        <i data-icon="scan" class="i i-sm"></i> Open the public trace
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
