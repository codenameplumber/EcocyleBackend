<?php

namespace App\Livewire\Agent;

use App\Livewire\Concerns\TracksAgentPosition;
use App\Models\WasteBatch;
use App\Services\BatchStateMachine;
use App\Services\JobMatcher;
use App\Support\Geo;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.phone')]
class Jobs extends Component
{
    use TracksAgentPosition;

    /** Batch the agent has tapped Accept on but not yet confirmed. */
    public ?int $confirmId = null;

    public function ask(int $batchId): void
    {
        $this->confirmId = $batchId;
    }

    public function dismiss(): void
    {
        $this->confirmId = null;
    }

    public function getConfirmingProperty(): ?WasteBatch
    {
        return $this->confirmId
            ? WasteBatch::with(['household.user', 'household.area', 'category'])->find($this->confirmId)
            : null;
    }

    public function accept(int $batchId, BatchStateMachine $machine)
    {
        $this->confirmId = null;

        $agent = auth()->user()->agent;
        $batch = WasteBatch::find($batchId);

        if (! $batch) {
            return;
        }

        // The database decides who won, not the browser.
        if (! $machine->claim($batch, $agent)) {
            $this->dispatch('toast', message: 'Another agent took that one first', kind: 'warn');

            return;
        }

        $batch->household?->user?->notify(new \App\Notifications\JobAccepted($batch->refresh()));

        $this->dispatch('toast', message: 'Job accepted — it is on your route', kind: 'ok');

        return $this->redirect(route('agent.pickup', $batch), navigate: true);
    }

    public function render(JobMatcher $matcher)
    {
        $agent = auth()->user()->agent;

        $jobs = $matcher->rank($agent, 20);

        // The agent's own position first, then every job on offer. An agent
        // reading this map is asking "which way do I go", so their pin has to
        // be on it.
        $pins = [];
        if ($agent->last_lat) {
            $pins[] = Geo::pin((float) $agent->last_lat, (float) $agent->last_lng, 'agent', live: true, label: 'You');
        }
        foreach ($jobs as $j) {
            if ($j->household?->lat) {
                $pins[] = Geo::pin((float) $j->household->lat, (float) $j->household->lng, 'house');
            }
        }

        return view('livewire.agent.jobs', [
            'agent'     => $agent,
            'jobs'      => $jobs,
            'pins'      => $pins,
            'remaining' => $agent->remainingCapacityKg(),
            'posAge'    => $this->positionAgeMinutes(),
        ])->layoutData([
            'app' => 'agent', 'page' => 'jobs',
            'heading' => 'Available jobs',
            'sub' => 'Ranked for your vehicle and position',
        ]);
    }
}
