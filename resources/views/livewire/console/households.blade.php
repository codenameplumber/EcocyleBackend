<div>
    <div class="page-head">
        <div><h1>Households</h1>
            <p class="muted">Who generates waste, how much of it, and how reliably.</p></div>
    </div>

    <section class="grid grid-4 mb-16">
        <div class="card stat tone-green">
            <div class="stat-top"><span class="stat-label">Registered</span>
                <span class="stat-icon"><i data-icon="house" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ number_format($total) }}</div>
            <div class="stat-foot">on the network</div>
        </div>
        <div class="card stat tone-cyan">
            <div class="stat-top"><span class="stat-label">Active</span>
                <span class="stat-icon"><i data-icon="pulse" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ number_format($active) }}</div>
            <div class="stat-foot">{{ $total ? round($active / $total * 100) : 0 }}% of the base</div>
        </div>
        <div class="card stat tone-amber">
            <div class="stat-top"><span class="stat-label">Avg per pickup</span>
                <span class="stat-icon"><i data-icon="scale" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ number_format($avgKg, 1) }}<small>kg</small></div>
            <div class="stat-foot">verified, network-wide</div>
        </div>
        <div class="card stat tone-violet">
            <div class="stat-top"><span class="stat-label">Areas served</span>
                <span class="stat-icon"><i data-icon="pin" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ $areas->count() }}</div>
            <div class="stat-foot">within one collection radius</div>
        </div>
    </section>

    <section class="grid mb-16" style="grid-template-columns:1.4fr 1fr;gap:16px;align-items:start">
        <div class="card">
            <div class="card-head">
                <h3>Generation density</h3>
                <span class="badge">{{ number_format($total) }} registered</span>
            </div>
            <x-map :pins="$pins" :height="300" :legend="false" />
            <div class="media media-21x9 media-flat media-scrim">
                <img src="{{ asset('assets/img/city-aerial.jpg') }}" alt="Residential streets in Lagos"
                     loading="lazy" width="1400" height="800">
                <div class="media-cap">
                    <div class="tiny">Lagos cluster · {{ $areas->count() }} areas served</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>By area</h3><span class="badge">verified kg</span></div>
        <div class="card-body col g-12">
            @foreach ($areas as $a)
                <div>
                    <div class="between mb-4">
                        <span class="small strong">{{ $a->name }}</span>
                        <span class="row g-10">
                            <span class="tiny dim">{{ $a->households_count }} households</span>
                            <span class="small figure-num">{{ number_format($a->kg, 1) }} kg</span>
                        </span>
                    </div>
                    <div class="bar"><span style="width:{{ $a->kg / $maxKg * 100 }}%"></span></div>
                </div>
            @endforeach
        </div>
    </div>
    </section>

    <section class="card">
        <div class="card-head">
            <h3>All households</h3>
            <div class="row g-10">
                <div class="input-group" style="width:240px">
                    <i data-icon="search" class="i i-sm"></i>
                    <input class="input" wire:model.live.debounce.400ms="q" placeholder="Name, ID or area…">
                </div>
                <select class="select" wire:model.live="status" style="width:auto">
                    <option value="">All</option><option value="active">Active</option><option value="paused">Paused</option>
                </select>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th>Household</th><th>Area</th><th class="num">Size</th><th>Frequency</th>
                    <th>Usual stream</th><th class="num">Batches</th><th class="num">Predicted next</th><th>Status</th>
                </tr></thead>
                <tbody>
                @forelse ($rows as $h)
                    <tr wire:key="hh-{{ $h->id }}" wire:click="show({{ $h->id }})" style="cursor:pointer">
                        <td><div class="row g-8">
                            <span class="avatar avatar-sm {{ $h->user->avatarVariant() }}">{{ $h->user->initials() }}</span>
                            <span><div class="small strong">{{ $h->name() }}</div>
                                <div class="tiny dim mono">{{ $h->code }}</div></span>
                        </div></td>
                        <td class="small">{{ $h->area?->name }}</td>
                        <td class="num">{{ $h->size }}</td>
                        <td class="small">{{ $h->frequency }}</td>
                        <td class="small">{{ $h->defaultCategory?->label ?? '—' }}</td>
                        <td class="num figure-num">{{ $h->batches_count }}</td>
                        <td class="num"><span class="badge badge-violet">{{ number_format($h->predictedKg(), 1) }} kg</span></td>
                        <td>
                            <span class="badge {{ $h->status === 'active' ? 'badge-green' : 'badge-amber' }}">
                                <span class="dot"></span>{{ ucfirst($h->status) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="empty">
                        <span class="empty-icon"><i data-icon="search" class="i i-lg"></i></span>
                        <div class="strong">No households match</div></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-foot">{{ $rows->links() }}</div>
    </section>
    {{-- Household record --}}
    @if ($open)
        <div class="scrim" wire:click.self="close">
            <div class="modal" role="dialog" aria-modal="true">
                <div class="card-head">
                    <div class="row g-10">
                        <span class="avatar {{ $open->user?->avatarVariant() }}">{{ $open->user?->initials() }}</span>
                        <div>
                            <h3>{{ $open->name() }}</h3>
                            <div class="tiny dim mono">{{ $open->code }} · {{ $open->area?->name }}</div>
                        </div>
                    </div>
                    <button class="btn btn-icon btn-quiet" wire:click="close" aria-label="Close">
                        <i data-icon="close"></i>
                    </button>
                </div>

                <div class="card-body">
                    <div class="grid grid-3 mb-16" style="gap:10px">
                        <div>
                            <div class="tiny dim">Avg per pickup</div>
                            <div class="strong figure-num">{{ number_format($open->averageKg(), 1) }} kg</div>
                        </div>
                        <div>
                            <div class="tiny dim">Predicted next</div>
                            <div class="strong figure-num" style="color:var(--violet)">
                                {{ number_format($open->predictedKg(), 1) }} kg
                            </div>
                        </div>
                        <div>
                            <div class="tiny dim">Rating</div>
                            <div class="strong figure-num">{{ number_format($open->rating, 1) }} ★</div>
                        </div>
                    </div>

                    @if (count($openTrend) > 1)
                        <svg class="chart" data-chart="bars" data-height="150"
                             data-values="{{ implode(',', $openTrend) }}"
                             data-labels="{{ implode(',', range(1, count($openTrend))) }}"
                             data-color="var(--primary)"></svg>
                    @endif

                    <dl class="mt-16 mb-16">
                        <div class="kv"><dt>Phone</dt><dd>{{ $open->user?->phone ?? '—' }}</dd></div>
                        <div class="kv"><dt>Household size</dt><dd>{{ $open->size }} people</dd></div>
                        <div class="kv"><dt>Frequency</dt><dd>{{ $open->frequency }}</dd></div>
                        <div class="kv"><dt>Usual stream</dt><dd>{{ $open->defaultCategory?->label ?? '—' }}</dd></div>
                        <div class="kv">
                            <dt>Coordinates</dt>
                            <dd class="mono">
                                {{ $open->lat ? number_format($open->lat, 5) : '—' }},
                                {{ $open->lng ? number_format($open->lng, 5) : '—' }}
                            </dd>
                        </div>
                        <div class="kv"><dt>Registered</dt><dd>{{ $open->created_at?->diffForHumans() }}</dd></div>
                        <div class="kv">
                            <dt>Wallet balance</dt>
                            <dd class="strong figure-num">₦{{ number_format($open->balance(), 2) }}</dd>
                        </div>
                        <div class="kv">
                            <dt>Status</dt>
                            <dd><span class="badge {{ $open->status === 'active' ? 'badge-green' : 'badge-amber' }}">{{ ucfirst($open->status) }}</span></dd>
                        </div>
                    </dl>

                    <h4 class="mb-10">Recent batches</h4>
                    @if ($openBatches->isNotEmpty())
                        <ul class="list" style="border:1px solid var(--border);border-radius:var(--r-md)">
                            @foreach ($openBatches as $b)
                                <li wire:key="ob-{{ $b->id }}">
                                    <a class="item" href="/b/{{ $b->code }}" wire:navigate>
                                        <span class="grow" style="min-width:0">
                                            <div class="small strong mono">{{ $b->code }}</div>
                                            <div class="tiny dim">
                                                {{ $b->requested_at?->diffForHumans(short: true) }} ·
                                                {{ $b->effectiveCategory()?->label }}
                                            </div>
                                        </span>
                                        <span class="item-end">
                                            <div class="small strong figure-num">
                                                {{ number_format((float) ($b->verified_kg ?? $b->expected_kg ?? 0), 1) }} kg
                                            </div>
                                            <span class="st {{ $b->status->cssClass() }}">{{ $b->status->label() }}</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="small muted">Nothing collected from this household yet.</p>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
