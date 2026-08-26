<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Livewire\Agent\Jobs;
use App\Livewire\Agent\Pickup;
use App\Livewire\Console\Inspection;
use App\Livewire\Console\Integrity;
use App\Livewire\Console\Payments;
use App\Livewire\Console\Processing;
use App\Livewire\Household\Profile as HouseholdProfile;
use App\Livewire\Household\RequestPickup;
use App\Livewire\Household\Wallet;
use App\Livewire\Notifications;
use App\Models\Agent;
use App\Models\Household;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\ProcessingRun;
use App\Models\User;
use App\Models\WasteBatch;
use App\Models\WasteCategory;
use Database\Seeders\EcoCycleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The screens, driven the way a person drives them.
 *
 * RouteSmokeTest proves the pages render; this proves the buttons on them do
 * what they claim. It walks one batch from a household's request through to a
 * settled payout, asserting the money rules at each hand-off, and then exercises
 * the smaller interactions that a render test cannot see.
 */
class LifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EcoCycleSeeder::class);
    }

    #[Test]
    public function a_batch_walks_from_request_to_settled_payout(): void
    {
        Notification::fake();

        $householdUser = User::whereHas('household')->firstOrFail();
        $category = WasteCategory::where('key', 'plastic')->firstOrFail();

        // --- 1. The household requests a pickup ---------------------------
        Livewire::actingAs($householdUser)
            ->test(RequestPickup::class)
            ->set('categoryId', $category->id)
            ->set('kg', 12.0)
            ->call('submit')
            ->assertHasNoErrors();

        $batch = WasteBatch::latest('id')->firstOrFail();

        $this->assertSame(BatchStatus::Available, $batch->status);
        $this->assertEquals(12.0, $batch->expected_kg);
        $this->assertNull($batch->collected_kg, 'a request must not pre-fill a collected weight');
        $this->assertNull($batch->verified_kg, 'a request must not pre-fill a verified weight');

        // --- 2. An agent claims it ----------------------------------------
        $agentUser = User::whereHas('agent')->firstOrFail();

        Livewire::actingAs($agentUser)
            ->test(Jobs::class)
            ->call('accept', $batch->id);

        $batch->refresh();
        $this->assertSame(BatchStatus::Accepted, $batch->status);
        $this->assertSame($agentUser->agent->id, $batch->agent_id);

        // --- 3. The agent works the pickup stages -------------------------
        $pickup = Livewire::actingAs($agentUser)->test(Pickup::class, ['batch' => $batch]);

        // Walking the stage rail is a UI step; nothing about the batch changes.
        $pickup->call('next')->call('next');
        $this->assertSame(BatchStatus::Accepted, $batch->refresh()->status,
            'moving through the stages must not transition the batch');

        $pickup->set('kg', 13.4)->set('categoryId', $category->id)->call('confirm')
            ->assertHasNoErrors();

        $batch->refresh();
        $this->assertSame(BatchStatus::Collected, $batch->status);
        $this->assertEquals(13.4, $batch->collected_kg);
        $this->assertNull($batch->verified_kg, 'a declared weight is not a verified one');

        $pickup->call('depart');
        $this->assertSame(BatchStatus::InTransit, $batch->refresh()->status);

        // --- 4. The gate receives it --------------------------------------
        app(\App\Services\BatchStateMachine::class)
            ->transition($batch, BatchStatus::Received, $agentUser);

        // --- 5. The inspector weighs and verifies -------------------------
        $staff = User::where('role', 'admin')->firstOrFail();

        $inspection = Livewire::actingAs($staff)->test(Inspection::class)
            ->call('select', $batch->id)
            ->set('verifiedKg', '11.8')
            ->set('categoryId', $category->id)
            ->set('grade', 'A');

        $inspection->call('accept');

        $batch->refresh();
        $this->assertSame(BatchStatus::Verified, $batch->status);
        $this->assertEquals(11.8, $batch->verified_kg);

        // Money follows the scale, not the declaration.
        $expectedValue = round(11.8 * (float) $category->rate_per_kg, 2);
        $this->assertEquals($expectedValue, (float) $batch->value_naira);
        $this->assertEquals(round($expectedValue * 0.6, 2), (float) $batch->household_share);
        $this->assertEquals(
            round($expectedValue, 2),
            round((float) $batch->household_share + (float) $batch->agent_share, 2),
            'the two shares must reconstitute the batch value exactly',
        );

        // --- 6. Both sides were credited ----------------------------------
        $this->assertDatabaseHas('ledger_entries', [
            'waste_batch_id' => $batch->id,
            'party_type' => Household::class,
            'direction' => 'credit',
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'waste_batch_id' => $batch->id,
            'party_type' => Agent::class,
            'direction' => 'credit',
        ]);

        // --- 7. The household opens a payout ------------------------------
        $household = $batch->household;
        $household->update(['preferences' => ['payout_bank' => 'GTBank', 'payout_account' => '0123 **** 81']]);

        $before = $household->balance();
        $this->assertGreaterThan(0, $before);

        Livewire::actingAs($household->user)->test(Wallet::class)->call('requestPayout');

        $payout = Payout::where('party_type', Household::class)
            ->where('party_id', $household->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertEqualsWithDelta($before, (float) $payout->amount, 0.01);
        $this->assertEqualsWithDelta(0, $household->fresh()->balance(), 0.01,
            'opening a payout must zero the balance via a balancing debit');

        // --- 8. Processing, and the run that consumed it -------------------
        Livewire::actingAs($staff)->test(Processing::class)->call('startRun', $batch->id);

        $batch->refresh();
        $this->assertSame(BatchStatus::Processing, $batch->status);
        $this->assertNotNull($batch->processing_run_id, 'a processed batch must be attached to a run');

        Livewire::actingAs($staff)->test(Processing::class)->call('complete', $batch->id);

        $batch->refresh();
        $this->assertSame(BatchStatus::Processed, $batch->status);

        $run = ProcessingRun::findOrFail($batch->processing_run_id);
        $this->assertSame('complete', $run->status);
        $this->assertGreaterThan(0, (float) $run->output_kg);
        $this->assertLessThanOrEqual((float) $run->input_kg, (float) $run->output_kg,
            'a line cannot produce more than it was fed');
    }

    #[Test]
    public function settling_the_same_batch_twice_does_not_pay_twice(): void
    {
        $batch = WasteBatch::whereNotNull('verified_kg')
            ->where('status', BatchStatus::Processed->value)
            ->firstOrFail();

        $before = LedgerEntry::where('waste_batch_id', $batch->id)->count();

        app(\App\Services\SettlementService::class)->settle($batch, (float) $batch->verified_kg);

        $this->assertSame($before, LedgerEntry::where('waste_batch_id', $batch->id)->count());
    }

    #[Test]
    public function two_agents_cannot_claim_the_same_job(): void
    {
        $batch = WasteBatch::where('status', BatchStatus::Available->value)->firstOrFail();

        $agents = User::whereHas('agent')->take(2)->get();
        $this->assertCount(2, $agents);

        Livewire::actingAs($agents[0])->test(Jobs::class)->call('accept', $batch->id);
        Livewire::actingAs($agents[1])->test(Jobs::class)->call('accept', $batch->id);

        $this->assertSame($agents[0]->agent->id, $batch->fresh()->agent_id);
    }

    #[Test]
    public function a_rejected_batch_pays_nobody(): void
    {
        Notification::fake();

        $batch = WasteBatch::where('status', BatchStatus::Received->value)->first()
            ?? WasteBatch::where('status', BatchStatus::Available->value)->firstOrFail();

        if ($batch->status !== BatchStatus::Received) {
            $machine = app(\App\Services\BatchStateMachine::class);
            $agent = Agent::firstOrFail();
            $batch->update(['agent_id' => $agent->id, 'collected_kg' => 10]);
            foreach ([BatchStatus::Accepted, BatchStatus::Collected, BatchStatus::InTransit, BatchStatus::Received] as $to) {
                $machine->transition($batch, $to, $agent->user);
            }
        }

        $staff = User::where('role', 'admin')->firstOrFail();

        Livewire::actingAs($staff)->test(Inspection::class)
            ->call('select', $batch->id)
            ->set('rejectionReason', 'Hazardous content')
            ->call('reject');

        $batch->refresh();
        $this->assertSame(BatchStatus::Rejected, $batch->status);
        $this->assertSame(0, LedgerEntry::where('waste_batch_id', $batch->id)->count(),
            'a rejected batch must not produce a single ledger entry');
    }

    #[Test]
    public function preference_switches_persist(): void
    {
        $user = User::whereHas('household')->firstOrFail();
        $before = $user->household->pref('notify_weekly');

        Livewire::actingAs($user)->test(HouseholdProfile::class)->call('toggle', 'notify_weekly');

        $this->assertSame(! $before, $user->household->fresh()->pref('notify_weekly'));
    }

    #[Test]
    public function an_unknown_preference_key_is_ignored(): void
    {
        $user = User::whereHas('household')->firstOrFail();

        Livewire::actingAs($user)->test(HouseholdProfile::class)->call('toggle', 'grant_me_admin');

        $this->assertArrayNotHasKey('grant_me_admin', (array) $user->household->fresh()->preferences);
    }

    #[Test]
    public function marking_notifications_read_clears_the_unread_count(): void
    {
        $user = User::whereHas('household')->firstOrFail();

        $user->notify(new \App\Notifications\BatchVerified(
            WasteBatch::whereNotNull('verified_kg')->firstOrFail()
        ));

        $this->assertGreaterThan(0, $user->unreadNotifications()->count());

        Livewire::actingAs($user)->test(Notifications::class)->call('markAllRead');

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    #[Test]
    public function resolving_an_integrity_case_records_the_decision(): void
    {
        $staff = User::where('role', 'admin')->firstOrFail();
        $flag = \App\Models\IntegrityFlag::open()->first();

        if (! $flag) {
            $this->markTestSkipped('the base fixtures raised no integrity flag');
        }

        Livewire::actingAs($staff)->test(Integrity::class)
            ->call('select', $flag->id)
            ->call('resolve', 'dismissed');

        $flag->refresh();
        $this->assertSame('dismissed', $flag->status);
        $this->assertNotNull($flag->resolved_at);
        $this->assertSame($staff->id, $flag->resolved_by);
    }

    #[Test]
    public function the_settlement_queue_filters_by_status(): void
    {
        $staff = User::where('role', 'admin')->firstOrFail();

        Livewire::actingAs($staff)->test(Payments::class)
            ->call('setStatus', 'paid')
            ->assertSet('status', 'paid')
            ->assertOk();
    }
}
