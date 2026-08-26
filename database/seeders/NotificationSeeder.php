<?php

namespace Database\Seeders;

use App\Enums\BatchStatus;
use App\Models\Payout;
use App\Models\WasteBatch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Back-fills the notification feed for history that was seeded rather than lived.
 *
 * Rows are written straight into `notifications` and deliberately NOT dispatched
 * through the Notification classes: those send mail as well, and re-sending nine
 * hundred emails for events that happened up to ninety days ago would be wrong.
 * The payload shape is copied from each notification's toDatabase(), so the feed
 * renders these identically to ones a live event produced.
 */
class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('notifications')->delete();

        $rows = [];

        $batches = WasteBatch::with(['household.user', 'agent.user'])
            ->whereNotNull('agent_id')
            ->latest('requested_at')
            ->limit(400)
            ->get();

        foreach ($batches as $b) {
            $householdUser = $b->household?->user;
            $agentUser = $b->agent?->user;

            if ($b->accepted_at && $householdUser) {
                $rows[] = $this->row($householdUser, \App\Notifications\JobAccepted::class, $b->accepted_at, [
                    'kind' => 'job_accepted',
                    'icon' => 'truck',
                    'title' => ($agentUser?->name ?? 'An agent') . ' accepted your request',
                    'body' => 'Batch ' . $b->code,
                    'batch' => $b->code,
                    'batch_id' => $b->id,
                ]);
            }

            if ($b->collected_at && $householdUser) {
                $rows[] = $this->row($householdUser, \App\Notifications\BatchCollected::class, $b->collected_at, [
                    'kind' => 'batch_collected',
                    'icon' => 'scan',
                    'title' => 'Batch tagged at pickup',
                    'body' => $b->code . ' · declared ' . number_format((float) $b->collected_kg, 1) . ' kg',
                    'batch' => $b->code,
                    'batch_id' => $b->id,
                ]);
            }

            if ($b->status === BatchStatus::Rejected && $b->inspected_at) {
                foreach (array_filter([$householdUser, $agentUser]) as $u) {
                    $rows[] = $this->row($u, \App\Notifications\BatchRejected::class, $b->inspected_at, [
                        'kind' => 'batch_rejected',
                        'icon' => 'alert',
                        'title' => 'Batch rejected at inspection',
                        'body' => $b->rejection_reason ?: 'Contamination beyond the accepted threshold',
                        'batch' => $b->code,
                        'batch_id' => $b->id,
                    ]);
                }
                continue;
            }

            if ($b->verified_kg !== null && $b->verified_at) {
                foreach (array_filter([$householdUser, $agentUser]) as $u) {
                    $isHousehold = $u->is($householdUser);
                    $share = (float) ($isHousehold ? $b->household_share : $b->agent_share);

                    $rows[] = $this->row($u, \App\Notifications\BatchVerified::class, $b->verified_at, [
                        'kind' => 'batch_verified',
                        'icon' => 'shield',
                        'title' => 'Batch verified at ' . number_format((float) $b->verified_kg, 1) . ' kg',
                        'body' => ($b->grade ? "Grade {$b->grade} · " : '')
                            . 'NGN ' . number_format($share, 2) . ' credited',
                        'batch' => $b->code,
                        'batch_id' => $b->id,
                    ]);
                }
            }
        }

        foreach (Payout::with('party')->get() as $p) {
            $user = $p->party?->user;
            if (! $user) {
                continue;
            }

            $paid = $p->status === 'paid';
            $rows[] = $this->row($user, \App\Notifications\BatchVerified::class, $p->updated_at, [
                'kind' => $paid ? 'payout_paid' : 'payout_opened',
                'icon' => $paid ? 'cash' : 'wallet',
                'title' => $paid
                    ? 'Payout of NGN ' . number_format((float) $p->amount, 2) . ' processed'
                    : 'Payout of NGN ' . number_format((float) $p->amount, 2) . ' opened',
                'body' => $paid ? ($p->reference ?: 'Bank transfer') : 'Awaiting the next payment run',
                'payout' => $p->reference,
            ]);
        }

        // Newest first, then mark only the most recent few per user unread --
        // an inbox where everything is unread reads as broken, not as busy.
        usort($rows, fn ($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));

        $seen = [];
        foreach ($rows as $i => $r) {
            $n = $seen[$r['notifiable_id']] = ($seen[$r['notifiable_id']] ?? 0) + 1;
            if ($n > 2) {
                $rows[$i]['read_at'] = $r['created_at'];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('notifications')->insert($chunk);
        }

        $unread = count(array_filter($rows, fn ($r) => $r['read_at'] === null));
        $this->command->info('  ' . number_format(count($rows)) . ' notifications (' . $unread . ' unread)');
    }

    /** @param  array<string, mixed>  $data */
    private function row(object $user, string $type, mixed $at, array $data): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'notifiable_type' => $user::class,
            'notifiable_id' => $user->id,
            'data' => json_encode($data),
            'read_at' => null,
            'created_at' => $at,
            'updated_at' => $at,
        ];
    }
}
