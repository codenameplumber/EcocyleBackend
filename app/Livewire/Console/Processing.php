<?php

namespace App\Livewire\Console;

use App\Enums\BatchStatus;
use App\Models\ProcessingLine;
use App\Models\WasteBatch;
use App\Models\ProcessingRun;
use App\Services\BatchStateMachine;
use App\Services\InsightService;
use App\Services\ProcessingService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.console')]
class Processing extends Component
{
    use WithPagination;

    public int $window = 14;

    public bool $showStart = false;
    public ?int $runLineId = null;
    public ?int $runCategoryId = null;
    public float $runTargetKg = 0;

    public function openStart(): void
    {
        $this->showStart = true;
        $this->runLineId ??= ProcessingLine::where('status', '!=', 'offline')->value('id');
        $this->refreshTarget();
    }

    public function updatedRunCategoryId(): void
    {
        $this->refreshTarget();
    }

    /** How much verified feedstock of the chosen stream is actually waiting. */
    private function refreshTarget(): void
    {
        $this->runTargetKg = round((float) $this->queuedFor($this->runCategoryId)->sum('verified_kg'), 1);
    }

    private function queuedFor(?int $categoryId)
    {
        return WasteBatch::where('status', BatchStatus::Verified->value)
            ->when($categoryId, fn ($q) => $q->whereRaw(
                'COALESCE(confirmed_category_id, waste_category_id) = ?', [$categoryId]
            ));
    }

    /**
     * Feed everything queued for a stream onto a line, up to the target weight.
     *
     * Oldest first: verified material sitting in the yard degrades, and a run
     * that skipped the back of the queue would leave it there indefinitely.
     */
    public function startBatchRun(BatchStateMachine $machine, ProcessingService $processing): void
    {
        $target = max(0, (float) $this->runTargetKg);
        $fed = 0.0;
        $count = 0;
        $run = null;

        foreach ($this->queuedFor($this->runCategoryId)->orderBy('verified_at')->cursor() as $batch) {
            if ($fed + (float) $batch->verified_kg > $target && $count > 0) {
                break;
            }

            $machine->transition($batch, BatchStatus::Processing, auth()->user());
            $run = $processing->feed($batch) ?? $run;

            $fed += (float) $batch->verified_kg;
            $count++;
        }

        $this->showStart = false;

        $this->dispatch('toast',
            message: $count
                ? sprintf('%s started · %d batches · %s kg', $run?->code ?? 'Run', $count, number_format($fed, 1))
                : 'Nothing verified is waiting for that line',
            kind: $count ? 'ok' : 'warn');
    }

    /** Move a verified batch onto a line and attach it to that line's open run. */
    public function startRun(int $batchId, BatchStateMachine $machine, ProcessingService $processing): void
    {
        $batch = WasteBatch::with('confirmedCategory', 'category')->find($batchId);

        if (! $batch || $batch->status !== BatchStatus::Verified) {
            return;
        }

        $machine->transition($batch, BatchStatus::Processing, auth()->user());
        $run = $processing->feed($batch);

        $this->dispatch('toast',
            message: $run
                ? "Batch {$batch->code} fed to {$run->line?->name} on {$run->code}"
                : "Batch {$batch->code} entered processing",
            kind: 'ok');
    }

    public function complete(int $batchId, BatchStateMachine $machine, ProcessingService $processing): void
    {
        $batch = WasteBatch::find($batchId);

        if (! $batch || $batch->status !== BatchStatus::Processing) {
            return;
        }

        $machine->transition($batch, BatchStatus::Processed, auth()->user());

        // Close the run once nothing on it is still in processing.
        $run = $batch->processingRun;
        if ($run && ! $run->batches()->where('status', BatchStatus::Processing->value)->exists()) {
            $processing->close($run);
        }

        $this->dispatch('toast', message: "Batch {$batch->code} processed", kind: 'ok');
    }

    public function setWindow(int $days): void
    {
        $this->window = $days;
    }

    public function render(ProcessingService $processing, InsightService $insights)
    {
        $lines = ProcessingLine::with('category')->get();

        return view('livewire.console.processing', [
            'lines'     => $lines,
            'running_lines' => $lines->where('status', 'running')->count(),
            'output'    => $processing->output(1),
            // The same standing cards the analytics screen shows, filtered to the
            // two that speak to whether the lines can take what is coming.
            'capacity'  => $insights->tagged(['Capacity', 'Forecast']),
            'outputAll' => $processing->output($this->window),
            'throughput'=> $processing->throughput($this->window),
            'queuedByStream' => WasteBatch::where('status', BatchStatus::Verified->value)
                ->join('waste_categories', 'waste_categories.id', '=',
                    \Illuminate\Support\Facades\DB::raw('COALESCE(waste_batches.confirmed_category_id, waste_batches.waste_category_id)'))
                ->groupBy('waste_categories.id', 'waste_categories.label')
                ->selectRaw('waste_categories.id, waste_categories.label, SUM(verified_kg) as kg')
                ->orderByDesc('kg')
                ->get(),
            'runs'      => ProcessingRun::with('line.category')
                ->withCount('batches')
                ->latest('started_at')->limit(20)->get(),
            // Paginated, not capped: an operator working a hundred-batch backlog
            // needs to reach the hundredth one, but not all at once.
            'queue'     => WasteBatch::where('status', BatchStatus::Verified->value)
                ->with(['household.user', 'category'])->latest('verified_at')
                ->paginate(10, ['*'], 'queuePage'),
            'running'   => WasteBatch::where('status', BatchStatus::Processing->value)
                ->with(['household.user', 'category'])->latest('processing_at')
                ->paginate(10, ['*'], 'runPage'),
            'doneToday' => (float) WasteBatch::whereDate('processed_at', today())->sum('verified_kg'),
            'totalDone' => (float) WasteBatch::where('status', BatchStatus::Processed->value)->sum('verified_kg'),
        ])->layoutData(['page' => 'processing', 'heading' => 'Processing']);
    }
}
