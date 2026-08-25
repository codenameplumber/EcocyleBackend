<div class="col g-16">
    <div class="callout callout-info">
        <i data-icon="scale" class="i i-sm"></i>
        <div>
            <strong>{{ number_format($remaining, 1) }} kg of capacity left.</strong>
            Jobs above that are dimmed — deliver your current load first.
        </div>
    </div>

    <div class="col g-10">
        @forelse ($jobs as $j)
            @include('livewire.agent.partials.job-card', ['j' => $j, 'remaining' => $remaining])
        @empty
            <div class="empty">
                <span class="empty-icon"><i data-icon="boxes" class="i i-lg"></i></span>
                <div class="strong">No open jobs</div>
                <p class="small muted">Nothing on the marketplace right now. Check back shortly.</p>
            </div>
        @endforelse
    </div>

    <p class="tiny dim center">
        Jobs are ranked by distance, your remaining capacity, the value of the stream and how soon the
        collection window closes — not simply by proximity.
    </p>
</div>
