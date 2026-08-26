<?php

namespace App\Livewire\Agent;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.phone')]
class Earnings extends Component
{
    /** Filters the settlement list by tag code or household name. */
    public string $q = '';

    public function cashOut(\App\Services\LedgerService $ledger): void
    {
        $agent = auth()->user()->agent;

        // Same guard the household wallet applies. A payout row with nowhere to
        // send the money is a row the payment run cannot action.
        if (! $agent->hasPayoutAccount()) {
            $this->dispatch('toast',
                message: 'Add a payout account on your profile first', kind: 'warn');

            return;
        }

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

        $nextRun = now()->next('Monday');

        return view('livewire.agent.earnings', [
            'account'  => $agent->payoutAccount(),
            'nextRun'  => $nextRun,
            'heldTotal' => (float) $agent->payouts()->where('status', 'held')->sum('amount'),
            'recentPayouts' => $agent->payouts()->latest()->limit(5)->get(),
            'agent'    => $agent,
            'balance'  => $agent->balance(),
            'pending'  => $agent->pendingAmount(),
            'earned'   => $agent->totalEarned(),
            'paidOut'  => $agent->totalPaidOut(),
            'accuracy' => $agent->weightAccuracy(),
            'trips'    => $agent->tripsCompleted(),
            'weeks'    => $weeks,
            'recent'   => $agent->batches()->settleable()->with(['household.user', 'category'])
                ->when($this->q !== '', function ($query) {
                    $term = '%' . trim($this->q) . '%';
                    $query->where(fn ($w) => $w->where('code', 'like', $term)
                        ->orWhereHas('household.user', fn ($u) => $u->where('name', 'like', $term)));
                })
                ->latest('verified_at')->limit($this->q !== '' ? 20 : 6)->get(),
            'payouts'  => $agent->morphMany(\App\Models\Payout::class, 'party')->latest()->limit(4)->get(),
        ])->layoutData([
            'app' => 'agent', 'page' => 'earn',
            'heading' => 'Earnings', 'sub' => auth()->user()->name . ' · ' . $agent->code,
        ]);
    }
}
