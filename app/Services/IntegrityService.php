<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Enums\FlagSeverity;
use App\Models\IntegrityFlag;
use App\Models\WasteBatch;

/**
 * Turns the declared-versus-verified gap, and three other checkable signals,
 * into something a human can review.
 *
 * The gap is not an error to clean up; it is the signal the business runs on,
 * so it is recorded whether or not anybody acts on it.
 *
 * Four detectors run. `SIGNALS` lists eight, because the other four describe
 * patterns the pilot's data cannot yet support — and saying so on the screen is
 * better than shipping four detectors that quietly never fire.
 */
class IntegrityService
{
    /**
     * Every signal the console reports on, and whether it actually runs.
     *
     * @var list<array{key: string, icon: string, name: string, detail: string, live: bool}>
     */
    public const SIGNALS = [
        ['key' => 'weight_variance', 'icon' => 'scale', 'name' => 'Weight variance',
            'detail' => 'Declared consistently apart from the gate scale', 'live' => true],
        ['key' => 'gps_mismatch', 'icon' => 'pin', 'name' => 'GPS mismatch',
            'detail' => 'Confirmation far from the registered coordinates', 'live' => true],
        ['key' => 'volume_spike', 'icon' => 'trend', 'name' => 'Volume spike',
            'detail' => 'Weight far outside the household baseline', 'live' => true],
        ['key' => 'repeat_rejection', 'icon' => 'ban', 'name' => 'Repeat rejection',
            'detail' => 'Three or more rejected batches in a row', 'live' => true],
        ['key' => 'impossible_travel', 'icon' => 'clock', 'name' => 'Impossible travel',
            'detail' => 'Two pickups too far apart, too close in time', 'live' => false],
        ['key' => 'duplicate_batch', 'icon' => 'package', 'name' => 'Duplicate batch',
            'detail' => 'Same waste tagged more than once', 'live' => false],
        ['key' => 'collusion', 'icon' => 'users', 'name' => 'Collusion pattern',
            'detail' => 'One agent servicing one household unusually often', 'live' => false],
        ['key' => 'frequency_anomaly', 'icon' => 'pulse', 'name' => 'Frequency anomaly',
            'detail' => 'Requests far more often than the pattern supports', 'live' => false],
    ];

    /** How far a pickup confirmation may sit from the registered address, in metres. */
    private const GPS_RADIUS_M = 300;

    /** A batch this many times the household's rolling baseline is a spike. */
    private const SPIKE_MULTIPLE = 2.5;

    /** Consecutive rejections before a household is flagged. */
    private const REJECTION_RUN = 3;

    /**
     * Run every live detector against one batch.
     *
     * Returns the most severe flag raised, or null. Each detector writes its own
     * row, so a batch can carry more than one — a heavy load dropped at the wrong
     * coordinates is two separate findings and reads better as two.
     */
    public function evaluateBatch(WasteBatch $batch): ?IntegrityFlag
    {
        $flags = array_filter([
            $this->weightVariance($batch),
            $this->gpsMismatch($batch),
            $this->volumeSpike($batch),
            $this->repeatRejection($batch),
        ]);

        if (! $flags) {
            return null;
        }

        usort($flags, fn ($a, $b) => $this->rank($b->severity) <=> $this->rank($a->severity));

        return $flags[0];
    }

    // -------------------------------------------------------------- detectors

    /** The declared weight against the one the scale actually recorded. */
    public function weightVariance(WasteBatch $batch): ?IntegrityFlag
    {
        if ($batch->variance_pct === null || ! $batch->agent_id) {
            return null;
        }

        $tolerance = (float) config('ecocycle.variance_tolerance');
        $abs = abs((float) $batch->variance_pct);

        if ($abs <= $tolerance) {
            return null;
        }

        return $this->raise($batch, 'weight_variance', match (true) {
            $abs >= 20 => FlagSeverity::High,
            $abs >= 10 => FlagSeverity::Medium,
            default    => FlagSeverity::Low,
        }, sprintf(
            'Declared %.1f kg, verified %.1f kg (%+.1f%%) on batch %s.',
            (float) $batch->collected_kg,
            (float) $batch->verified_kg,
            (float) $batch->variance_pct,
            $batch->code,
        ), [
            'declared' => $batch->collected_kg,
            'verified' => $batch->verified_kg,
            'variance' => $batch->variance_pct,
            'photo'    => $batch->photo_path,
        ]);
    }

    /**
     * Where the agent said the pickup happened, against where the household lives.
     *
     * A household legitimately moves house, and GPS in dense Lagos streets is not
     * surgical — which is why this raises a case for a human rather than holding
     * the money.
     */
    public function gpsMismatch(WasteBatch $batch): ?IntegrityFlag
    {
        $household = $batch->household;

        if (! $batch->pickup_lat || ! $batch->pickup_lng || ! $household?->lat || ! $household?->lng) {
            return null;
        }

        $metres = $this->haversine(
            (float) $batch->pickup_lat, (float) $batch->pickup_lng,
            (float) $household->lat, (float) $household->lng,
        ) * 1000;

        if ($metres <= self::GPS_RADIUS_M) {
            return null;
        }

        return $this->raise($batch, 'gps_mismatch', $metres > 2000 ? FlagSeverity::High : FlagSeverity::Medium, sprintf(
            'Pickup confirmed %s m from the registered address on batch %s, past the %d m radius.',
            number_format($metres, 0),
            $batch->code,
            self::GPS_RADIUS_M,
        ), [
            'distance_m' => round($metres),
            'radius_m'   => self::GPS_RADIUS_M,
            'pickup'     => [$batch->pickup_lat, $batch->pickup_lng],
            'registered' => [$household->lat, $household->lng],
        ]);
    }

