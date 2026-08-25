<?php

namespace App\Livewire\Console;

use App\Enums\BatchStatus;
use App\Models\WasteBatch;
use App\Models\WasteCategory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.console')]
class Batches extends Component
{
    use WithPagination;

    #[Url] public string $q = '';
    #[Url] public string $status = '';
    #[Url] public string $category = '';
    #[Url] public bool $varianceOnly = false;

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $tolerance = (float) config('ecocycle.variance_tolerance');

        $batches = WasteBatch::query()
            ->with(['household.user', 'agent.user', 'category', 'confirmedCategory'])
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->category, fn ($q) => $q->where('waste_category_id', $this->category))
            ->when($this->varianceOnly, fn ($q) => $q->whereRaw('ABS(variance_pct) > ?', [$tolerance]))
            ->when($this->q, function ($q) {
                $term = '%' . $this->q . '%';
                $q->where(fn ($w) => $w->where('code', 'like', $term)
                    ->orWhereHas('household.user', fn ($h) => $h->where('name', 'like', $term))
                    ->orWhereHas('agent.user', fn ($a) => $a->where('name', 'like', $term)));
            })
            ->latest('created_at');

        return view('livewire.console.batches', [
            'batches'    => $batches->paginate(15),
            'statuses'   => BatchStatus::cases(),
            'categories' => WasteCategory::active()->get(),
            'totalKg'    => (float) WasteBatch::sum('verified_kg'),
            'flagged'    => WasteBatch::whereRaw('ABS(variance_pct) > ?', [$tolerance])->count(),
        ])->layoutData(['page' => 'batches', 'heading' => 'Batches']);
    }
}
