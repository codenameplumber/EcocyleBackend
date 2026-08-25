<?php

namespace App\Livewire\Console;

use App\Models\IntegrityFlag;
use App\Services\IntegrityService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.console')]
class Integrity extends Component
{
    public ?int $flagId = null;
    public string $filter = 'open';

    public function mount(): void
    {
        $this->flagId = IntegrityFlag::open()->latest()->value('id');
    }

    public function select(int $id): void
    {
        $this->flagId = $id;
    }

    public function setFilter(string $f): void
    {
        $this->filter = $f;
    }

    /** Every flag is appealable, so resolving records who decided and when. */
    public function resolve(string $outcome): void
    {
        $flag = IntegrityFlag::find($this->flagId);
        if (! $flag) {
            return;
        }

        $flag->update([
            'status'      => $outcome,
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]);

        if ($flag->agent) {
            app(IntegrityService::class)->refreshAgentScore($flag->agent);
        }

        $this->dispatch('toast',
            message: "Case marked {$outcome}",
            kind: $outcome === 'confirmed' ? 'warn' : 'ok');

        $this->flagId = IntegrityFlag::open()->latest()->value('id');
    }

    public function getFlagProperty(): ?IntegrityFlag
    {
        return $this->flagId
            ? IntegrityFlag::with(['agent.user', 'batch.household.user'])->find($this->flagId)
            : null;
    }

    public function render()
    {
        $cases = IntegrityFlag::query()
            ->when($this->filter !== 'all', fn ($q) => $q->where('status', $this->filter))
            ->with(['agent.user', 'batch'])
            ->latest()
            ->get();

        return view('livewire.console.integrity', [
            'cases'       => $cases,
            'openCount'   => IntegrityFlag::open()->count(),
            'confirmed'   => IntegrityFlag::where('status', 'confirmed')->count(),
            'valueAtRisk' => (float) IntegrityFlag::open()->with('batch')->get()
                ->sum(fn ($f) => (float) ($f->batch?->value_naira ?? 0)),
        ])->layoutData(['page' => 'integrity', 'heading' => 'Integrity']);
    }
}
