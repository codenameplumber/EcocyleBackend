<?php

namespace App\Livewire\Agent;

use App\Enums\BatchStatus;
use App\Services\JobMatcher;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.phone')]
class Today extends Component
{
    public function toggleOnline(): void
    {
        $agent = auth()->user()->agent;
        $agent->update(['is_online' => ! $agent->is_online, 'last_seen_at' => now()]);

        $this->dispatch(
            'toast',
            message: $agent->is_online ? 'You are online — jobs will appear' : 'You are offline',
            kind: $agent->is_online ? 'ok' : 'warn',
        );
    }

    public function render(JobMatcher $matcher)
    {
        $agent = auth()->user()->agent;

        $current = $agent->batches()
            ->whereIn('status', [BatchStatus::Accepted->value, BatchStatus::Collected->value, BatchStatus::InTransit->value])
            ->with(['household.user', 'category'])
            ->latest('accepted_at')
            ->first();

        $earnedToday = (float) $agent->ledgerEntries()
            ->where('direction', 'credit')
            ->whereDate('created_at', today())
            ->sum('amount');

        $weekKg = (float) $agent->batches()
            ->where('verified_at', '>=', now()->startOfWeek())
            ->sum('verified_kg');

        return view('livewire.agent.today', [
            'agent'       => $agent,
            'current'     => $current,
            'earnedToday' => $earnedToday,
            'balance'     => $agent->balance(),
            'onBoard'     => $agent->loadOnBoardKg(),
            'remaining'   => $agent->remainingCapacityKg(),
            'held'        => $agent->batches()->whereIn('status', ['COLLECTED', 'IN_TRANSIT'])->count(),
            'jobsToday'   => $agent->batches()->whereDate('accepted_at', today())->count(),
            'weekKg'      => $weekKg,
            'nearby'      => $matcher->rank($agent, 3),
        ])->layoutData([
            'app' => 'agent', 'page' => 'today',
            'heading' => 'Today',
            'sub' => auth()->user()->name . ' · ' . $agent->vehicle_type,
        ]);
    }
}
