<div>
    <div class="row g-8">
        @foreach ($chips as $key => $label)
            <button class="chip" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                    wire:click="setFilter('{{ $key }}')">{{ $label }}</button>
        @endforeach
        <div class="grow"></div>
        <button class="btn btn-sm btn-quiet" wire:click="markAllRead" @disabled(! $unread)>
            Mark all read @if ($unread)<span class="badge badge-sm">{{ $unread }}</span>@endif
        </button>
    </div>

    <section class="card">
        <ul class="list">
            @forelse ($notes as $n)
                @php
                    $d = $n->data;
                    $unreadRow = $n->read_at === null;
                @endphp
                <li>
                    <button class="item" wire:click="open('{{ $n->id }}')" wire:key="n-{{ $n->id }}"
                            @if ($unreadRow) style="background:var(--primary-soft)" @endif>
                        <span class="qa-ic {{ $this->toneFor($d['kind'] ?? null) }}" style="width:34px;height:34px">
                            <i data-icon="{{ $d['icon'] ?? 'bell' }}" class="i i-sm"></i>
                        </span>
                        <span class="grow" style="min-width:0">
                            <div class="item-title truncate">{{ $d['title'] ?? 'Update' }}</div>
                            <div class="item-sub truncate">{{ $d['body'] ?? ($d['batch'] ?? '') }}</div>
                        </span>
                        <span class="item-end">
                            <div class="tiny dim">{{ $n->created_at->diffForHumans(short: true) }}</div>
                            @if ($unreadRow)
                                <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--primary);margin-top:5px"></span>
                            @endif
                        </span>
                    </button>
                </li>
            @empty
                <li>
                    <div class="empty">
                        <span class="empty-icon"><i data-icon="bell" class="i i-lg"></i></span>
                        <div class="strong">Nothing here yet</div>
                        <p class="small dim" style="max-width:280px">
                            You will be notified when a job is accepted, a batch is weighed at the gate
                            and when money moves.
                        </p>
                    </div>
                </li>
            @endforelse
        </ul>

        @if ($notes->hasPages())
            <div class="card-foot">{{ $notes->links() }}</div>
        @endif
    </section>
</div>
