<?php

namespace App\Livewire\Console;

use App\Enums\BatchStatus;
use App\Models\WasteBatch;
use App\Services\BatchStateMachine;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every batch in one worked table.
 *
 * This was a ten-column board. A board is a nice picture of a state machine and
 * a poor way to work one: it capped each column at twelve cards, it could not be
 * searched or sorted, and opening a card gave you a record you could read but
 * not act on. An operator watching a batch stall had to go and find another
 * screen to move it.
 *
 * So: a table you can filter and page through, and a row that opens into the
 * same record with the state machine's own next steps attached to it.
 */
#[Layout('layouts.console')]
class Pipeline extends Component
{
    use WithPagination;

    #[Url(as: 'q', keep: false)]
    public string $search = '';

    #[Url(as: 'status', keep: false)]
    public string $filter = 'open';

    public ?int $openId = null;

    /** Reason captured when the chosen transition is a rejection. */
    public string $note = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setFilter(string $f): void
    {
        $this->filter = $f;
        $this->resetPage();
    }

    public function show(int $id): void
    {
        $this->openId = $id;
        $this->note = '';
    }

    public function close(): void
    {
        $this->openId = null;
        $this->note = '';
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

    /**
     * The moves this batch may legally make, straight off the enum.
     *
     * Read from BatchStatus::allowedNext() rather than a list kept here, so the
     * buttons on the sheet cannot drift from what the state machine will accept.
     *
     * @return array<int, BatchStatus>
     */
    public function getNextStatesProperty(): array
    {
        return $this->openBatch?->status->allowedNext() ?? [];
    }

    /**
     * Advance a batch by hand.
     *
     * Deliberately narrow. It moves state and writes the audit row; it does not
     * value anything. Verification stays on the inspection bench because that is
     * where a weight is entered, and a weight is what settlement is computed
     * from — so this refuses to be the back door onto the money.
     */
    public function moveTo(string $status, BatchStateMachine $machine): void
    {
        $batch = $this->openBatch;
        $target = BatchStatus::tryFrom($status);

        if (! $batch || ! $target) {
            return;
        }

        if (! $batch->status->canTransitionTo($target)) {
            $this->dispatch('toast',
                message: $batch->status->label() . ' cannot go to ' . $target->label(),
                kind: 'warn');

            return;
        }

        // Verified is the moment money is released, and it is computed from a
        // weight. There is no weight on this screen, so there is no Verify here.
        if ($target === BatchStatus::Verified) {
            $this->dispatch('toast',
                message: 'Verify on the inspection bench — settlement needs a scale reading',
                kind: 'warn');

            return;
        }

        if ($target === BatchStatus::Rejected && trim($this->note) === '') {
            $this->dispatch('toast', message: 'A rejection needs a reason', kind: 'warn');

            return;
        }

        $attributes = $target === BatchStatus::Rejected
            ? ['rejection_reason' => trim($this->note)]
            : [];

        $machine->transition($batch, $target, auth()->user(), $attributes, [
            'source' => 'pipeline',
            'note'   => trim($this->note) ?: null,
        ]);

        $this->note = '';

        $this->dispatch('toast',
            message: $batch->shortCode() . ' → ' . $target->label(),
            kind: 'ok');
    }

    public function render()
    {
        $base = fn () => WasteBatch::query()
            ->when($this->filter === 'open', fn ($q) => $q->open())
            ->when(
                BatchStatus::tryFrom($this->filter),
                fn ($q) => $q->where('status', $this->filter)
            )
            ->when($this->search !== '', function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(fn ($w) => $w
                    ->where('code', 'like', $term)
                    ->orWhereHas('household.user', fn ($u) => $u->where('name', 'like', $term))
                    ->orWhereHas('agent.user', fn ($u) => $u->where('name', 'like', $term)));
            });

        // Counts per state drive the filter chips, so an operator can see where
        // the work has piled up before deciding what to open.
        $counts = WasteBatch::query()
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) c, SUM(COALESCE(verified_kg, collected_kg, expected_kg, 0)) kg')
            ->get()
            ->keyBy('status');

        return view('livewire.console.pipeline', [
            'batches' => $base()
                ->with(['household.user', 'agent.user', 'category', 'confirmedCategory'])
                ->latest('updated_at')
                ->paginate(20),
            'states'  => BatchStatus::pipeline(),
            'counts'  => $counts,
            'openCount' => WasteBatch::open()->count(),
            'shownKg' => (float) $base()->sum(
                DB::raw('COALESCE(verified_kg, collected_kg, expected_kg, 0)')
            ),
        ])->layoutData(['page' => 'pipeline', 'heading' => 'Waste pipeline']);
    }
}
