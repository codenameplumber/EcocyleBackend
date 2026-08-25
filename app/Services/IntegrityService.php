<?php

namespace App\Services;

use App\Enums\FlagSeverity;
use App\Models\IntegrityFlag;
use App\Models\WasteBatch;

/**
 * Turns the declared-versus-verified gap into something a human can review.
 *
 * The gap is not an error to clean up; it is the signal the business runs on,
 * so it is recorded whether or not anybody acts on it.
 */
class IntegrityService
{
    public function evaluateBatch(WasteBatch $batch): ?IntegrityFlag
    {
        if ($batch->variance_pct === null || ! $batch->agent) {
            return null;
        }

        $tolerance = (float) config('ecocycle.variance_tolerance');
        $abs = abs($batch->variance_pct);

        if ($abs <= $tolerance) {
            return null;
        }

        $severity = match (true) {
            $abs >= 20 => FlagSeverity::High,
            $abs >= 10 => FlagSeverity::Medium,
            default    => FlagSeverity::Low,
        };

        return IntegrityFlag::firstOrCreate(
            ['waste_batch_id' => $batch->id, 'kind' => 'weight_variance'],
            [
                'agent_id'     => $batch->agent_id,
                'household_id' => $batch->household_id,
                'severity'     => $severity,
                'detail'       => sprintf(
                    'Declared %.1f kg, verified %.1f kg (%+.1f%%) on batch %s.',
                    $batch->collected_kg,
                    $batch->verified_kg,
                    $batch->variance_pct,
                    $batch->code,
                ),
                'evidence' => [
                    'declared' => $batch->collected_kg,
                    'verified' => $batch->verified_kg,
                    'variance' => $batch->variance_pct,
                    'photo'    => $batch->photo_path,
                ],
                'status' => 'open',
            ]
        );
    }

    /** Recompute an agent's score from their recent weighing history. */
    public function refreshAgentScore(\App\Models\Agent $agent): float
    {
        $accuracy = $agent->weightAccuracy();
        $rejects  = $agent->batches()->where('status', 'REJECTED')->count();
        $total    = max(1, $agent->batches()->count());
        $rejectRate = ($rejects / $total) * 100;

        $score = max(0, min(100, $accuracy - ($rejectRate * 1.5)));
        $agent->update(['integrity_score' => round($score, 2)]);

        return $score;
    }
}
