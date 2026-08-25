<div class="col g-16" @if ($batch) wire:poll.15s @endif>
    @if ($batch)
        <section class="card">
            <div class="card-head">
                <div class="row g-8">
                    <span style="width:7px;height:7px;border-radius:50%;background:var(--primary)"></span>
                    <h3 class="mono">{{ $batch->shortCode() }}</h3>
                </div>
                <span class="st {{ $batch->status->cssClass() }}">{{ $batch->status->label() }}</span>
            </div>
            <div class="card-body">
                <div class="steps">
                    @foreach ([\App\Enums\BatchStatus::Requested, \App\Enums\BatchStatus::Accepted, \App\Enums\BatchStatus::Collected, \App\Enums\BatchStatus::Received, \App\Enums\BatchStatus::Verified] as $s)
                        @php
                            $done = $batch->status->step() > $s->step();
                            $now  = $batch->status->step() === $s->step();
                        @endphp
                        <div class="step {{ $done ? 'done' : '' }} {{ $now ? 'active' : '' }}">
                            <span class="step-dot"><i data-icon="{{ $s->icon() }}" class="i i-sm"></i></span>
                            <span class="step-label">{{ $s->label() }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        @if ($batch->agent)
            <section class="card card-flat">
                <div class="card-head"><h3>Your agent</h3><span class="badge badge-green">KYC verified</span></div>
                <div class="card-body">
                    <div class="row g-12">
                        <span class="avatar avatar-lg {{ $batch->agent->user->avatarVariant() }}">
                            {{ $batch->agent->user->initials() }}
                        </span>
                        <span class="grow">
                            <div class="item-title">{{ $batch->agent->name() }}</div>
                            <div class="item-sub">
                                {{ $batch->agent->vehicle_type }} · {{ $batch->agent->capacity_kg }} kg
                                · {{ $batch->agent->rating }} ★
                            </div>
                        </span>
                    </div>
                    @if ($batch->agent->user->phone)
                        <a class="btn btn-ghost btn-block mt-14"
                           href="tel:{{ preg_replace('/\s/', '', $batch->agent->user->phone) }}">
                            <i data-icon="phone" class="i i-sm"></i>
                            Call {{ explode(' ', $batch->agent->name())[0] }}
                        </a>
                    @endif
                </div>
            </section>
        @endif

        <section class="card card-flat">
            <div class="card-head"><h3>The three weights</h3></div>
            <div class="card-body">
                <dl>
                    <div class="kv"><dt>You estimated</dt>
                        <dd class="figure-num">{{ $batch->expected_kg ? number_format($batch->expected_kg, 1) . ' kg' : '—' }}</dd>
                    </div>
                    <div class="kv"><dt>Agent declared</dt>
                        <dd class="figure-num">{{ $batch->collected_kg ? number_format($batch->collected_kg, 1) . ' kg' : 'pending' }}</dd>
                    </div>
                    <div class="kv"><dt><span class="strong">Facility verified</span></dt>
                        <dd class="strong figure-num {{ $batch->verified_kg ? 't-pos' : '' }}">
                            {{ $batch->verified_kg ? number_format($batch->verified_kg, 1) . ' kg' : 'pending' }}
                        </dd>
                    </div>
                    <div class="kv"><dt>Your payout</dt>
                        <dd class="strong figure-num">
                            {{ $batch->household_share ? '₦' . number_format((float) $batch->household_share, 2) : '—' }}
                        </dd>
                    </div>
                </dl>
                <div class="callout callout-info mt-14">
                    <i data-icon="info" class="i i-sm"></i>
                    <div>Payment is released once the facility weighs this batch. The verified figure is what you are paid on.</div>
                </div>
            </div>
        </section>

        <section class="card card-flat">
            <div class="card-head"><h3>Trail</h3><span class="tiny dim">{{ count($batch->timeline()) }} events</span></div>
            <div class="card-body">
                <div class="timeline">
                    @foreach ($batch->timeline() as $t)
                        <div class="tl-item {{ $loop->last ? 'current' : 'done' }}">
                            <span class="tl-dot"><i data-icon="{{ $t['status']->icon() }}" class="i i-sm"></i></span>
                            <div>
                                <div class="tl-title">{{ $t['status']->label() }}</div>
                                <div class="tl-time">{{ $t['at']->format('j M, g:i A') }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @else
        <div class="empty">
            <span class="empty-icon"><i data-icon="pin" class="i i-lg"></i></span>
            <div class="strong">Nothing in progress</div>
            <p class="small muted">Request a pickup and you can follow it here.</p>
            <a class="btn btn-primary mt-8" href="{{ route('app.request') }}" wire:navigate>Request a pickup</a>
        </div>
    @endif
</div>
