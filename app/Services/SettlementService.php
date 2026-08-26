<?php

namespace App\Services;

use App\Models\WasteBatch;
use App\Models\WasteCategory;
use Illuminate\Support\Facades\DB;

/**
 * The 60/40 split, computed once, from the scale.
 *
 * Everything here takes verified_kg. There is deliberately no code path that
 * values a batch from an estimate or a declaration -- that is the product's
 * central promise, so it is enforced in one place rather than trusted to
 * twenty screens.
 */
class SettlementService
{
    public function __construct(private LedgerService $ledger) {}

    /**
     * What a given weight would be worth, without writing anything.
     * Used live by the inspection bay as the inspector types.
     *
     * @return array{value: float, household: float, agent: float, rate: float, variance: ?float}
     */
    public function preview(WasteBatch $batch, ?float $verifiedKg, ?WasteCategory $category = null): array
    {
        $category ??= $batch->effectiveCategory();
        $rate = (float) ($category?->rate_per_kg ?? 0);
        $kg = max(0, (float) $verifiedKg);

        $value = round($kg * $rate, 2);
        $householdShare = round($value * $this->householdShare(), 2);
        // The agent takes the remainder, so rounding can never create or lose a naira.
        $agentShare = round($value - $householdShare, 2);

        return [
            'rate'      => $rate,
            'value'     => $value,
            'household' => $householdShare,
            'agent'     => $agentShare,
            'variance'  => $this->variance($batch->collected_kg, $kg),
        ];
    }

    /**
     * Freeze the settlement onto the batch and write the ledger entries.
     * Idempotent: calling it twice for the same batch credits nobody twice.
     */
    public function settle(WasteBatch $batch): WasteBatch
    {
        if (! $batch->status->isSettleable()) {
            throw new \DomainException("Batch {$batch->code} is {$batch->status->value}; only verified batches settle.");
        }

        if ($batch->verified_kg === null) {
            throw new \DomainException("Batch {$batch->code} has no verified weight.");
        }

        return DB::transaction(function () use ($batch) {
            $category = $batch->effectiveCategory();
            $figures  = $this->preview($batch, $batch->verified_kg, $category);

            $batch->forceFill([
                'rate_applied'    => $figures['rate'],
                'value_naira'     => $figures['value'],
                'household_share' => $figures['household'],
                'agent_share'     => $figures['agent'],
                'variance_pct'    => $figures['variance'],
            ])->save();

            if ($batch->household && $figures['household'] > 0) {
                $this->ledger->credit(
                    $batch->household,
                    $figures['household'],
                    'settlement',
                    "settle:hh:{$batch->id}",
                    $batch,
                    "Batch {$batch->code} verified at {$batch->verified_kg} kg",
                );
            }

            if ($batch->agent && $figures['agent'] > 0) {
                $this->ledger->credit(
                    $batch->agent,
                    $figures['agent'],
                    'settlement',
                    "settle:ag:{$batch->id}",
                    $batch,
                    "Batch {$batch->code} verified at {$batch->verified_kg} kg",
                );
            }

            return $batch->refresh();
        });
    }

    /**
     * Apportion one weighbridge reading across the batches on the vehicle.
     *
     * An agent crosses the scale once with several batches on board, so the
     * net weight is split in proportion to what was declared per batch. This
     * is the rule the README flagged as unresolved; it is resolved here, and
     * it is deliberately the only rule, because two rules would be a dispute.
     */
    public function apportionDelivery(\App\Models\Delivery $delivery): void
    {
        // Order is pinned, not incidental. The last batch in this list absorbs
        // the rounding remainder, so which batch that is must not depend on
        // whatever order the database felt like returning -- and the gate's
        // preview has to be able to reproduce it exactly.
        $batches = $delivery->batches()->orderBy('id')->get();
        $declaredTotal = (float) $batches->sum('collected_kg');
        $net = (float) $delivery->net_kg;

        if ($batches->isEmpty() || $net <= 0) {
            return;
        }

        // If nothing was declared we can only split evenly.
        $running = 0.0;
        $last = $batches->count() - 1;

        foreach ($batches->values() as $i => $batch) {
            if ($i === $last) {
                $share = round($net - $running, 2);      // absorb rounding on the last one
            } else {
                $ratio = $declaredTotal > 0
                    ? ((float) $batch->collected_kg / $declaredTotal)
                    : (1 / $batches->count());
                $share = round($net * $ratio, 2);
                $running += $share;
            }

            $batch->forceFill([
                'verified_kg'  => $share,
                'variance_pct' => $this->variance($batch->collected_kg, $share),
            ])->save();
        }
    }

    /** Percentage the verified weight differs from what the agent declared. */
    public function variance(?float $declared, ?float $verified): ?float
    {
        if (! $declared || $verified === null) {
            return null;
        }

        return round((($verified - $declared) / $declared) * 100, 2);
    }

    private function householdShare(): float
    {
        $hh = (float) config('ecocycle.household_share');
        $ag = (float) config('ecocycle.agent_share');

        if (abs(($hh + $ag) - 1.0) > 0.0001) {
            throw new \DomainException('household_share + agent_share must equal 1.0');
        }

        return $hh;
    }
}
