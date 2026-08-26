<?php

namespace App\Livewire\Console;

use App\Models\IntegrityFlag;
use App\Services\IntegrityService;
use App\Support\Geo;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.console')]
class Integrity extends Component
{
    use WithPagination;

    public ?int $flagId = null;
    public string $filter = 'open';
    public bool $showRules = false;

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
        $this->resetPage();
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

    /**
     * Registered address against confirmed pickup point, as two pins.
     *
     * Only drawn when both are known — an empty map next to a case is worse than
     * no map, because it reads as "we looked and found nothing".
     *
     * @return list<array<string, mixed>>
     */
    private function trail(?IntegrityFlag $flag): array
    {
        $b = $flag?->batch;
        $h = $b?->household;

        if (! $b?->pickup_lat || ! $h?->lat) {
            return [];
        }

        return [
            Geo::pin((float) $h->lat, (float) $h->lng, 'house', label: 'Registered'),
            Geo::pin((float) $b->pickup_lat, (float) $b->pickup_lng, 'agent', live: true, label: 'Confirmed'),
        ];
    }

    /**
     * Declared against verified for the agent's last dozen weighed batches.
     *
     * A single flagged batch says almost nothing on its own — an agent can
     * mis-read a scale once. The series is what tells a reviewer whether to have
     * a conversation or close the case.
     *
     * @return array{labels: list<string>, declared: list<float>, verified: list<float>, count: int}
     */
    private function evidence(?IntegrityFlag $flag): array
    {
        $agent = $flag?->agent;

        if (! $agent) {
            return ['labels' => [], 'declared' => [], 'verified' => [], 'count' => 0];
        }

        $rows = $agent->batches()
            ->whereNotNull('verified_kg')
            ->whereNotNull('collected_kg')
            ->latest('verified_at')
            ->limit(12)
            ->get(['code', 'collected_kg', 'verified_kg'])
            ->reverse()
            ->values();

        return [
            'labels' => $rows->map(fn ($b) => \Illuminate\Support\Str::afterLast($b->code, '-'))->all(),
            'declared' => $rows->map(fn ($b) => round((float) $b->collected_kg, 1))->all(),
            'verified' => $rows->map(fn ($b) => round((float) $b->verified_kg, 1))->all(),
            'count' => $rows->count(),
        ];
    }

    /** Metres between the two points on the trail, or null when there is no trail. */
    private function trailDistance(?IntegrityFlag $flag): ?float
    {
        $d = $flag?->evidence['distance_m'] ?? null;

        return $d !== null ? (float) $d : null;
    }

    public function render(IntegrityService $integrity)
    {
        $cases = IntegrityFlag::query()
            ->when($this->filter !== 'all', fn ($q) => $q->where('status', $this->filter))
            ->with(['agent.user', 'batch'])
            ->latest()
            ->paginate(25);

        return view('livewire.console.integrity', [
            'cases'       => $cases,
            'openCount'   => IntegrityFlag::open()->count(),
            'confirmed'   => IntegrityFlag::where('status', 'confirmed')->count(),
            'valueAtRisk' => (float) IntegrityFlag::open()->with('batch')->get()
                ->sum(fn ($f) => (float) ($f->batch?->value_naira ?? 0)),
            'signals'     => IntegrityService::SIGNALS,
            'thresholds'  => $integrity->thresholds(),
            // How many cases each detector has actually raised, so the grid
            // reports its own track record rather than just its intent.
            'flagCounts'  => IntegrityFlag::selectRaw('kind, COUNT(*) c')
                ->groupBy('kind')->pluck('c', 'kind')->all(),
            'trail'         => $this->trail($this->flag),
            // The subject agent's recent weighing history, so the reviewer can see
            // whether this case is a one-off or the shape of a pattern.
            'evidence'      => $this->evidence($this->flag),
            'trailDistance' => $this->trailDistance($this->flag),
        ])->layoutData(['page' => 'integrity', 'heading' => 'Integrity']);
    }
}
