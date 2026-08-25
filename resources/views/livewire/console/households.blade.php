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

    <section class="card mb-16">
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
                    <tr>
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
</div>
