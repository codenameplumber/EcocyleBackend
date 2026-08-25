<?php

namespace App\Livewire\Console;

use App\Models\Area;
use App\Models\Household;
use App\Models\WasteBatch;
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
        ])->layoutData(['page' => 'households', 'heading' => 'Households']);
    }
}
