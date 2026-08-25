<?php

namespace App\Livewire\Household;

use App\Models\LedgerEntry;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

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
        ])->layoutData([
            'app' => 'household', 'page' => 'wallet',
            'heading' => 'Wallet', 'sub' => auth()->user()->name,
        ]);
    }
}
