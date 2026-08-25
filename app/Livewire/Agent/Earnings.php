<?php

namespace App\Livewire\Agent;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.phone')]
class Earnings extends Component
{
    public function cashOut(\App\Services\LedgerService $ledger): void
    {
        $agent = auth()->user()->agent;
        $payout = $ledger->openPayout($agent);

        $this->dispatch(
            'toast',
            message: $payout
                ? 'Payout ' . $payout->reference . ' queued for ₦' . number_format((float) $payout->amount, 2)
                : 'Nothing outstanding to pay out',
            kind: $payout ? 'ok' : 'warn',
        );
    }

    public function render()
    {
        $agent = auth()->user()->agent;

        $weeks = collect(range(11, 0))->map(function ($w) use ($agent) {
            $start = now()->subWeeks($w)->startOfWeek();

            return (float) $agent->batches()
                ->whereBetween('verified_at', [$start, $start->copy()->endOfWeek()])
                ->sum('verified_kg');
        });

        return view('livewire.agent.earnings', [
            'agent'    => $agent,
            'balance'  => $agent->balance(),
            'pending'  => $agent->pendingAmount(),
            'earned'   => $agent->totalEarned(),
            'paidOut'  => $agent->totalPaidOut(),
            'accuracy' => $agent->weightAccuracy(),
            'trips'    => $agent->tripsCompleted(),
            'weeks'    => $weeks,
            'recent'   => $agent->batches()->settleable()->with(['household.user', 'category'])
                ->latest('verified_at')->limit(6)->get(),
            'payouts'  => $agent->morphMany(\App\Models\Payout::class, 'party')->latest()->limit(4)->get(),
        ])->layoutData([
            'app' => 'agent', 'page' => 'earn',
            'heading' => 'Earnings', 'sub' => auth()->user()->name . ' · ' . $agent->code,
        ]);
    }
}
