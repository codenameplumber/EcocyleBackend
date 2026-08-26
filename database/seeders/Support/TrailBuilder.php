<?php

namespace Database\Seeders\Support;

use App\Enums\BatchStatus;
use App\Models\WasteBatch;
use Illuminate\Support\Facades\DB;

/**
 * Reconstructs `batch_events` for a batch the seeders stamped in one write.
 *
 * Production goes through `BatchStateMachine`, which writes an event per
 * transition. Seeding seven hundred batches that way takes minutes rather than
 * seconds, so the seeders stamp the whole lifecycle at once — and then call
 * this, so the audit trail still exists and still holds one row per transition.
 *
 * The trail is derived from the timestamps rather than invented: if a batch has
 * no `collected_at`, no COLLECTED event appears. That keeps seeded history from
 * claiming a step that never happened.
 */
final class TrailBuilder
{
    /** Column that records each state's arrival, in lifecycle order. */
    private const STAMPS = [
        'requested_at'  => BatchStatus::Requested,
        'available_at'  => BatchStatus::Available,
        'accepted_at'   => BatchStatus::Accepted,
        'collected_at'  => BatchStatus::Collected,
        'in_transit_at' => BatchStatus::InTransit,
        'received_at'   => BatchStatus::Received,
        'inspecting_at' => BatchStatus::Inspecting,
        'verified_at'   => BatchStatus::Verified,
        'rejected_at'   => BatchStatus::Rejected,
        'processing_at' => BatchStatus::Processing,
        'processed_at'  => BatchStatus::Processed,
    ];

    /** Rewrite the trail for one batch. Idempotent — safe to re-run a seeder. */
    public static function rebuild(WasteBatch $batch): int
    {
        DB::table('batch_events')->where('waste_batch_id', $batch->id)->delete();

        $rows = [];
        $from = null;

        foreach (self::STAMPS as $column => $status) {
            $at = $batch->{$column};

            if (! $at) {
                continue;
            }

            $rows[] = [
                'waste_batch_id' => $batch->id,
                'from_status' => $from,
                'to_status' => $status->value,
                'actor_id' => null,
                'actor_role' => self::actorFor($status),
                'meta' => null,
                'created_at' => $at,
            ];

            $from = $status->value;
        }

        if ($rows) {
            DB::table('batch_events')->insert($rows);
        }

        return count($rows);
    }

    /** Who moves a batch into each state, in the real operation. */
    private static function actorFor(BatchStatus $status): string
    {
        return match (true) {
            $status->step() <= 2 => 'household',
            $status->step() <= 5 => 'agent',
            default => 'facility',
        };
    }
}
