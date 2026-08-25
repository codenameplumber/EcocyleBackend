<?php

namespace App\Services;

use App\Enums\LedgerDirection;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\WasteBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Append-only money.
 *
 * Nothing in this class ever updates an existing row's amount. A mistake is
 * corrected by writing a reversing entry, so the history stays complete and a
 * balance is always reproducible from the entries that justify it.
 */
class LedgerService
{
    /**
     * Write one entry, at most once.
     *
     * The idempotency key is what makes a double-clicked Accept button, a
     * retried queue job or a replayed webhook harmless.
     */
    public function record(
        Model $party,
        LedgerDirection $direction,
        float $amount,
        string $kind,
        string $idempotencyKey,
        ?WasteBatch $batch = null,
        ?Payout $payout = null,
        ?string $memo = null,
    ): LedgerEntry {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Ledger amounts are always positive; direction carries the sign.');
        }

        return LedgerEntry::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'waste_batch_id' => $batch?->id,
                'payout_id'      => $payout?->id,
                'party_type'     => $party->getMorphClass(),
                'party_id'       => $party->getKey(),
                'direction'      => $direction,
                'amount'         => round($amount, 2),
                'kind'           => $kind,
                'memo'           => $memo,
                'created_at'     => now(),
            ]
        );
    }

    public function credit(Model $party, float $amount, string $kind, string $key, ?WasteBatch $batch = null, ?string $memo = null): LedgerEntry
    {
        return $this->record($party, LedgerDirection::Credit, $amount, $kind, $key, $batch, null, $memo);
    }

    public function debit(Model $party, float $amount, string $kind, string $key, ?Payout $payout = null, ?string $memo = null): LedgerEntry
    {
        return $this->record($party, LedgerDirection::Debit, $amount, $kind, $key, null, $payout, $memo);
    }

    /**
     * Collect everything a party is owed into a single payout and debit it.
     * Returns null when there is nothing outstanding.
     */
    public function openPayout(Model $party, string $method = 'Bank transfer'): ?Payout
    {
        return DB::transaction(function () use ($party, $method) {
            $entries = LedgerEntry::where('party_type', $party->getMorphClass())
                ->where('party_id', $party->getKey())
                ->where('direction', LedgerDirection::Credit->value)
                ->whereNull('payout_id')
                ->lockForUpdate()
                ->get();

            if ($entries->isEmpty()) {
                return null;
            }

            $amount = round((float) $entries->sum('amount'), 2);

            $payout = Payout::create([
                'reference'  => 'PY-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
                'party_type' => $party->getMorphClass(),
                'party_id'   => $party->getKey(),
                'amount'     => $amount,
                'method'     => $method,
                'status'     => 'pending',
            ]);

            // Attaching the credits stops them being paid out a second time.
            LedgerEntry::whereIn('id', $entries->pluck('id'))->update(['payout_id' => $payout->id]);

            $this->debit(
                $party,
                $amount,
                'payout',
                'payout:' . $payout->id,
                $payout,
                'Payout ' . $payout->reference,
            );

            return $payout;
        });
    }

    public function markPaid(Payout $payout, ?int $actionedBy = null): Payout
    {
        $payout->update([
            'status'      => 'paid',
            'paid_at'     => now(),
            'actioned_by' => $actionedBy,
        ]);

        return $payout;
    }

    /** A party's balance, straight from the entries. */
    public function balanceFor(Model $party): float
    {
        return (float) LedgerEntry::where('party_type', $party->getMorphClass())
            ->where('party_id', $party->getKey())
            ->selectRaw("COALESCE(SUM(CASE WHEN direction='credit' THEN amount ELSE -amount END),0) AS t")
            ->value('t');
    }
}
