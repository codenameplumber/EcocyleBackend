<?php

namespace App\Livewire\Household;

use App\Models\LedgerEntry;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use App\Services\LedgerService;

#[Layout('layouts.phone')]
class Wallet extends Component
{
    use WithPagination;

    public string $filter = 'all';

    public function setFilter(string $f): void
    {
        $this->filter = $f;
        $this->resetPage();
    }

    /**
     * Open a payout against everything currently unattached in the ledger.
     *
     * Deliberately not "withdraw": nothing leaves here. It moves the balance
     * into a payout row for the facility's next payment run, and the debit that
     * balances it is written by LedgerService in the same transaction.
     */
    public bool $showWithdraw = false;

    public function openWithdraw(): void
    {
        if ($this->balanceNow() <= 0) {
            $this->dispatch('toast', message: 'Nothing to withdraw yet', kind: 'warn');

            return;
        }

        $this->showWithdraw = true;
    }

    private function balanceNow(): float
    {
        return auth()->user()->household->balance();
    }

    public function requestPayout(LedgerService $ledger): void
    {
        $this->showWithdraw = false;
        $household = auth()->user()->household;

        if (! $household->hasPayoutAccount()) {
            $this->dispatch('toast', message: 'Add a payout account first', kind: 'warn');

            return;
        }

        $payout = $ledger->openPayout($household);

        $this->dispatch('toast',
            message: $payout
                ? 'Payout ' . $payout->reference . ' opened for ₦' . number_format((float) $payout->amount, 2)
                : 'Nothing to pay out yet',
            kind: $payout ? 'ok' : 'warn');
    }

    /** Eight months of credited value, for the earnings chart. */
    private function monthly($household): array
    {
        $labels = $values = [];

        for ($i = 7; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $labels[] = $m->format('M');
            $values[] = round((float) $household->ledgerEntries()
                ->where('direction', 'credit')
                ->whereBetween('created_at', [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()])
                ->sum('amount'), 2);
        }

        // Growth against the previous month, only when there is one to compare to.
        $delta = null;
        if (count($values) >= 2 && $values[count($values) - 2] > 0) {
            $delta = ($values[count($values) - 1] - $values[count($values) - 2]) / $values[count($values) - 2] * 100;
        }

        return ['labels' => $labels, 'values' => $values, 'delta' => $delta];
    }

    public function render()
    {
        $household = auth()->user()->household;

        $batches = $household->batches()->with('category')->latest('requested_at');

        if ($this->filter !== 'all') {
            $batches->where('status', strtoupper($this->filter));
        }

        return view('livewire.household.wallet', [
            'household' => $household,
            'balance'   => $household->balance(),
            'earned'    => $household->totalEarned(),
            'paidOut'   => $household->totalPaidOut(),
            'pending'   => $household->pendingAmount(),
            'lifetimeKg'=> $household->lifetimeKg(),
            // What agents earned moving this household's material -- shown so the
            // household can see the other side of the split, not just their own.
            'agentPaid' => (float) $household->batches()->sum('agent_share'),
            'batches'   => $batches->paginate(8),
            'entries'   => $household->ledgerEntries()->with('batch')->latest('created_at')->limit(10)->get(),
            'rates'     => \App\Models\WasteCategory::active()->get(),
            'monthly'   => $this->monthly($household),
            'account'   => $household->payoutAccount(),
            'payouts'   => $household->payouts()->latest()->limit(5)->get(),
        ])->layoutData([
            'app' => 'household', 'page' => 'wallet',
            'heading' => 'Wallet', 'sub' => auth()->user()->name,
        ]);
    }
}
