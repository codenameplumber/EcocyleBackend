<?php

namespace App\Livewire\Agent;

use App\Models\WasteBatch;
use App\Services\BatchStateMachine;
use App\Services\JobMatcher;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.phone')]
class Jobs extends Component
{
    public function accept(int $batchId, BatchStateMachine $machine)
    {
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

        return view('livewire.agent.jobs', [
            'agent'     => $agent,
            'jobs'      => $matcher->rank($agent, 20),
            'remaining' => $agent->remainingCapacityKg(),
        ])->layoutData([
            'app' => 'agent', 'page' => 'jobs',
            'heading' => 'Available jobs',
            'sub' => 'Ranked for your vehicle and position',
        ]);
    }
}
