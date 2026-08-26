<?php

namespace App\Concerns;

use App\Models\LedgerEntry;
use App\Models\Payout;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A party that money can be owed to.
 *
 * There is no balance column. The balance is the signed sum of an append-only
 * ledger, which means it can always be reconstructed and can never silently
 * drift away from the entries that justify it.
 */
trait HasLedger
{
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'party');
    }

    /** Payouts opened against this party, paid or not. */
    public function payouts(): MorphMany
    {
        return $this->morphMany(Payout::class, 'party');
    }

    /** Everything credited, minus everything paid out. */
    public function balance(): float
    {
        return (float) $this->ledgerEntries()
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) AS total")
            ->value('total');
    }

    /** Credited but not yet included in a paid payout. */
    public function pendingAmount(): float
    {
        return (float) $this->ledgerEntries()
            ->where('direction', 'credit')
            ->whereNull('payout_id')
            ->sum('amount');
    }

    public function totalEarned(): float
    {
        return (float) $this->ledgerEntries()->where('direction', 'credit')->sum('amount');
    }

    public function totalPaidOut(): float
    {
        return (float) $this->ledgerEntries()->where('direction', 'debit')->sum('amount');
    }
}