    /** A load far outside what this household has ever produced. */
    public function volumeSpike(WasteBatch $batch): ?IntegrityFlag
    {
        if ($batch->verified_kg === null || ! $batch->household_id) {
            return null;
        }

        $baseline = (float) WasteBatch::where('household_id', $batch->household_id)
            ->whereKeyNot($batch->id)
            ->whereNotNull('verified_kg')
            ->latest('verified_at')
            ->limit(8)
            ->avg('verified_kg');

        // Fewer than a handful of prior batches and there is no baseline to be
        // outside of; a new household is not an anomaly.
        if ($baseline <= 0 || WasteBatch::where('household_id', $batch->household_id)
            ->whereNotNull('verified_kg')->count() < 5) {
            return null;
        }

        $multiple = (float) $batch->verified_kg / $baseline;

        if ($multiple < self::SPIKE_MULTIPLE) {
            return null;
        }

        return $this->raise($batch, 'volume_spike', $multiple >= 4 ? FlagSeverity::Medium : FlagSeverity::Low, sprintf(
            'Batch %s verified at %.1f kg against a %.1f kg rolling baseline — %.1f× the household norm.',
            $batch->code,
            (float) $batch->verified_kg,
            $baseline,
            $multiple,
        ), [
            'verified' => $batch->verified_kg,
            'baseline' => round($baseline, 1),
            'multiple' => round($multiple, 2),
        ]);
    }

    /** A household whose last several batches were all turned away. */
    public function repeatRejection(WasteBatch $batch): ?IntegrityFlag
    {
        if ($batch->status !== BatchStatus::Rejected || ! $batch->household_id) {
            return null;
        }

        $recent = WasteBatch::where('household_id', $batch->household_id)
            ->whereIn('status', [BatchStatus::Rejected->value, BatchStatus::Processed->value, BatchStatus::Verified->value])
            ->latest('inspecting_at')
            ->limit(self::REJECTION_RUN)
            ->pluck('status');

        if ($recent->count() < self::REJECTION_RUN
            || $recent->contains(fn ($s) => $s !== BatchStatus::Rejected)) {
            return null;
        }

        return $this->raise($batch, 'repeat_rejection', FlagSeverity::High, sprintf(
            'The last %d batches from this household were all rejected at inspection. The account should be paused pending a separation conversation.',
            self::REJECTION_RUN,
        ), ['run' => self::REJECTION_RUN]);
    }

    // ---------------------------------------------------------------- scoring

    /**
     * How many notional prior batches every agent is credited with, and at what
     * rejection rate, before their own record counts.
     *
     * Without this, one rejected batch on a brand-new agent's first pickup gives
     * a 100% rejection rate and a score of zero — which would bar them from work
     * forever on a sample of one. Ten notional batches at the network norm means
     * a real pattern still moves the score, but a single bad bag does not end a
     * livelihood.
     */
    private const SCORE_PRIOR_N = 10;
    private const SCORE_PRIOR_REJECT_RATE = 0.05;

    /**
     * Recompute an agent's score from their weighing history.
     *
     * Two inputs: how close their declarations run to the gate scale, and how
     * often their loads are rejected. Rejection is only partly theirs -- a
     * contaminated bag is the household's doing -- which is why it is weighted
     * but smoothed rather than treated as proof of anything.
     */
    public function refreshAgentScore(\App\Models\Agent $agent): float
    {
        $accuracy = $agent->weightAccuracy();

        $rejects = $agent->batches()->where('status', 'REJECTED')->count();
        $total   = $agent->batches()->count();

        $smoothed = ($rejects + self::SCORE_PRIOR_N * self::SCORE_PRIOR_REJECT_RATE)
            / ($total + self::SCORE_PRIOR_N);

        $score = max(0, min(100, $accuracy - ($smoothed * 100 * 1.5)));
        $agent->update(['integrity_score' => round($score, 2)]);

        return $score;
    }

    /** The thresholds the console displays, so the screen cannot drift from the code. */
    public function thresholds(): array
    {
        return [
            'variance_tolerance' => (float) config('ecocycle.variance_tolerance'),
            'gps_radius_m'       => self::GPS_RADIUS_M,
            'spike_multiple'     => self::SPIKE_MULTIPLE,
            'rejection_run'      => self::REJECTION_RUN,
        ];
    }

    // ----------------------------------------------------------------- shared

    /** @param  array<string, mixed>  $evidence */
    private function raise(WasteBatch $batch, string $kind, FlagSeverity $severity, string $detail, array $evidence): IntegrityFlag
    {
        return IntegrityFlag::firstOrCreate(
            ['waste_batch_id' => $batch->id, 'kind' => $kind],
            [
                'agent_id'     => $batch->agent_id,
                'household_id' => $batch->household_id,
                'severity'     => $severity,
                'detail'       => $detail,
                'evidence'     => $evidence,
                'status'       => 'open',
            ]
        );
    }

    private function rank(FlagSeverity $s): int
    {
        return match ($s) {
            FlagSeverity::High => 3,
            FlagSeverity::Medium => 2,
            default => 1,
        };
    }

    /** Great-circle distance in kilometres. */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
