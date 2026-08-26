<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Household;
use Illuminate\Database\Seeder;

/**
 * Gives every party a payout arrangement.
 *
 * Account numbers are generated already masked. There is no un-masked form
 * anywhere in this codebase, because the pilot pays by hand from a bank file
 * and a full account number would be a liability with no corresponding use.
 */
class PayoutAccountSeeder extends Seeder
{
    /** The same list the profile screens offer, so seeded and typed accounts agree. */
    private const BANKS = Household::PAYOUT_BANKS;

    public function run(): void
    {
        $n = 0;

        foreach (Household::cursor() as $h) {
            $prefs = (array) ($h->preferences ?? []);

            // A minority genuinely have not added one; the wallet screen has to
            // handle that case, so the data has to contain it.
            if ($h->id % 11 === 0) {
                continue;
            }

            $h->update(['preferences' => $prefs + [
                'payout_bank' => self::BANKS[$h->id % count(self::BANKS)],
                'payout_account' => $this->masked($h->id),
                'payout_cycle' => 'On request',
                'payout_auto' => $h->id % 3 === 0,
                'payout_threshold' => 10000,
            ]]);
            $n++;
        }

        foreach (Agent::cursor() as $a) {
            $prefs = (array) ($a->preferences ?? []);

            $a->update(['preferences' => $prefs + [
                'payout_bank' => self::BANKS[($a->id + 3) % count(self::BANKS)],
                'payout_account' => $this->masked($a->id * 7),
                'payout_cycle' => 'Weekly, every Monday',
                'payout_auto' => true,
                'payout_threshold' => 0,
            ]]);
            $n++;
        }

        $this->command->info("  {$n} payout accounts");
    }

    private function masked(int $seed): string
    {
        return sprintf('%04d **** %02d', 1000 + ($seed * 37) % 8999, ($seed * 13) % 90 + 10);
    }
}
