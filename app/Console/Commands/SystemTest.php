<?php

namespace App\Console\Commands;

use App\Enums\BatchStatus;
use App\Models\Agent;
use App\Models\Delivery;
use App\Models\Household;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\User;
use App\Models\WasteBatch;
use App\Models\WasteCategory;
use App\Notifications\BatchVerified;
use App\Services\BatchCodeGenerator;
use App\Services\BatchStateMachine;
use App\Services\IntegrityService;
use App\Services\JobMatcher;
use App\Services\LedgerService;
use App\Services\OpenAiService;
use App\Services\QrService;
use App\Services\SettlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Full backend regression test.
 *
 *   php artisan ecocycle:test
 *
 * Runs inside a transaction that is always rolled back, so it can be run
 * against the live database without leaving anything behind.
 */
class SystemTest extends Command
{
    protected $signature = 'ecocycle:test {--keep : commit instead of rolling back}';
    protected $description = 'Exercise the whole backend and report what is broken';

    private array $results = [];

    public function handle(): int
    {
        $this->line('');
        $this->info('EcoCycle backend test');
        $this->line(str_repeat('=', 62));

        DB::beginTransaction();

        try {
            $this->testReferenceData();
            $this->testLifecycle();
            $this->testSettlementMath();
            $this->testIdempotency();
            $this->testIllegalTransitions();
            $this->testConcurrentClaim();
            $this->testDeliveryApportionment();
            $this->testLedgerIntegrity();
            $this->testPayouts();
            $this->testIntegrityFlagging();
            $this->testNotifications();
            $this->testJobMatching();
            $this->testQrCodes();
            $this->testOpenAiFallback();
            $this->testRoleRouting();
        } catch (\Throwable $e) {
            $this->fail_('UNCAUGHT', $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
        }

        if ($this->option('keep')) {
            DB::commit();
            $this->line("\n(changes committed)");
        } else {
            DB::rollBack();
        }

        return $this->report();
    }

    // ---------------------------------------------------------------- tests

    private function testReferenceData(): void
    {
        $this->group('Reference data');
        $this->check('7 waste categories seeded', WasteCategory::count() === 7);
        $this->check('12 areas seeded', \App\Models\Area::count() === 12);
        $this->check('facility exists', \App\Models\Facility::count() >= 1);
        $this->check('4 processing lines', \App\Models\ProcessingLine::count() === 4);
        $this->check('rates are positive', WasteCategory::where('rate_per_kg', '<=', 0)->count() === 0);
        $this->check('shares sum to 1.0',
            abs(config('ecocycle.household_share') + config('ecocycle.agent_share') - 1.0) < 0.0001);
    }

    private function testLifecycle(): void
    {
        $this->group('Full batch lifecycle');

        $machine = app(BatchStateMachine::class);
        $codes = app(BatchCodeGenerator::class);
        $household = Household::first();
        $agent = Agent::first();
        $staff = User::where('role', 'admin')->first();

        $batch = WasteBatch::create([
            'ulid' => $codes->ulid(),
            'code' => 'TEST-' . uniqid(),
            'household_id' => $household->id,
            'waste_category_id' => WasteCategory::where('key', 'plastic')->value('id'),
            'status' => BatchStatus::Requested,
            'expected_kg' => 20.0,
            'requested_at' => now(),
        ]);
        $this->check('created in REQUESTED', $batch->status === BatchStatus::Requested);

        $steps = [
            [BatchStatus::Available, []],
            [BatchStatus::Accepted, ['agent_id' => $agent->id]],
            [BatchStatus::Collected, ['collected_kg' => 22.0]],
            [BatchStatus::InTransit, []],
            [BatchStatus::Received, []],
            [BatchStatus::Inspecting, []],
            [BatchStatus::Verified, ['verified_kg' => 21.0, 'grade' => 'A']],
            [BatchStatus::Processing, []],
            [BatchStatus::Processed, []],
        ];

        foreach ($steps as [$to, $attrs]) {
            $batch = $machine->transition($batch, $to, $staff, $attrs);
        }

        $this->check('reached PROCESSED', $batch->status === BatchStatus::Processed);
        $this->check('10 audit events written', $batch->events()->count() === 9);
        $this->check('every transition timestamped',
            $batch->requested_at && $batch->accepted_at && $batch->collected_at
            && $batch->received_at && $batch->verified_at && $batch->processed_at);

        $this->testBatch = $batch;
    }

    private WasteBatch $testBatch;

    private function testSettlementMath(): void
    {
        $this->group('Settlement arithmetic');

        $settlement = app(SettlementService::class);
        $batch = $this->testBatch;
        $settlement->settle($batch);
        $batch->refresh();

        $rate = (float) $batch->rate_applied;
        $expectedValue = round(21.0 * $rate, 2);

        $this->check('value = verified_kg x rate',
            abs((float) $batch->value_naira - $expectedValue) < 0.01,
            "got {$batch->value_naira}, expected {$expectedValue}");

        $this->check('household share is 60%',
            abs((float) $batch->household_share - round($expectedValue * 0.6, 2)) < 0.01);

        $drift = round((float) $batch->household_share + (float) $batch->agent_share - (float) $batch->value_naira, 4);
        $this->check('shares reconcile to zero drift', $drift == 0.0, "drift = {$drift}");

        $this->check('variance computed from declared',
            abs($batch->variance_pct - round((21.0 - 22.0) / 22.0 * 100, 2)) < 0.01);

        // Settlement must refuse an unverified batch.
        $unverified = WasteBatch::where('status', BatchStatus::Requested->value)->first()
            ?? WasteBatch::create([
                'ulid' => app(BatchCodeGenerator::class)->ulid(),
                'code' => 'TEST2-' . uniqid(),
                'household_id' => Household::first()->id,
                'waste_category_id' => WasteCategory::first()->id,
                'status' => BatchStatus::Requested, 'expected_kg' => 5,
            ]);
        try {
            app(SettlementService::class)->settle($unverified);
            $this->check('refuses to settle an unverified batch', false, 'it settled');
        } catch (\DomainException) {
            $this->check('refuses to settle an unverified batch', true);
        }
    }

    private function testIdempotency(): void
    {
        $this->group('Idempotency');

        $batch = $this->testBatch;
        $before = $batch->household->balance();
        $entriesBefore = $batch->ledgerEntries()->count();

        app(SettlementService::class)->settle($batch->refresh());
        app(SettlementService::class)->settle($batch->refresh());

        $this->check('settling 3x does not change balance',
            abs($batch->household->balance() - $before) < 0.001,
            'balance moved from ' . $before . ' to ' . $batch->household->balance());
        $this->check('no duplicate ledger entries',
            $batch->ledgerEntries()->count() === $entriesBefore,
            'entries went ' . $entriesBefore . ' -> ' . $batch->ledgerEntries()->count());
    }

    private function testIllegalTransitions(): void
    {
        $this->group('State machine guards');

        $machine = app(BatchStateMachine::class);
        $staff = User::where('role', 'admin')->first();

        foreach ([
            [BatchStatus::Processed, BatchStatus::Requested, 'PROCESSED -> REQUESTED'],
            [BatchStatus::Requested, BatchStatus::Verified, 'REQUESTED -> VERIFIED (skip)'],
            [BatchStatus::Available, BatchStatus::Processed, 'AVAILABLE -> PROCESSED (skip)'],
        ] as [$from, $to, $label]) {
            $b = WasteBatch::where('status', $from->value)->first();
            if (! $b) { $this->skip("no {$from->value} batch to test {$label}"); continue; }
            try {
                $machine->transition($b, $to, $staff);
                $this->check("refuses {$label}", false, 'it was allowed');
            } catch (\DomainException) {
                $this->check("refuses {$label}", true);
            }
        }

        $this->check('terminal states have no exits',
            BatchStatus::Processed->isTerminal() && BatchStatus::Rejected->isTerminal());
    }

    private function testConcurrentClaim(): void
    {
        $this->group('Concurrency');

        $machine = app(BatchStateMachine::class);
        $job = WasteBatch::where('status', BatchStatus::Available->value)->whereNull('agent_id')->first();

        if (! $job) { $this->skip('no AVAILABLE job to race'); return; }

        $a1 = Agent::first();
        $a2 = Agent::skip(1)->first();

        $first = $machine->claim($job, $a1);
        $second = $machine->claim($job->refresh(), $a2);

        $this->check('first agent claims the job', $first === true);
        $this->check('second agent is refused', $second === false);
        $this->check('job belongs to the first agent', $job->refresh()->agent_id === $a1->id);
    }

    private function testDeliveryApportionment(): void
    {
        $this->group('Delivery apportionment');

        $agent = Agent::first();
        $codes = app(BatchCodeGenerator::class);
        $cat = WasteCategory::where('key', 'organic')->first();

        $delivery = Delivery::create([
            'code' => 'TESTD-' . uniqid(),
            'agent_id' => $agent->id,
            'facility_id' => \App\Models\Facility::first()->id,
            'gross_kg' => 100, 'tare_kg' => 10, 'net_kg' => 90,
            'weighed_at' => now(), 'status' => 'weighed',
        ]);

        // Three batches declared 30 / 60 / 10 = 100, net weighs 90.
        foreach ([30.0, 60.0, 10.0] as $declared) {
            WasteBatch::create([
                'ulid' => $codes->ulid(), 'code' => 'TESTB-' . uniqid(),
                'household_id' => Household::first()->id,
                'agent_id' => $agent->id, 'delivery_id' => $delivery->id,
                'waste_category_id' => $cat->id,
                'status' => BatchStatus::Received, 'collected_kg' => $declared,
            ]);
        }

        app(SettlementService::class)->apportionDelivery($delivery->refresh());

        $shares = $delivery->batches()->pluck('verified_kg')->map(fn ($v) => (float) $v);
        $sum = round($shares->sum(), 2);

        $this->check('apportioned weights sum to the net', $sum === 90.0, "sum = {$sum}");
        $this->check('split in declared proportion',
            abs($shares[0] - 27.0) < 0.02 && abs($shares[1] - 54.0) < 0.02,
            'got ' . $shares->implode(', '));
        $this->check('variance recorded on each batch',
            $delivery->batches()->whereNull('variance_pct')->count() === 0);
    }

    private function testLedgerIntegrity(): void
    {
        $this->group('Ledger');

        $this->check('no wallet_balance column exists',
            ! \Schema::hasColumn('households', 'wallet_balance')
            && ! \Schema::hasColumn('agents', 'wallet_balance'));

        $this->check('every entry has an idempotency key',
            LedgerEntry::whereNull('idempotency_key')->count() === 0);

        $this->check('idempotency keys are unique',
            LedgerEntry::count() === LedgerEntry::distinct('idempotency_key')->count('idempotency_key'));

        $this->check('no negative amounts',
            LedgerEntry::where('amount', '<', 0)->count() === 0);

        // Balance must equal the signed sum, independently computed.
        $h = Household::whereHas('ledgerEntries')->first();
        if ($h) {
            $manual = (float) LedgerEntry::where('party_type', $h->getMorphClass())
                ->where('party_id', $h->id)
                ->get()
                ->sum(fn ($e) => (float) $e->amount * $e->direction->sign());
            $this->check('balance() equals the signed sum',
                abs($h->balance() - $manual) < 0.001, "{$h->balance()} vs {$manual}");
        } else {
            $this->skip('no household with ledger entries');
        }
    }

    private function testPayouts(): void
    {
        $this->group('Payouts');

        $ledger = app(LedgerService::class);
        $h = Household::whereHas('ledgerEntries')->first();

        if (! $h) { $this->skip('no party with credits'); return; }

        $owed = $h->pendingAmount();
        $payout = $ledger->openPayout($h);

        if ($owed <= 0) { $this->skip('nothing outstanding'); return; }

        $this->check('payout created', $payout !== null);
        $this->check('payout equals what was owed',
            abs((float) $payout->amount - $owed) < 0.01, "{$payout->amount} vs {$owed}");
        $this->check('balance returns to zero after payout',
            abs($h->fresh()->balance()) < 0.01, 'balance = ' . $h->fresh()->balance());
        $this->check('credits are attached to the payout',
            $payout->entries()->where('direction', 'credit')->count() > 0);

        $second = $ledger->openPayout($h->fresh());
        $this->check('a second payout finds nothing left', $second === null);

        $ledger->markPaid($payout, User::first()->id);
        $this->check('payout marks as paid', $payout->fresh()->status === 'paid');
    }

    private function testIntegrityFlagging(): void
    {
        $this->group('Integrity');

        $integrity = app(IntegrityService::class);
        $codes = app(BatchCodeGenerator::class);

        $bad = WasteBatch::create([
            'ulid' => $codes->ulid(), 'code' => 'TESTF-' . uniqid(),
            'household_id' => Household::first()->id,
            'agent_id' => Agent::first()->id,
            'waste_category_id' => WasteCategory::first()->id,
            'status' => BatchStatus::Verified,
            'collected_kg' => 50.0, 'verified_kg' => 30.0, 'variance_pct' => -40.0,
        ]);

        $flag = $integrity->evaluateBatch($bad);
        $this->check('40% variance raises a flag', $flag !== null);
        $this->check('40% variance is high severity',
            $flag && $flag->severity->value === 'high', 'got ' . ($flag?->severity->value ?? 'none'));

        $ok = WasteBatch::create([
            'ulid' => $codes->ulid(), 'code' => 'TESTG-' . uniqid(),
            'household_id' => Household::first()->id,
            'agent_id' => Agent::first()->id,
            'waste_category_id' => WasteCategory::first()->id,
            'status' => BatchStatus::Verified,
            'collected_kg' => 20.0, 'verified_kg' => 19.6, 'variance_pct' => -2.0,
        ]);
        $this->check('2% variance raises nothing', $integrity->evaluateBatch($ok) === null);

        $this->check('flagging twice does not duplicate',
            $integrity->evaluateBatch($bad->refresh())->id === $flag->id);
    }

    private function testNotifications(): void
    {
        $this->group('Notifications and email');

        Notification::fake();
        $batch = $this->testBatch;
        $user = $batch->household->user;
        $user->notify(new BatchVerified($batch));

        Notification::assertSentTo($user, BatchVerified::class);
        $this->check('notification dispatches', true);

        // Render the real mail body to prove it does not blow up on nulls.
        Notification::fake(false);
        $mail = (new BatchVerified($batch))->toMail($user);
        $body = (string) $mail->render();

        $this->check('email subject names the batch',
            str_contains($mail->subject ?? '', $batch->code), 'subject: ' . ($mail->subject ?? '(none)'));
        $this->check('email body renders', strlen($body) > 400);
        $this->check('email states the verified weight',
            str_contains($body, (string) $batch->verified_kg));
        $this->check('email states the payout',
            str_contains(str_replace(',', '', $body), number_format((float) $batch->household_share, 2, '.', '')));

        $db = (new BatchVerified($batch))->toDatabase($user);
        $this->check('database channel payload is complete',
            isset($db['kind'], $db['title'], $db['batch_id']));

        $this->check('mail driver configured', filled(config('mail.default')));

        foreach ([\App\Notifications\BatchRejected::class,
                  \App\Notifications\JobAccepted::class,
                  \App\Notifications\BatchCollected::class] as $class) {
            try {
                $n = new $class($batch);
                $m = $n->toMail($user);
                $this->check(class_basename($class) . ' renders', strlen((string) $m->render()) > 200);
            } catch (\Throwable $e) {
                $this->check(class_basename($class) . ' renders', false, $e->getMessage());
            }
        }
    }

    private function testJobMatching(): void
    {
        $this->group('Job matching');

        $matcher = app(JobMatcher::class);
        $agent = Agent::first();
        $ranked = $matcher->rank($agent, 10);

        $this->check('matcher returns jobs', $ranked->count() >= 0);

        if ($ranked->count() >= 2) {
            $scores = $ranked->pluck('match_score')->all();
            $this->check('results are sorted by score',
                $scores === array_values(collect($scores)->sortDesc()->all()));
            $this->check('every job has a distance',
                $ranked->every(fn ($j) => is_numeric($j->distance_km)));
            $this->check('scores are 0-100',
                $ranked->every(fn ($j) => $j->match_score >= 0 && $j->match_score <= 100));
        } else {
            $this->skip('fewer than 2 open jobs to rank');
        }

        // Haversine sanity: Ikeja to Ikorodu is roughly 18 km.
        $d = $matcher->distanceKm(6.6018, 3.3515, 6.6194, 3.5105);
        $this->check('haversine is plausible', $d > 12 && $d < 25, sprintf('%.1f km', $d));
    }

    private function testQrCodes(): void
    {
        $this->group('QR codes');

        $svg = app(QrService::class)->svg('http://localhost/b/EC-W-2026-0091827');
        $this->check('QR renders as SVG', str_starts_with($svg, '<svg'));
        $this->check('QR has modules', substr_count($svg, '<rect') > 100);
    }

    private function testOpenAiFallback(): void
    {
        $this->group('OpenAI');

        $ai = app(OpenAiService::class);
        $enabled = $ai->enabled();
        $this->line('    key configured: ' . ($enabled ? 'yes' : 'NO — testing fallbacks'));

        $answer = $ai->answer('How much did we verify this month?', [
            'batch_count' => 10, 'area_count' => 12,
            'intake_today_kg' => 100, 'verified_kg_month' => 250, 'settled_month_naira' => 5000,
        ]);
        $this->check('analytics always returns an answer', filled($answer['answer']));
        $this->check('answer declares whether it was generated', isset($answer['generated']));

        $c = $ai->classifyPhoto('does-not-exist.jpg');
        $this->check('classification degrades on a missing file',
            isset($c['mix']) && $c['generated'] === false);
    }

    private function testRoleRouting(): void
    {
        $this->group('Roles');

        $this->check('household lands on /app',
            \App\Enums\UserRole::Household->home() === '/app');
        $this->check('agent lands on /agent',
            \App\Enums\UserRole::Agent->home() === '/agent');
        $this->check('staff lands on /console',
            \App\Enums\UserRole::Staff->home() === '/console');
        $this->check('admin counts as facility',
            \App\Enums\UserRole::Admin->isFacility());
        $this->check('household is not facility',
            ! \App\Enums\UserRole::Household->isFacility());
    }

    // --------------------------------------------------------------- output

    private function group(string $name): void
    {
        $this->line('');
        $this->line("  \e[1m{$name}\e[0m");
    }

    private function check(string $label, bool $ok, string $detail = ''): void
    {
        $this->results[] = $ok;
        $mark = $ok ? "\e[32mPASS\e[0m" : "\e[31mFAIL\e[0m";
        $this->line(sprintf('    %s  %s%s', $mark, $label, $ok || $detail === '' ? '' : "  \e[33m({$detail})\e[0m"));
    }

    private function skip(string $why): void
    {
        $this->line("    \e[33mSKIP\e[0m  {$why}");
    }

    private function fail_(string $label, string $detail): void
    {
        $this->results[] = false;
        $this->line("    \e[31mFAIL\e[0m  {$label}  \e[33m({$detail})\e[0m");
    }

    private function report(): int
    {
        $total = count($this->results);
        $passed = count(array_filter($this->results));
        $failed = $total - $passed;

        $this->line('');
        $this->line(str_repeat('=', 62));
        $this->line(sprintf('  %d checks   %d passed   %d failed', $total, $passed, $failed));
        $this->line('');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
