<?php

namespace App\Livewire\Console;

use App\Enums\BatchStatus;
use App\Models\WasteBatch;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.console')]
class Pipeline extends Component
{
    public function render()
    {
        $columns = collect(BatchStatus::pipeline())->map(function (BatchStatus $s) {
            $batches = WasteBatch::where('status', $s->value)
                ->with(['household.user', 'agent.user', 'category'])
                ->latest('updated_at')
                ->limit(12)
                ->get();

            return [
                'status'  => $s,
                'count'   => WasteBatch::where('status', $s->value)->count(),
                'kg'      => (float) WasteBatch::where('status', $s->value)
                                ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(verified_kg, collected_kg, expected_kg, 0)')),
                'batches' => $batches,
            ];
        });

        return view('livewire.console.pipeline', [
            'columns' => $columns,
        ])->layoutData(['page' => 'pipeline', 'heading' => 'Waste pipeline']);
    }
}
