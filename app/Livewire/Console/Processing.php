<?php

namespace App\Livewire\Console;

use App\Enums\BatchStatus;
use App\Models\ProcessingLine;
use App\Models\WasteBatch;
use App\Services\BatchStateMachine;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.console')]
class Processing extends Component
{
    /** Move a verified batch onto a line. */
    public function startRun(int $batchId, BatchStateMachine $machine): void
    {
        $batch = WasteBatch::find($batchId);

        if ($batch && $batch->status === BatchStatus::Verified) {
            $machine->transition($batch, BatchStatus::Processing, auth()->user());
            $this->dispatch('toast', message: "Batch {$batch->code} entered processing", kind: 'ok');
        }
    }

    public function complete(int $batchId, BatchStateMachine $machine): void
    {
        $batch = WasteBatch::find($batchId);

        if ($batch && $batch->status === BatchStatus::Processing) {
            $machine->transition($batch, BatchStatus::Processed, auth()->user());
            $this->dispatch('toast', message: "Batch {$batch->code} processed", kind: 'ok');
        }
    }

    public function render()
    {
        return view('livewire.console.processing', [
            'lines'     => ProcessingLine::with('category')->get(),
            'queue'     => WasteBatch::where('status', BatchStatus::Verified->value)
                ->with(['household.user', 'category'])->latest('verified_at')->get(),
            'running'   => WasteBatch::where('status', BatchStatus::Processing->value)
                ->with(['household.user', 'category'])->latest('processing_at')->get(),
            'doneToday' => (float) WasteBatch::whereDate('processed_at', today())->sum('verified_kg'),
            'totalDone' => (float) WasteBatch::where('status', BatchStatus::Processed->value)->sum('verified_kg'),
        ])->layoutData(['page' => 'processing', 'heading' => 'Processing']);
    }
}
