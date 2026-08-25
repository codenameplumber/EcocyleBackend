<div>
    <span class="eyebrow">Batch record</span>
    <h1 class="display mt-10" style="font-size:var(--t-2xl)">{{ $batch->code }}</h1>
    <p class="lede mt-10">
        {{ $batch->household->name() }} · {{ $batch->household->area?->name }}
        @if ($batch->agent) · collected by {{ $batch->agent->name() }} @endif
    </p>

    <div class="row g-8 mt-14">
        <span class="st {{ $batch->status->cssClass() }}">{{ $batch->status->label() }}</span>
        @if ($batch->grade)<span class="badge">Grade {{ $batch->grade }}</span>@endif
        <span class="badge">{{ $batch->effectiveCategory()?->label }}</span>
    </div>

    {{-- The three weights, which is the whole point of the record --}}
    <div class="grid grid-3 mt-32" style="gap:14px">
        <div class="card card-flat card-pad">
            <div class="tiny dim">Household expected</div>
            <div class="stat-value figure-num" style="font-size:22px">
                {{ $batch->expected_kg ? number_format($batch->expected_kg, 1) . ' kg' : '—' }}
            </div>
            <div class="tiny dim mt-4">predicted, plans only</div>
        </div>
        <div class="card card-flat card-pad">
            <div class="tiny dim">Agent declared</div>
            <div class="stat-value figure-num" style="font-size:22px">
                {{ $batch->collected_kg ? number_format($batch->collected_kg, 1) . ' kg' : '—' }}
            </div>
            <div class="tiny dim mt-4">recorded at pickup</div>
        </div>
        <div class="card card-flat card-pad" style="border-color:var(--primary-line);background:var(--primary-soft)">
            <div class="tiny" style="color:var(--primary)">Facility verified</div>
            <div class="stat-value figure-num" style="font-size:22px;color:var(--primary)">
                {{ $batch->verified_kg ? number_format($batch->verified_kg, 1) . ' kg' : 'pending' }}
            </div>
            <div class="tiny mt-4" style="color:var(--primary)">settles the money</div>
        </div>
    </div>

    @if ($batch->variance_pct !== null)
        <div class="callout {{ $batch->varianceIsOutOfTolerance() ? 'callout-warn' : 'callout-ok' }} mt-16">
            <i data-icon="{{ $batch->varianceIsOutOfTolerance() ? 'alert' : 'ok' }}" class="i i-sm"></i>
            <div>
                <strong>{{ $batch->variance_pct > 0 ? '+' : '' }}{{ number_format($batch->variance_pct, 1) }}%
                between the declaration and the scale.</strong>
                {{ $batch->varianceIsOutOfTolerance()
                    ? 'Outside the ' . config('ecocycle.variance_tolerance') . '% tolerance, so it was flagged for review.'
                    : 'Inside tolerance.' }}
            </div>
        </div>
    @endif

    @if ($batch->value_naira)
        <div class="grid grid-keep-2 mt-16" style="gap:12px">
            <div style="padding:14px 16px;background:var(--primary-soft);border-radius:var(--r-sm)">
                <div class="tiny" style="color:var(--primary)">Household · 60%</div>
                <div class="figure-num" style="font-size:24px;color:var(--primary);margin-top:2px">
                    ₦{{ number_format((float) $batch->household_share, 2) }}
                </div>
            </div>
            <div style="padding:14px 16px;background:var(--info-soft);border-radius:var(--r-sm)">
                <div class="tiny" style="color:var(--info)">Agent · 40%</div>
                <div class="figure-num" style="font-size:24px;color:var(--info);margin-top:2px">
                    ₦{{ number_format((float) $batch->agent_share, 2) }}
                </div>
            </div>
        </div>
        <p class="tiny dim mt-10">
            {{ number_format($batch->verified_kg, 1) }} kg × ₦{{ number_format((float) $batch->rate_applied, 0) }}/kg
            = ₦{{ number_format((float) $batch->value_naira, 2) }}
        </p>
    @endif

    @if ($batch->rejection_reason)
        <div class="callout callout-err mt-16">
            <i data-icon="ban" class="i i-sm"></i>
            <div><strong>Rejected.</strong> {{ $batch->rejection_reason }}</div>
        </div>
    @endif

    {{-- The audit trail --}}
    <div class="block-head mt-32"><h3>Trail</h3><span class="tiny dim">every transition, timestamped</span></div>
    <div class="timeline">
        @foreach ($batch->timeline() as $t)
            <div class="tl-item {{ $loop->last ? 'current' : 'done' }}">
                <span class="tl-dot"><i data-icon="{{ $t['status']->icon() }}" class="i i-sm"></i></span>
                <div>
                    <div class="tl-title">{{ $t['status']->label() }}</div>
                    <div class="tl-time">{{ $t['at']->format('j M Y, g:i A') }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="foot-note mt-32">
        This page is the batch record itself, not a report drawn over it. Anyone holding the tagged bag can
        scan the code and see exactly what was weighed, what it was graded, and what both parties were paid.
    </div>
</div>
