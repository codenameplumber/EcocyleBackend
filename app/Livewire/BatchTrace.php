<?php

namespace App\Livewire;

use App\Models\WasteBatch;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The public face of a batch code.
 *
 * Deliberately readable without signing in: the whole promise is that the
 * record can be checked by anybody holding the bag.
 */
#[Layout('layouts.trace')]
class BatchTrace extends Component
{
    public WasteBatch $batch;

    public function mount(string $code): void
    {
        $this->batch = WasteBatch::where('code', $code)
            ->with(['household.user', 'household.area', 'agent.user', 'category', 'confirmedCategory'])
            ->firstOrFail();
    }

    public function render()
    {
        return view('livewire.batch-trace');
    }
}
