<?php

namespace App\Livewire\Console;

use App\Enums\BatchStatus;
use App\Models\WasteBatch;
use App\Models\WasteCategory;
use App\Notifications\BatchRejected;
use App\Notifications\BatchVerified;
use App\Services\BatchStateMachine;
use App\Services\OpenAiService;
use App\Services\IntegrityService;
use App\Services\SettlementService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The screen where money is decided.
 *
 * The settlement preview recomputes server-side as the inspector types, from
 * the server's own rate table, so the figure that settles can never come from
 * anything the browser sent.
 */
#[Layout('layouts.console')]
class Inspection extends Component
{
    public ?int $batchId = null;

    public ?string $verifiedKg = null;
    public string $tareKg = '0';
    public ?int $categoryId = null;
    public string $grade = 'A';
    public array $contamination = [];
    public string $rejectionReason = '';
    public string $inspectorNote = '';
    public bool $showReject = false;

    /** True only while a classification request is in flight. */
    public bool $classifying = false;

    public array $contaminationOptions = [
        'None', 'Construction debris', 'Glass shards', 'Liquids', 'Nappies', 'Batteries', 'Textiles',
    ];

    public array $rejectionReasons = [
        'Contaminated — non-recoverable material',
        'Category mismatch',
        'Weight materially below declaration',
        'Hazardous content',
        'Spoiled beyond processing window',
    ];

    public function mount(): void
    {
        $this->selectFirst();
        $this->rejectionReason = $this->rejectionReasons[0];
    }

    private function selectFirst(): void
    {
        $first = WasteBatch::awaitingInspection()->orderByRaw($this->queueOrder())->first();
        if ($first) {
            $this->select($first->id);
        }
    }

    public function select(int $id): void
    {
        $batch = WasteBatch::find($id);
        if (! $batch) {
            return;
        }

        $this->batchId = $batch->id;
        $this->categoryId = $batch->confirmed_category_id ?? $batch->waste_category_id;
        $this->verifiedKg = null;
        $this->grade = 'A';
        $this->contamination = [];
        $this->showReject = false;
    }

    /** Oldest waiting first. IN_TRANSIT has no received_at, so fall back. */
    private function queueOrder(): string
    {
        return 'COALESCE(received_at, in_transit_at, collected_at, created_at) ASC';
    }

    public function toggleContamination(string $value): void
    {
        $this->contamination = in_array($value, $this->contamination, true)
            ? array_values(array_diff($this->contamination, [$value]))
            : [...$this->contamination, $value];
    }

    /**
     * Stand-in for a serial or Modbus feed from the platform scale.
     * At pilot scale the inspector reads the display and types it; the control
     * is that the agent is not the one typing.
     */
    public function takeReading(): void
    {
        $batch = $this->batch();
        if (! $batch) {
            return;
        }

        $base = $batch->collected_kg ?: $batch->expected_kg ?: 10;
        $this->verifiedKg = (string) round($base * (0.88 + (random_int(0, 140) / 1000)), 1);
    }

    public function getBatchProperty(): ?WasteBatch
    {
        return $this->batchId
            ? WasteBatch::with(['household.user', 'household.area', 'agent.user', 'category'])->find($this->batchId)
            : null;
    }

    private function batch(): ?WasteBatch
    {
        return $this->getBatchProperty();
    }

    /** Live preview as the weight is typed. Never persisted from here. */
    public function getPreviewProperty(): ?array
    {
        $batch = $this->batch();
        if (! $batch || ! is_numeric($this->verifiedKg)) {
            return null;
        }

        $settlement = app(SettlementService::class);

        // A misconfigured split is an operations problem, not a reason for the
        // bench to 500 the moment a weight is typed. The banner says what is
        // wrong; Accept stays disabled until it is fixed.
        if ($settlement->shareConfigError()) {
            return null;
        }

        $category = WasteCategory::find($this->categoryId) ?? $batch->effectiveCategory();

        return $settlement->preview($batch, (float) $this->verifiedKg, $category);
    }

    /**
     * Walk a batch up to INSPECTING from wherever the inspector found it.
     *
     * A load now arrives still marked IN_TRANSIT, so the bench books it in and
     * opens it in one motion. Each step still goes through the state machine,
     * so every hop is validated and leaves its own batch_events row -- the
     * arrival is recorded even though there is no longer a gate screen.
     */
    private function toInspecting(WasteBatch $batch, BatchStateMachine $machine): WasteBatch
    {
        if ($batch->status === BatchStatus::InTransit) {
            $batch = $machine->transition($batch, BatchStatus::Received, auth()->user(), [
                'facility_id' => $batch->facility_id ?? \App\Models\Facility::value('id'),
            ]);
        }

        if ($batch->status === BatchStatus::Received) {
            $batch = $machine->transition($batch, BatchStatus::Inspecting, auth()->user());
        }

        return $batch;
    }

