<?php

namespace App\Livewire\Console;

use App\Models\Agent;
use App\Models\Facility;
use App\Models\IntegrityFlag;
use App\Support\Geo;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.console')]
class Agents extends Component
{
    #[Url] public string $q = '';

    public ?int $openId = null;

    public function show(int $id): void
    {
        $this->openId = $id;
    }

    public function close(): void
    {
        $this->openId = null;
    }

    public function getOpenAgentProperty(): ?\App\Models\Agent
    {
        return $this->openId
            ? \App\Models\Agent::with(['user', 'flags' => fn ($q) => $q->where('status', 'open')])
                ->find($this->openId)
            : null;
    }

    public function render()
    {
        $agents = Agent::query()
            ->with('user')
            ->withCount('batches')
            ->when($this->q, function ($q) {
                $t = '%' . $this->q . '%';
                $q->where(fn ($w) => $w->where('code', 'like', $t)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $t)));
            })
            ->orderByDesc('is_online')
            ->orderBy('code')
            ->get();

        return view('livewire.console.agents', [
            'agents'   => $agents,
            'fleet'    => $agents->groupBy('vehicle_type')->map->count(),
            'online'   => $agents->where('is_online', true)->count(),
            'avgScore' => round((float) $agents->avg('integrity_score'), 1),
            'flagged'  => IntegrityFlag::open()->whereNotNull('agent_id')->distinct()->count('agent_id'),
            'pins'     => $this->fieldPins($agents),
            'open'     => $this->openAgent,
            'openBatches' => $this->openAgent
                ? $this->openAgent->batches()->with(['household.user', 'category'])
                    ->latest('accepted_at')->limit(6)->get()
                : collect(),
            // Declared against verified for the last eight weighed batches, so
            // the sheet shows the pattern the score is derived from.
            'openTrend' => $this->openAgent
                ? $this->openAgent->batches()->whereNotNull('verified_kg')
                    ->latest('verified_at')->limit(8)->pluck('verified_kg')->reverse()->values()->all()
                : [],
        ])->layoutData(['page' => 'agents', 'heading' => 'Agents']);
    }

    /**
     * Every agent with a last-known position, live ones marked.
     *
     * Not sampled: thirty pins is readable, and an operator asking "where is my
     * fleet" needs the whole fleet, not a representative sample of it.
     *
     * @return list<array<string, mixed>>
     */
    private function fieldPins($agents): array
    {
        $pins = $agents
            ->filter(fn ($a) => $a->last_lat !== null)
            ->map(fn ($a) => Geo::pin((float) $a->last_lat, (float) $a->last_lng, 'agent', live: (bool) $a->is_online))
            ->values()
            ->all();

        $f = Facility::first();
        $pins[] = Geo::pin((float) ($f?->lat ?? 6.5794), (float) ($f?->lng ?? 3.3792), 'plant', label: $f?->name ?? 'Lagos Facility');

        return $pins;
    }
}
