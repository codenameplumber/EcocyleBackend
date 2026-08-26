<?php

namespace App\Console\Commands;

use App\Enums\BatchStatus;
use App\Models\Agent;
use App\Models\Household;
use App\Models\LedgerEntry;
use App\Models\WasteBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Whole-database invariants.
 *
 * `ecocycle:test` proves the rules hold for a batch it drives itself. This asks
 * the harder question: do they hold across every row already in the database —
 * including rows written months ago by code that has since changed?
 *
 * Read-only. Safe to run against production.
 */
class DataAudit extends Command
{
    protected $signature = 'ecocycle:audit';

    protected $description = 'Check every money, lifecycle and referential invariant across the whole database';

    private int $failed = 0;

    public function handle(): int
    {
        $this->newLine();

        $this->group('Money');
        $this->money();

        $this->group('Settlement');
        $this->settlement();

        $this->group('Payouts');
        $this->payouts();

        $this->group('Lifecycle');
        $this->lifecycle();

        $this->group('Processing');
        $this->processing();

        $this->group('References');
        $this->references();

        $this->newLine();
        $this->line('  ' . str_repeat('=', 62));

        if ($this->failed === 0) {
            $this->line('  <fg=green;options=bold>Every invariant holds across the whole database.</>');

            return self::SUCCESS;
        }

        $this->line("  <fg=red;options=bold>{$this->failed} invariant(s) broken.</>");

        return self::FAILURE;
    }

    // ----------------------------------------------------------------- money

    private function money(): void
    {
        $credits = (float) LedgerEntry::where('direction', 'credit')->sum('amount');
        $debits = (float) LedgerEntry::where('direction', 'debit')->sum('amount');
        $balances = Household::all()->sum(fn ($h) => $h->balance())
            + Agent::all()->sum(fn ($a) => $a->balance());

        // The one that matters: if the ledger and the balances derived from it
        // ever disagree, the append-only guarantee has been broken somewhere.
        $this->check(
            'ledger net equals the sum of every party balance',
            abs(($credits - $debits) - $balances) < 0.01,
            sprintf('NGN %s vs NGN %s', number_format($credits - $debits, 2), number_format($balances, 2)),
        );

        $this->check('no negative balances anywhere',
            Household::all()->every(fn ($h) => $h->balance() >= -0.01)
            && Agent::all()->every(fn ($a) => $a->balance() >= -0.01));

        $dupes = LedgerEntry::selectRaw('idempotency_key, COUNT(*) c')
            ->groupBy('idempotency_key')->havingRaw('COUNT(*) > 1')->count();
        $this->check('every ledger entry has a unique idempotency key', $dupes === 0,
            $dupes ? "{$dupes} duplicated" : '');

        $this->check('no ledger entry has a zero or negative amount',
            LedgerEntry::where('amount', '<=', 0)->count() === 0);
    }

    // ------------------------------------------------------------ settlement

    private function settlement(): void
    {
        $mismatch = WasteBatch::whereNotNull('value_naira')
            ->whereRaw('ABS(household_share + agent_share - value_naira) > 0.011')->count();
        $this->check('household share + agent share reconstitutes batch value', $mismatch === 0,
            $mismatch ? "{$mismatch} batches off" : '');

        $unverified = WasteBatch::whereNull('verified_kg')->whereNotNull('value_naira')->count();
        $this->check('nothing was valued without a verified weight', $unverified === 0,
            $unverified ? "{$unverified} batches" : '');

        $rejectedPaid = WasteBatch::where('status', BatchStatus::Rejected->value)
            ->whereHas('ledgerEntries')->count();
        $this->check('no rejected batch produced a ledger entry', $rejectedPaid === 0,
            $rejectedPaid ? "{$rejectedPaid} batches" : '');

        $wrongRate = WasteBatch::whereNotNull('value_naira')
            ->whereRaw('ABS(verified_kg * rate_applied - value_naira) > 0.011')->count();
        $this->check('every batch value equals verified_kg × rate_applied', $wrongRate === 0,
            $wrongRate ? "{$wrongRate} batches" : '');
    }

    // --------------------------------------------------------------- payouts

