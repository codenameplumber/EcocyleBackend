<?php

namespace App\Livewire\Household;

use App\Enums\BatchStatus;
use App\Models\WasteBatch;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every batch this household has ever sent, with the detail sheet behind each row.
 *
 * The wallet answers "what am I owed"; this answers "what happened to my waste".
 * They read the same rows and deliberately show different columns — weight and
 * outcome here, money there.
 */
#[Layout('layouts.phone')]
class History extends Component
{
    use WithPagination;

    public string $filter = 'all';

    /** Batch id of the row whose detail sheet is open, if any. */
    public ?int $openId = null;

    public function setFilter(string $f): void
    {
        $this->filter = $f;
        $this->resetPage();
    }

    public function show(int $id): void
    {
        $this->openId = $id;
    }

    public function close(): void
    {
        $this->openId = null;
    }

    public function render()
    {
        $household = auth()->user()->household;

        $query = $household->batches()->with(['category', 'confirmedCategory', 'agent.user'])
            ->latest('requested_at');

        $query = match ($this->filter) {
            'all' => $query,
            // "In progress" is everything that has not reached a terminal state,
            // asked of the enum rather than spelled out here so a new status
            // cannot silently fall out of the filter.
            'open' => $query->whereNotIn('status', collect(BatchStatus::cases())
                ->filter(fn ($s) => $s->isTerminal())->map(fn ($s) => $s->value)->all()),
            default => $query->where('status', $this->filter),
        };

        $all = $household->batches();

        return view('livewire.household.history', [
            'batches'    => $query->paginate(12),
            'openBatch'  => $this->openId
                ? $household->batches()->with(['category', 'confirmedCategory', 'agent.user', 'events'])->find($this->openId)
                : null,
            'totalKg'    => (float) $all->clone()->sum('verified_kg'),
            'totalCount' => (int) $all->clone()->count(),
            'totalEarn'  => (float) $all->clone()->sum('household_share'),
            'rejected'   => (int) $all->clone()->where('status', BatchStatus::Rejected->value)->count(),
            // The last eight verified weights, oldest first, for the sparkline.
            'trend'      => $household->batches()
                ->whereNotNull('verified_kg')
                ->latest('verified_at')
                ->limit(8)
                ->pluck('verified_kg')
                ->reverse()
                ->values()
                ->all(),
        ])->layoutData([
            'app'     => 'household',
            'page'    => 'home',
            'heading' => 'Collections',
            'sub'     => 'Every batch you have sent',
            'back'    => route('app'),
        ]);
    }
}
