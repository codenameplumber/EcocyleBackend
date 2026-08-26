<?php

namespace App\Livewire\Agent;

use App\Enums\BatchStatus;
use App\Models\WasteBatch;
use App\Models\WasteCategory;
use App\Notifications\BatchCollected;
use App\Services\BatchStateMachine;
use App\Services\OpenAiService;
use App\Services\QrService;
use App\Services\JobMatcher;
use App\Support\Geo;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The five-stage pickup: travel, arrive, tag, weigh, done.
 *
 * The declared weight is checked against the household's prediction as it is
 * typed, so a large gap prompts a note at the point of capture rather than
 * being argued about at the gate an hour later.
 */
#[Layout('layouts.phone')]
class Pickup extends Component
{
    use WithFileUploads;

    public WasteBatch $batch;

    public int $stage = 1;
    public ?float $kg = null;
    public ?int $categoryId = null;
    public string $note = '';
    public $photo;
    public ?array $classification = null;
    public bool $classifying = false;

    public function mount(WasteBatch $batch): void
    {
        abort_unless($batch->agent_id === auth()->user()->agent?->id, 403);

        $this->batch = $batch;
        $this->categoryId = $batch->waste_category_id;
        $this->kg = $batch->collected_kg ?? $batch->expected_kg;

        // Resume where the batch actually is, rather than always at step one.
        $this->stage = match ($batch->status) {
            BatchStatus::Accepted  => 1,
            BatchStatus::Collected, BatchStatus::InTransit => 5,
            default => 1,
        };
    }

    public function next(): void
    {
        $this->stage = min(5, $this->stage + 1);
    }

    public function back(): void
    {
        $this->stage = max(1, $this->stage - 1);
    }

    /** Percentage the declared weight differs from the household's prediction. */
    public function getVarianceProperty(): ?float
    {
        if (! $this->kg || ! $this->batch->expected_kg) {
            return null;
        }

        return round((($this->kg - $this->batch->expected_kg) / $this->batch->expected_kg) * 100, 1);
    }

    public function updatedPhoto(): void
    {
        $this->validate(['photo' => 'image|max:8192']);

        $this->classifying = true;
        $path = $this->photo->getRealPath();

        // Advisory only. The inspector at the facility always overrides this.
        $this->classification = app(OpenAiService::class)->classifyPhoto($path);
        $this->classifying = false;
    }

    public function confirm(BatchStateMachine $machine)
    {
        $this->validate([
            'kg'         => 'required|numeric|min:0.5|max:500',
            'categoryId' => 'required|exists:waste_categories,id',
        ]);

        $stored = $this->photo
            ? $this->photo->store('pickups', 'public')
            : null;

        $batch = $machine->transition($this->batch, BatchStatus::Collected, auth()->user(), [
            'collected_kg'      => $this->kg,
            'waste_category_id' => $this->categoryId,
            'photo_path'        => $stored,
            'classification'    => $this->classification,
            'note'              => $this->note ?: $this->batch->note,
        ], [
            'declared_variance' => $this->variance,
        ]);

        $batch->household?->user?->notify(new BatchCollected($batch));

        $this->batch = $batch->refresh();
        $this->stage = 5;

        $this->dispatch('toast', message: 'Pickup recorded — batch on board', kind: 'ok');
    }

    /** Mark the load as travelling to the facility. */
    public function depart(BatchStateMachine $machine)
    {
        if ($this->batch->status === BatchStatus::Collected) {
            $this->batch = $machine->transition($this->batch, BatchStatus::InTransit, auth()->user());
            $this->dispatch('toast', message: 'On the way to the facility', kind: 'ok');
        }

        return $this->redirect(route('agent.route'), navigate: true);
    }

    public function render()
    {
        $agent = auth()->user()->agent;

        $h = $this->batch->household;

        // Stage 1 is the drive: you and the doorstep. Stage 2 confirms arrival
        // against the same point, which is why it gets its own tighter map.
        $travel = [];
        if ($agent->last_lat) {
            $travel[] = Geo::pin((float) $agent->last_lat, (float) $agent->last_lng,
                'agent', live: true, label: 'You');
        }
        if ($h?->lat) {
            $travel[] = Geo::pin((float) $h->lat, (float) $h->lng,
                'house', label: explode(' ', $h->name())[0]);
        }

        return view('livewire.agent.pickup', [
            'agent'      => $agent,
            'travelPins' => count($travel) > 1 ? $travel : [],
            'pickupPin'  => $h?->lat
                ? [Geo::pin((float) $h->lat, (float) $h->lng, 'house', live: true, label: 'Pickup point')]
                : [],
            'distanceKm' => ($agent->last_lat && $h?->lat)
                ? round(app(JobMatcher::class)->distanceKm(
                    (float) $agent->last_lat, (float) $agent->last_lng,
                    (float) $h->lat, (float) $h->lng), 1)
                : null,
            'categories' => WasteCategory::active()->get(),
            'household'  => $this->batch->household,
            'onBoard'    => $agent->loadOnBoardKg(),
            'qr'         => app(QrService::class)->svg(url('/b/' . $this->batch->code)),
        ])->layoutData([
            'app' => 'agent', 'page' => 'jobs',
            'heading' => 'Pickup',
            'sub' => $this->batch->shortCode() . ' · ' . $this->batch->household->name(),
            'back' => route('agent.jobs'),
        ]);
    }
}