    private function payouts(): void
    {
        $over = DB::table('payouts')
            ->leftJoin('ledger_entries', 'ledger_entries.payout_id', '=', 'payouts.id')
            ->groupBy('payouts.id', 'payouts.amount')
            ->selectRaw('payouts.id, payouts.amount,
                COALESCE(SUM(CASE WHEN ledger_entries.direction = "credit" THEN ledger_entries.amount ELSE 0 END), 0) AS attached')
            ->get()
            ->filter(fn ($r) => abs((float) $r->amount - (float) $r->attached) > 0.011)
            ->count();
        $this->check('every payout equals the credits attached to it', $over === 0,
            $over ? "{$over} payouts" : '');

        $orphan = LedgerEntry::where('direction', 'debit')->where('kind', 'payout')
            ->whereDoesntHave('payout')->count();
        $this->check('every payout debit points at a real payout', $orphan === 0,
            $orphan ? "{$orphan} entries" : '');
    }

    // ------------------------------------------------------------- lifecycle

    private function lifecycle(): void
    {
        $noTrail = WasteBatch::doesntHave('events')->count();
        $this->check('every batch carries an audit trail', $noTrail === 0,
            $noTrail ? "{$noTrail} without" : '');

        $unknown = WasteBatch::whereNotIn('status', array_column(BatchStatus::cases(), 'value'))->count();
        $this->check('no batch is in an unknown state', $unknown === 0);

        $noAgent = WasteBatch::whereNull('agent_id')
            ->whereNotIn('status', [BatchStatus::Requested->value, BatchStatus::Available->value])->count();
        $this->check('nothing past AVAILABLE is missing an agent', $noAgent === 0,
            $noAgent ? "{$noAgent} batches" : '');

        $noWeight = WasteBatch::whereIn('status', [
            BatchStatus::Verified->value, BatchStatus::Processing->value, BatchStatus::Processed->value,
        ])->whereNull('verified_kg')->count();
        $this->check('everything past inspection has a verified weight', $noWeight === 0,
            $noWeight ? "{$noWeight} batches" : '');
    }

    // ------------------------------------------------------------ processing

    private function processing(): void
    {
        $overYield = DB::table('processing_runs')
            ->whereNotNull('output_kg')->whereRaw('output_kg > input_kg')->count();
        $this->check('no line produced more than it was fed', $overYield === 0,
            $overYield ? "{$overYield} runs" : '');

        $runInput = (float) DB::table('processing_runs')->sum('input_kg');
        $batchInput = (float) WasteBatch::whereNotNull('processing_run_id')->sum('verified_kg');
        $this->check('run input equals the batches attached to those runs',
            abs($runInput - $batchInput) < 1.0,
            sprintf('%s kg vs %s kg', number_format($runInput, 1), number_format($batchInput, 1)));
    }

    // ------------------------------------------------------------ references

    private function references(): void
    {
        $this->check('no batch points at a delivery that does not exist',
            WasteBatch::whereNotNull('delivery_id')->whereDoesntHave('delivery')->count() === 0);

        $flagOrphan = DB::table('integrity_flags')
            ->leftJoin('waste_batches', 'waste_batches.id', '=', 'integrity_flags.waste_batch_id')
            ->whereNull('waste_batches.id')->count();
        $this->check('every integrity case points at a real batch', $flagOrphan === 0);

        $this->check('agent scores are inside 0-100',
            Agent::whereRaw('integrity_score < 0 OR integrity_score > 100')->count() === 0);

        $noParty = DB::table('users')
            ->whereIn('role', ['household', 'agent'])
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('households')
                ->whereColumn('households.user_id', 'users.id'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('agents')
                ->whereColumn('agents.user_id', 'users.id'))
            ->count();
        $this->check('every household/agent user has a party record', $noParty === 0,
            $noParty ? "{$noParty} users" : '');
    }

    // ---------------------------------------------------------------- output

    private function group(string $name): void
    {
        $this->newLine();
        $this->line("  <options=bold>{$name}</>");
    }

    private function check(string $label, bool $ok, string $detail = ''): void
    {
        if (! $ok) {
            $this->failed++;
        }

        $this->line(sprintf('  <fg=%s>%s</> %-52s <fg=gray>%s</>',
            $ok ? 'green' : 'red', $ok ? 'PASS' : 'FAIL', $label, $detail));
    }
}
