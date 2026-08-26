<?php

namespace App\Livewire\Console;

use App\Enums\BatchStatus;
use App\Models\WasteBatch;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.console')]
class Pipeline extends Component
{
    public ?int $openId = null;

    public function show(int $id): void
    {
        $this->openId = $id;
    }

    public function close(): void
    {
        $this->openId = null;
    }

    /** Everything the sheet renders, eager-loaded so it is one round trip. */
    public function getOpenBatchProperty(): ?WasteBatch
    {
        return $this->openId
            ? WasteBatch::with([
                'household.user', 'household.area', 'agent.user', 'category',
                'confirmedCategory', 'delivery', 'processingRun.line', 'events',
            ])->find($this->openId)
            : null;
    }

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
