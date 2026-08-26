<?php

namespace App\Livewire\Household;

use Livewire\Attributes\Layout;
use App\Models\Facility;
use App\Support\Geo;
use Livewire\Component;

#[Layout('layouts.phone')]
class Track extends Component
{
    public function render()
    {
        $household = auth()->user()->household;

        $batch = $household->batches()
            ->open()
            ->with(['agent.user', 'category', 'events.actor'])
            ->latest('requested_at')
            ->first();

        // You, the agent carrying your batch, and where it is going.
        $facility = Facility::first();
        $pins = [];

        if ($batch?->household?->lat) {
            $pins[] = Geo::pin((float) $batch->household->lat, (float) $batch->household->lng,
                'house', label: 'You');
        }

        if ($batch?->agent?->last_lat) {
            $pins[] = Geo::pin((float) $batch->agent->last_lat, (float) $batch->agent->last_lng,
                'agent', live: true, label: explode(' ', $batch->agent->name())[0]);
        }

        $pins[] = Geo::pin(
                (float) ($facility?->lat ?? config('ecocycle.facility.lat')),
                (float) ($facility?->lng ?? config('ecocycle.facility.lng')),
                'plant', label: 'Facility'
            );

        return view('livewire.household.track', [
            'batch' => $batch,
            'pins'  => count($pins) > 1 ? $pins : [],
            // Progress as a percentage of the ten-step lifecycle. Computed here
            // because Blade's inline @php cannot parse the nested calls.
            'pct'   => $batch ? min(100, max(0, $batch->status->step() / 10 * 100)) : 0,
            'qr'    => $batch ? app(\App\Services\QrService::class)->svg(url('/b/' . $batch->code)) : null,
        ])->layoutData([
            'app' => 'household', 'page' => 'track',
            'heading' => 'Track collection',
            'sub' => $batch?->code ?? 'Nothing in progress',
            'back' => route('app'),
        ]);
    }
}
