@props(['batch'])
{{--
  One batch, in full. Used by both the registry and the pipeline board, because
  an operator clicking a row on either screen is asking the same question — and
  two copies of this would have drifted apart within a week.

  Everything here is read-only. Acting on a batch happens on the inspection bay
  or the processing line, where the state machine can guard the transition.
--}}
<div class="card-head">
    <div>
        <h3 class="mono">{{ $batch->code }}</h3>
        <div class="tiny dim mt-4">
            {{ $batch->household?->name() }}
            @if ($batch->household?->area) · {{ $batch->household->area->name }} @endif
        </div>
    </div>
    <button class="btn btn-icon btn-quiet" wire:click="close" aria-label="Close"><i data-icon="close"></i></button>
</div>

<div class="card-body">
    <div class="between mb-16">
        <span class="st {{ $batch->status->cssClass() }}">{{ $batch->status->label() }}</span>
        <span class="strong figure-num">
            @if ($batch->value_naira)
                ₦{{ number_format((float) $batch->value_naira, 2) }}
            @else
                <span class="dim">not yet valued</span>
            @endif
        </span>
    </div>

    {{-- The three weights, side by side. Seeing them apart is the point. --}}
    <div class="grid grid-3 mb-16" style="gap:10px">
        <div>
            <div class="tiny dim">Expected</div>
            <div class="strong figure-num">{{ $batch->expected_kg ? number_format((float) $batch->expected_kg, 1).' kg' : '—' }}</div>
            <div class="tiny dim">household</div>
        </div>
        <div>
            <div class="tiny dim">Declared</div>
            <div class="strong figure-num">{{ $batch->collected_kg ? number_format((float) $batch->collected_kg, 1).' kg' : '—' }}</div>
            <div class="tiny dim">agent</div>
        </div>
        <div>
            <div class="tiny dim">Verified</div>
            <div class="strong figure-num" style="color:var(--primary)">
                {{ $batch->verified_kg ? number_format((float) $batch->verified_kg, 1).' kg' : '—' }}
            </div>
            <div class="tiny dim">the scale</div>
        </div>
    </div>

    @if ($batch->variance_pct !== null)
        @php($over = abs((float) $batch->variance_pct) > (float) config('ecocycle.variance_tolerance'))
        <div class="callout {{ $over ? 'callout-warn' : '' }} mb-14">
            <i data-icon="{{ $over ? 'alert' : 'ok' }}" class="i i-sm"></i>
            <div>
                Declared and verified differ by
                <strong>{{ number_format((float) $batch->variance_pct, 1) }}%</strong>.
                {{ $over
                    ? 'That is past the '.config('ecocycle.variance_tolerance').'% tolerance, so a case was raised against the agent. Settlement is unaffected.'
                    : 'Inside tolerance. No case raised.' }}
            </div>
        </div>
    @endif

    <dl class="mb-16">
        <div class="kv"><dt>Stream</dt><dd>{{ $batch->effectiveCategory()?->label ?? '—' }}</dd></div>
        @if ($batch->confirmed_category_id && $batch->confirmed_category_id !== $batch->waste_category_id)
            <div class="kv">
                <dt>Declared as</dt>
                <dd class="dim">{{ $batch->category?->label }} — regraded at inspection</dd>
            </div>
        @endif
        @if ($batch->grade)<div class="kv"><dt>Grade</dt><dd>{{ $batch->grade }}</dd></div>@endif
        @if ($batch->rate_applied)
            <div class="kv"><dt>Rate applied</dt><dd class="figure-num">₦{{ number_format((float) $batch->rate_applied, 2) }}/kg</dd></div>
            <div class="kv"><dt>Household share (60%)</dt><dd class="figure-num">₦{{ number_format((float) $batch->household_share, 2) }}</dd></div>
            <div class="kv"><dt>Agent share (40%)</dt><dd class="figure-num">₦{{ number_format((float) $batch->agent_share, 2) }}</dd></div>
        @endif
        <div class="kv"><dt>Agent</dt><dd>{{ $batch->agent?->name() ?? 'Unassigned' }}</dd></div>
        @if ($batch->delivery)
            <div class="kv">
                <dt>Delivery</dt>
                <dd class="mono">{{ $batch->delivery->code }} · {{ $batch->delivery->weighbridge }}</dd>
            </div>
        @endif
        @if ($batch->processingRun)
            <div class="kv">
                <dt>Processing run</dt>
                <dd class="mono">{{ $batch->processingRun->code }} · {{ $batch->processingRun->line?->name }}</dd>
            </div>
        @endif
        <div class="kv">
            <dt>Pickup point</dt>
            <dd class="mono">
                {{ $batch->pickup_lat ? number_format((float) $batch->pickup_lat, 5) : '—' }},
                {{ $batch->pickup_lng ? number_format((float) $batch->pickup_lng, 5) : '—' }}
            </dd>
        </div>
    </dl>

    @if ($batch->rejection_reason)
        <div class="callout callout-err mb-14">
            <i data-icon="alert" class="i i-sm"></i>
            <div><strong>Rejected at inspection.</strong> {{ $batch->rejection_reason }}</div>
        </div>
    @endif

    <div class="divider mt-16 mb-14"></div>
    <div class="tiny dim mb-10">Audit trail</div>
    <div class="timeline">
        @foreach ($batch->events as $e)
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
</div>

<div class="card-body" style="padding-top:0">
    <div class="qr-tag">
        {!! app(\App\Services\QrService::class)->svg(url('/b/' . $batch->code), 104) !!}
        <div>
            <div class="tiny dim">Batch tag</div>
            <div class="mono strong small">{{ $batch->code }}</div>
            <p class="tiny dim mt-8">
                The same code on the bag. Scan it from this screen to open the trace on a phone.
            </p>
        </div>
    </div>
</div>

<div class="card-foot row" style="justify-content:space-between">
    <span class="tiny dim">Anyone with the tag can read this record</span>
    <a class="btn btn-ghost" href="/b/{{ $batch->code }}" wire:navigate>
        <i data-icon="scan" class="i i-sm"></i> Public trace
    </a>
</div>
