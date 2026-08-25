<?php

namespace App\Livewire\Household;

use Livewire\Attributes\Layout;
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

        return view('livewire.household.track', [
            'batch' => $batch,
        ])->layoutData([
            'app' => 'household', 'page' => 'track',
            'heading' => 'Track collection',
            'sub' => $batch?->code ?? 'Nothing in progress',
            'back' => route('app'),
        ]);
    }
}
