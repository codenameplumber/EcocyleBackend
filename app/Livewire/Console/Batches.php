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

    public bool $showScan = false;
    public string $scanCode = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * Look up a batch by the code on its tag.
     *
     * There is no camera here — the console runs on a desktop at the gate office
     * and the inspector reads the code off the sticker. When a handheld scanner
     * is plugged in it types into this field, which is all a scanner does.
     */
    public function lookup(): void
    {
        $code = trim($this->scanCode);

        if ($code === '') {
            return;
        }

        $batch = WasteBatch::where('code', $code)->first();

        if (! $batch) {
            $this->dispatch('toast', message: "No batch tagged {$code}", kind: 'err');

            return;
        }

        $this->showScan = false;
        $this->scanCode = '';
        $this->redirect('/b/' . $batch->code, navigate: true);
    }

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
