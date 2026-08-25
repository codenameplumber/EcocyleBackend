<?php

namespace App\Livewire\Console;

use App\Models\Agent;
use App\Models\Household;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Services\LedgerService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.console')]
class Payments extends Component
{
    public function runPayouts(LedgerService $ledger): void
    {
        $made = 0;

        foreach (Household::all() as $h) {
            if ($ledger->openPayout($h)) { $made++; }
        }
        foreach (Agent::all() as $a) {
            if ($ledger->openPayout($a)) { $made++; }
        }

        $this->dispatch('toast', message: $made
            ? "{$made} payouts queued"
            : 'Nothing outstanding to pay out', kind: $made ? 'ok' : 'warn');
    }

    public function markPaid(int $id, LedgerService $ledger): void
    {
        $payout = Payout::find($id);
        if ($payout && $payout->status !== 'paid') {
            $ledger->markPaid($payout, auth()->id());
            $this->dispatch('toast', message: "Payout {$payout->reference} marked paid", kind: 'ok');
        }
    }

    /** Bank-transfer file. Simpler and safer than a payments API at pilot scale. */
    public function exportCsv()
    {
        $rows = Payout::with('party')->where('status', 'pending')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['reference', 'party', 'type', 'amount_ngn', 'method', 'created']);
            foreach ($rows as $p) {
                fputcsv($out, [
                    $p->reference,
                    $p->party?->name() ?? '—',
                    class_basename($p->party_type),
                    number_format((float) $p->amount, 2, '.', ''),
                    $p->method,
                    $p->created_at->toDateString(),
                ]);
            }
            fclose($out);
        }, 'ecocycle-payouts-' . now()->toDateString() . '.csv');
    }

    public function render()
    {
        $month = now()->startOfMonth();

        return view('livewire.console.payments', [
            'settledMonth' => (float) LedgerEntry::where('direction', 'credit')
                ->where('created_at', '>=', $month)->sum('amount'),
            'pendingTotal' => (float) Payout::where('status', 'pending')->sum('amount'),
            'paidTotal'    => (float) Payout::where('status', 'paid')->sum('amount'),
            'unpaidCredits'=> (float) LedgerEntry::where('direction', 'credit')
                ->whereNull('payout_id')->sum('amount'),
            'payouts'      => Payout::with('party')->latest()->limit(25)->get(),
            'recent'       => LedgerEntry::with(['party', 'batch'])->latest('created_at')->limit(12)->get(),
        ])->layoutData(['page' => 'payments', 'heading' => 'Settlements']);
    }
}
