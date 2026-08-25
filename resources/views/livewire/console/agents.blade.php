<div>
    <div class="page-head">
        <div><h1>Collection agents</h1>
            <p class="muted">The movement layer, and the trust attached to it.</p></div>
    </div>

    <section class="grid grid-4 mb-16">
        <div class="card stat tone-green">
            <div class="stat-top"><span class="stat-label">Registered</span>
                <span class="stat-icon"><i data-icon="truck" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ $agents->count() }}</div>
            <div class="stat-foot">on the network</div>
        </div>
        <div class="card stat tone-cyan">
            <div class="stat-top"><span class="stat-label">Online now</span>
                <span class="stat-icon"><i data-icon="pulse" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ $online }}</div>
            <div class="stat-foot">accepting jobs</div>
        </div>
        <div class="card stat tone-amber">
            <div class="stat-top"><span class="stat-label">Avg integrity</span>
                <span class="stat-icon"><i data-icon="shield" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ $avgScore }}</div>
            <div class="stat-foot">out of 100</div>
        </div>
        <div class="card stat tone-red">
            <div class="stat-top"><span class="stat-label">Flagged</span>
                <span class="stat-icon"><i data-icon="alert" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ $flagged }}</div>
            <div class="stat-foot">with an open case</div>
        </div>
    </section>

    <section class="grid mb-16" style="grid-template-columns:1fr 2fr;gap:16px;align-items:start">
        <div class="card">
            <div class="card-head"><h3>Fleet mix</h3><span class="badge">by vehicle</span></div>
            <div class="card-body col g-12">
                @foreach ($fleet as $type => $count)
                    <div class="between">
                        <span class="row g-8 small"><i data-icon="truck" class="i i-sm"></i>{{ $type }}</span>
                        <span class="small strong figure-num">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
            <div class="media media-16x9 media-flat media-scrim">
                <img src="{{ asset('assets/img/vehicle-truck.jpg') }}" alt="Largest vehicle class" loading="lazy">
                <div class="media-cap"><div class="tiny">Largest class on the network</div></div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3>All agents</h3>
                <div class="input-group" style="width:240px">
                    <i data-icon="search" class="i i-sm"></i>
                    <input class="input" wire:model.live.debounce.400ms="q" placeholder="Name or ID…">
                </div>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th>Agent</th><th>Vehicle</th><th>Area</th>
                        <th class="num">Batches</th><th class="num">Accuracy</th>
                        <th class="num">Integrity</th><th>Status</th>
                    </tr></thead>
                    <tbody>
                    @forelse ($agents as $a)
                        <tr>
                            <td><div class="row g-8">
                                <span class="avatar avatar-sm presence {{ $a->is_online ? 'on' : '' }} {{ $a->user->avatarVariant() }}">
                                    {{ $a->user->initials() }}
                                </span>
                                <span><div class="small strong">{{ $a->name() }}</div>
                                    <div class="tiny dim mono">{{ $a->code }}</div></span>
                            </div></td>
                            <td class="small">{{ $a->vehicle_type }} · {{ $a->capacity_kg }} kg</td>
                            <td class="small">{{ $a->operating_area ?? '—' }}</td>
                            <td class="num figure-num">{{ $a->batches_count }}</td>
                            <td class="num figure-num {{ $a->weightAccuracy() < 95 ? 't-warn' : '' }}">
                                {{ $a->weightAccuracy() }}%
                            </td>
                            <td class="num figure-num">{{ $a->integrity_score }}</td>
                            <td>
                                <span class="badge {{ $a->is_online ? 'badge-green' : '' }}">
                                    <span class="dot"></span>{{ $a->is_online ? 'Online' : 'Offline' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty">
                            <span class="empty-icon"><i data-icon="truck" class="i i-lg"></i></span>
                            <div class="strong">No agents match</div></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
