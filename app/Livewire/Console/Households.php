<?php

namespace App\Livewire\Console;

use App\Models\Area;
use App\Models\Household;
use App\Models\Facility;
use App\Models\WasteBatch;
use App\Support\Geo;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.console')]
class Households extends Component
{
    use WithPagination;

    #[Url] public string $q = '';
    #[Url] public string $status = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    /** Row the operator has opened, if any. */
    public ?int $openId = null;

    public function show(int $id): void
    {
        $this->openId = $id;
    }

    public function close(): void
    {
        $this->openId = null;
    }

    public function getOpenHouseholdProperty(): ?Household
    {
        return $this->openId
            ? Household::with(['user', 'area', 'defaultCategory'])->find($this->openId)
            : null;
    }

    public function render()
    {
        $rows = Household::query()
            ->with(['user', 'area', 'defaultCategory'])
            ->withCount('batches')
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->q, function ($q) {
                $t = '%' . $this->q . '%';
                $q->where(fn ($w) => $w->where('code', 'like', $t)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $t))
                    ->orWhereHas('area', fn ($a) => $a->where('name', 'like', $t)));
            })
            ->orderBy('code');

        $areas = Area::withCount('households')->orderBy('name')->get()
            ->map(function ($a) {
                $a->kg = (float) WasteBatch::whereHas('household', fn ($h) => $h->where('area_id', $a->id))
                    ->sum('verified_kg');

                return $a;
            })
            ->sortByDesc('kg')
            ->values();

        return view('livewire.console.households', [
            'rows'   => $rows->paginate(12),
            'areas'  => $areas,
            'maxKg'  => max(0.01, (float) $areas->max('kg')),
            'total'  => Household::count(),
            'active' => Household::where('status', 'active')->count(),
            'avgKg'  => (float) WasteBatch::whereNotNull('verified_kg')->avg('verified_kg'),
            'pins'   => $this->densityPins(),
            'open'   => $this->openHousehold,
            'openBatches' => $this->openHousehold
                ? $this->openHousehold->batches()->with('category')
                    ->latest('requested_at')->limit(6)->get()
                : collect(),
            // Last eight verified weights, oldest first, for the sparkline.
            'openTrend' => $this->openHousehold
                ? $this->openHousehold->batches()->whereNotNull('verified_kg')
                    ->latest('verified_at')->limit(8)->pluck('verified_kg')->reverse()->values()->all()
                : [],
        ])->layoutData(['page' => 'households', 'heading' => 'Households']);
    }

    /**
     * Household positions plus the plant.
     *
     * Sampled at 120: the canvas is 100 units wide, so beyond roughly that many
     * pins the map stops showing density and starts showing a solid block.
     *
     * @return list<array<string, mixed>>
     */
    private function densityPins(): array
    {
        $pins = Household::whereNotNull('lat')
            ->inRandomOrder()
            ->limit(120)
            ->get(['lat', 'lng'])
            ->map(fn ($h) => Geo::pin((float) $h->lat, (float) $h->lng, 'house'))
            ->all();

        $f = Facility::first();
        $pins[] = Geo::pin((float) ($f?->lat ?? 6.5794), (float) ($f?->lng ?? 3.3792), 'plant', label: $f?->name ?? 'Lagos Facility');

        return $pins;
    }
}
