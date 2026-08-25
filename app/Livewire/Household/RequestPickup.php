<?php

namespace App\Livewire\Household;

use App\Enums\BatchStatus;
use App\Models\WasteBatch;
use App\Models\WasteCategory;
use App\Services\BatchCodeGenerator;
use App\Services\BatchStateMachine;
use App\Services\SettlementService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Creates a batch and puts it on the marketplace.
 *
 * The payout shown here is explicitly an estimate. The batch is created with an
 * expected_kg only -- collected_kg and verified_kg stay null until an agent and
 * then the facility scale fill them in.
 */
#[Layout('layouts.phone')]
class RequestPickup extends Component
{
    public ?int $categoryId = null;
    public float $kg = 10.0;
    public string $window = 'Morning';
    public string $day = 'tomorrow';
    public string $note = '';
    public ?float $lat = null;
    public ?float $lng = null;

    public function mount(): void
    {
        $household = auth()->user()->household;
        $this->categoryId = $household->default_category_id ?? WasteCategory::active()->value('id');
        $this->kg = $household->predictedKg();
        $this->lat = $household->lat;
        $this->lng = $household->lng;
    }

    public function setCoords(float $lat, float $lng): void
    {
        $this->lat = round($lat, 7);
        $this->lng = round($lng, 7);
        $this->dispatch('toast', message: 'Pickup point updated', kind: 'ok');
    }

    /** Estimate only — the facility scale is what actually settles. */
    public function getEstimateProperty(): array
    {
        $category = WasteCategory::find($this->categoryId);
        $rate = (float) ($category?->rate_per_kg ?? 0);
        $value = round($this->kg * $rate, 2);
        $share = round($value * (float) config('ecocycle.household_share'), 2);

        return ['rate' => $rate, 'value' => $value, 'share' => $share];
    }

    public function submit(BatchCodeGenerator $codes, BatchStateMachine $machine)
    {
        $this->validate([
            'categoryId' => 'required|exists:waste_categories,id',
            'kg'         => 'required|numeric|min:1|max:200',
        ]);

        $household = auth()->user()->household;

        $windowStart = match ($this->day) {
            'today'    => now(),
            'tomorrow' => now()->addDay()->startOfDay(),
            default    => now()->addDays(2)->startOfDay(),
        };

        $offset = match ($this->window) {
            'Morning'   => [8, 12],
            'Afternoon' => [12, 16],
            'Evening'   => [16, 20],
            default     => [8, 20],
        };

        $batch = WasteBatch::create([
            'ulid'              => $codes->ulid(),
            'code'              => $codes->batchCode(),
            'household_id'      => $household->id,
            'waste_category_id' => $this->categoryId,
            'status'            => BatchStatus::Requested,
            'expected_kg'       => $this->kg,
            'pickup_lat'        => $this->lat ?? $household->lat,
            'pickup_lng'        => $this->lng ?? $household->lng,
            'note'              => $this->note ?: null,
            'requested_at'      => now(),
            'window_start'      => $windowStart->copy()->setHour($offset[0]),
            'window_end'        => $windowStart->copy()->setHour($offset[1]),
        ]);

        // Straight onto the marketplace so agents can see it.
        $machine->transition($batch, BatchStatus::Available, auth()->user());

        $this->dispatch('toast', message: 'Pickup requested — nearby agents notified', kind: 'ok');

        return $this->redirect(route('app'), navigate: true);
    }

    public function render()
    {
        return view('livewire.household.request-pickup', [
            'categories' => WasteCategory::active()->get(),
            'household'  => auth()->user()->household,
        ])->layoutData([
            'app' => 'household', 'page' => 'request',
            'heading' => 'Request a pickup', 'sub' => 'Waste is ready for collection',
            'back' => route('app'),
        ]);
    }
}
