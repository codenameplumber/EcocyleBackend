<?php

namespace App\Livewire\Console;

use App\Models\Agent;
use App\Models\Household;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Services\LedgerService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.console')]
class Payments extends Component
{
    use WithPagination;

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

    public string $status = 'all';

    public function setStatus(string $s): void
    {
        $this->status = $s;
        $this->resetPage();
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
            'payouts'      => Payout::with('party.user')
                ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
                ->latest()
                ->paginate(15),
            'settlementSeries' => $this->settlementSeries(),
            'toHouseholds' => (float) LedgerEntry::where('direction', 'credit')
                ->where('party_type', \App\Models\Household::class)->sum('amount'),
            'toAgents'     => (float) LedgerEntry::where('direction', 'credit')
                ->where('party_type', \App\Models\Agent::class)->sum('amount'),
            'held'         => (float) Payout::where('status', 'held')->sum('amount'),
            'splitBar'     => [
                ['label' => 'Households', 'value' => (float) LedgerEntry::where('direction', 'credit')
                    ->where('party_type', \App\Models\Household::class)->sum('amount'), 'color' => 'var(--primary)'],
                ['label' => 'Agents', 'value' => (float) LedgerEntry::where('direction', 'credit')
                    ->where('party_type', \App\Models\Agent::class)->sum('amount'), 'color' => 'var(--c2)'],
            ],
            'recent'       => LedgerEntry::with(['party', 'batch'])->latest('created_at')->limit(12)->get(),
        ])->layoutData(['page' => 'payments', 'heading' => 'Settlements']);
    }

    /**
     * Credited value per day over the last fortnight, plus the change against
     * the fortnight before it.
     *
     * Off ledger_entries rather than batches: the ledger is what actually moved,
     * and a batch that was settled then reversed should not still show up here.
     *
     * @return array{labels: list<string>, values: list<float>, delta: ?float}
     */
    private function settlementSeries(): array
    {
        $rows = LedgerEntry::where('direction', 'credit')
            ->where('created_at', '>=', now()->subDays(14)->startOfDay())
            ->groupByRaw('DATE(created_at)')
            ->selectRaw('DATE(created_at) as d, SUM(amount) as total')
            ->pluck('total', 'd');

        $labels = $values = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $labels[] = $day->format('j M');
            $values[] = round((float) ($rows[$day->toDateString()] ?? 0), 2);
        }

        $prior = (float) LedgerEntry::where('direction', 'credit')
            ->whereBetween('created_at', [now()->subDays(28)->startOfDay(), now()->subDays(14)->startOfDay()])
            ->sum('amount');
        $current = array_sum($values);

        return [
            'labels' => $labels,
            'values' => $values,
            'delta' => $prior > 0 ? ($current - $prior) / $prior * 100 : null,
        ];
    }
}
