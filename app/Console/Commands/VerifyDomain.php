<?php

namespace App\Console\Commands;

use App\Enums\BatchStatus;
use App\Models\User;
use App\Models\WasteBatch;
use App\Services\BatchStateMachine;
use App\Services\IntegrityService;
use App\Services\SettlementService;
use Illuminate\Console\Command;

/**
 * End-to-end check of the rules the business actually rests on.
 * Run with: php artisan ecocycle:verify
 */
class VerifyDomain extends Command
{
    protected $signature = 'ecocycle:verify';
    protected $description = 'Exercise the batch lifecycle, settlement and ledger guarantees';

    public function handle(BatchStateMachine $m, SettlementService $s, IntegrityService $i): int
    {
        $staff = User::where('email', 'manager@ecocycle.ng')->firstOrFail();
        $b = WasteBatch::where('status', BatchStatus::Received->value)->first();

        if (! $b) {
            $this->error('No RECEIVED batch to test with. Run db:seed first.');

            return self::FAILURE;
        }

        $this->line("batch      {$b->code}");
        $this->line("declared   {$b->collected_kg} kg");
        $this->line('hh before  NGN ' . number_format($b->household->balance(), 2));
        $this->line('ag before  NGN ' . number_format($b->agent?->balance() ?? 0, 2));

        $b = $m->transition($b, BatchStatus::Inspecting, $staff);
        $b = $m->transition($b, BatchStatus::Verified, $staff, ['verified_kg' => 18.7, 'grade' => 'A']);
        $s->settle($b);
        $flag = $i->evaluateBatch($b->refresh());

        $this->newLine();
        $this->info('--- after inspection ---');
        $this->line("status     {$b->status->value}");
        $this->line("verified   {$b->verified_kg} kg");
        $this->line('value      NGN ' . number_format((float) $b->value_naira, 2));
        $this->line('household  NGN ' . number_format((float) $b->household_share, 2));
        $this->line('agent      NGN ' . number_format((float) $b->agent_share, 2));

        $drift = round((float) $b->household_share + (float) $b->agent_share - (float) $b->value_naira, 4);
        $this->line("drift      {$drift}" . ($drift == 0 ? '  OK' : '  *** MONEY LOST ***'));
        $this->line("variance   {$b->variance_pct}%");
        $this->line('flag       ' . ($flag ? $flag->severity->value . ' / ' . $flag->kind : 'none'));
        $this->line('hh after   NGN ' . number_format($b->household->balance(), 2));
        $this->line('ag after   NGN ' . number_format($b->agent?->balance() ?? 0, 2));
        $this->line('events     ' . $b->events()->count());

        $this->newLine();
        $this->info('--- settling twice must not pay twice ---');
        $before = $b->household->fresh()->balance();
        $s->settle($b->refresh());
        $after = $b->household->fresh()->balance();
        $entries = $b->ledgerEntries()->count();
        $this->line('balance    NGN ' . number_format($after, 2)
            . ($before == $after ? '  UNCHANGED OK' : '  *** DOUBLE PAID ***'));
        $this->line("entries    {$entries}" . ($entries === 2 ? '  OK' : '  *** DUPLICATED ***'));

        $this->newLine();
        $this->info('--- illegal transitions must be refused ---');
        try {
            $m->transition($b->refresh(), BatchStatus::Requested, $staff);
            $this->error('ALLOWED — the state machine is not guarding transitions');

            return self::FAILURE;
        } catch (\DomainException $e) {
            $this->line('refused    ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('--- two agents cannot claim one job ---');
        $job = WasteBatch::where('status', BatchStatus::Available->value)->first();
        $a1 = \App\Models\Agent::first();
        $a2 = \App\Models\Agent::skip(1)->first();
        $first = $m->claim($job, $a1);
        $second = $m->claim($job->refresh(), $a2);
        $this->line('first claim  ' . var_export($first, true));
        $this->line('second claim ' . var_export($second, true)
            . ($second === false ? '  CORRECTLY REFUSED' : '  *** DOUBLE CLAIM ***'));

        return self::SUCCESS;
    }
}