    public function accept(
        BatchStateMachine $machine,
        SettlementService $settlement,
        IntegrityService $integrity,
    ): void {
        $batch = $this->batch();

        if (! $batch || ! is_numeric($this->verifiedKg) || (float) $this->verifiedKg <= 0) {
            $this->dispatch('toast', message: 'Take a scale reading first', kind: 'warn');

            return;
        }

        if ($problem = $settlement->shareConfigError()) {
            $this->dispatch('toast', message: $problem, kind: 'warn');

            return;
        }

        $batch = $this->toInspecting($batch, $machine);

        $batch = $machine->transition($batch, BatchStatus::Verified, auth()->user(), [
            'verified_kg' => (float) $this->verifiedKg,
            'confirmed_category_id' => $this->categoryId,
            'grade' => $this->grade,
            'contamination' => $this->contamination ?: null,
        ], [
            'tare_kg' => (float) $this->tareKg,
            'note' => $this->inspectorNote ?: null,
        ]);

        $settlement->settle($batch);
        $flag = $integrity->evaluateBatch($batch->refresh());

        $batch->household?->user?->notify(new BatchVerified($batch));
        $batch->agent?->user?->notify(new BatchVerified($batch));

        // One action, one alert. This used to fire two toasts back to back, which
        // read on screen as the same confirmation appearing twice.
        $this->dispatch('toast',
            message: $flag
                ? 'Batch accepted — settled, and the variance is flagged for review'
                : 'Batch accepted — settlement released',
            kind: $flag ? 'warn' : 'ok');

        $this->reset(['verifiedKg', 'contamination', 'inspectorNote']);
        $this->selectFirst();
    }

    public function reject(BatchStateMachine $machine): void
    {
        $batch = $this->batch();
        if (! $batch) {
            return;
        }

        $batch = $this->toInspecting($batch, $machine);

        $batch = $machine->transition($batch, BatchStatus::Rejected, auth()->user(), [
            'rejection_reason' => $this->rejectionReason,
            'contamination' => $this->contamination ?: null,
        ], ['note' => $this->inspectorNote ?: null]);

        $batch->household?->user?->notify(new BatchRejected($batch));
        $batch->agent?->user?->notify(new BatchRejected($batch));

        $this->dispatch('toast', message: 'Batch rejected — both parties notified', kind: 'warn');
        $this->showReject = false;
        $this->reset(['verifiedKg', 'contamination', 'inspectorNote']);
        $this->selectFirst();
    }

    /**
     * Ask the classifier what it sees in the agent's pickup photograph.
     *
     * Explicitly on demand rather than on batch load: it is a paid API call, the
     * inspector often does not need it, and a screen that quietly bills per page
     * view is a screen nobody trusts. The result is stored on the batch so the
     * same photograph is never sent twice.
     */
    public function classify(OpenAiService $ai): void
    {
        $batch = $this->batch;

        if (! $batch?->photo_path) {
            $this->dispatch('toast', message: 'No pickup photograph on this batch', kind: 'warn');

            return;
        }

        $this->classifying = true;

        $path = \Illuminate\Support\Facades\Storage::disk('public')->path($batch->photo_path);
        $result = $ai->classifyPhoto($path);

        $batch->update(['classification' => $result]);
        $this->classifying = false;

        $this->dispatch('toast',
            message: $result['generated']
                ? 'Classifier returned an assessment'
                : 'Classifier unavailable — showing the stored default',
            kind: $result['generated'] ? 'ok' : 'warn');
    }

    /**
     * Whether the inspector's confirmed category disagrees with the classifier.
     *
     * Surfacing the disagreement is the point: it is the signal that says either
     * the photograph was misleading or the model is drifting, and discarding it
     * would throw away the only feedback the classifier ever gets.
     */
    public function getDisagreementProperty(): ?string
    {
        $suggested = $this->batch?->classification['suggested'] ?? null;

        if (! $suggested || ! $this->categoryId) {
            return null;
        }

        $confirmed = WasteCategory::find($this->categoryId)?->key;

        return ($confirmed && $confirmed !== $suggested) ? $suggested : null;
    }

    public function render(OpenAiService $ai)
    {
        $queue = WasteBatch::awaitingInspection()
            ->with(['household.user', 'category'])
            ->orderByRaw($this->queueOrder())
            ->get();

        return view('livewire.console.inspection', [
            'shareError' => app(SettlementService::class)->shareConfigError(),
            'queue' => $queue,
            'queueKg' => $queue->sum(fn ($b) => $b->collected_kg ?? $b->expected_kg ?? 0),
            'categories' => WasteCategory::active()->get(),
            'aiEnabled' => $ai->enabled(),
        ])->layoutData(['page' => 'inspection', 'heading' => 'Inspection']);
    }
}
