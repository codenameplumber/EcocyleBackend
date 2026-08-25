<?php

namespace Database\Seeders;

use App\Enums\BatchStatus;
use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Area;
use App\Models\Delivery;
use App\Models\Facility;
use App\Models\Household;
use App\Models\User;
use App\Models\WasteBatch;
use App\Models\WasteCategory;
use App\Services\IntegrityService;
use App\Services\LedgerService;
use App\Services\SettlementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Builds a network at pilot scale: 150 households, 30 agents and roughly
 * 90 days of collection history.
 *
 * Batches are pushed through the real state machine and settled through the
 * real SettlementService, so the ledger, the variance flags and every balance
 * are produced the same way production produces them. Nothing is inserted
 * straight into ledger_entries.
 */
class NetworkSeeder extends Seeder
{
    private const HOUSEHOLDS = 150;
    private const AGENTS     = 30;
    private const DAYS       = 90;

    /** Lagos-plausible name pools: Yoruba, Igbo, Hausa, Efik and Edo. */
    private array $first = [
        'Adebayo','Folake','Chidi','Amaka','Segun','Ngozi','Tunde','Bimbo','Emeka','Yemi',
        'Ifeoma','Musa','Titilayo','Obinna','Bukola','Kelechi','Damilola','Chinyere','Femi','Aisha',
        'Olumide','Zainab','Uche','Ronke','Ibrahim','Chiamaka','Gbenga','Halima','Nnamdi','Toyin',
        'Sunday','Precious','Blessing','Grace','Faith','Joy','Peace','Victor','Samuel','Daniel',
        'Abiodun','Oluwaseun','Kemi','Tobi','Sade','Rotimi','Bolaji','Yusuf','Fatima','Hauwa',
        'Ikechukwu','Adaeze','Chukwuma','Nkechi','Ebere','Onyeka','Ifeanyi','Chioma','Ada','Uzo',
        'Efe','Osaze','Eseosa','Idris','Bashir','Sadiq','Maryam','Rukayat','Wale','Dapo',
    ];

    private array $last = [
        'Adeyemi','Okonkwo','Balogun','Eze','Adeleke','Nwosu','Bakare','Salami','Obi','Lawal',
        'Okoro','Danjuma','Ogunleye','Uche','Adeyinka','Ihuoma','Bello','Oyelaran','Adebayo','Musa',
        'Afolabi','Nwachukwu','Oladele','Chukwu','Ogundipe','Anyanwu','Sanusi','Ekwueme','Adekunle','Iheanacho',
        'Olawale','Nnaji','Abiola','Onwuka','Shonibare','Madueke','Akinyemi','Oyedepo','Umeh','Garba',
        'Odukoya','Nwankwo','Ajayi','Igwe','Fashola','Osagie','Ogbonna','Yakubu','Alabi','Nweke',
    ];

    public function run(): void
    {
        $settlement = app(SettlementService::class);
        $integrity  = app(IntegrityService::class);
        $ledger     = app(LedgerService::class);

        $areas      = Area::all()->keyBy('name');
        $categories = WasteCategory::active()->get();
        $facility   = Facility::firstOrFail();

        if ($areas->isEmpty() || $categories->isEmpty()) {
            $this->command->error('Run EcoCycleSeeder first — reference data is missing.');

            return;
        }

        $this->command->info('Building the network. This settles every verified batch through the real service, so give it a minute.');

        $households = $this->makeHouseholds($areas, $categories);
        $agents     = $this->makeAgents($areas);

        $this->command->info('  ' . count($households) . ' households, ' . count($agents) . ' agents');

        $made = $this->makeHistory($households, $agents, $categories, $facility, $settlement, $integrity);

        $this->command->info("  {$made} batches across " . self::DAYS . ' days');

        $this->makePayouts($ledger, $households, $agents);

        $this->summary();
    }

    // ------------------------------------------------------------- parties

    private function makeHouseholds($areas, $categories): array
    {
        $out = [];
        $areaList = $areas->values();
        $freqs = ['Weekly', 'Weekly', 'Weekly', 'Twice/week', 'Bi-weekly'];

        for ($i = 0; $i < self::HOUSEHOLDS; $i++) {
            $name = $this->name($i);
            $code = 'EC-H-' . str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT);

            if ($existing = Household::where('code', $code)->first()) {
                $out[] = $existing;
                continue;
            }

            $area = $areaList[$i % $areaList->count()];

            $user = User::create([
                'name' => $name,
                'email' => $this->email($name, $i),
                'phone' => $this->phone(),
                'password' => Hash::make('password'),
                'role' => UserRole::Household,
                'email_verified_at' => now(),
            ]);

            $out[] = Household::create([
                'user_id' => $user->id,
                'code' => $code,
                'area_id' => $area->id,
                'address' => random_int(1, 90) . ' ' . $this->street() . ', ' . $area->name,
                // Jitter around the area centre so the map is not a single dot.
                'lat' => round($area->lat + (random_int(-260, 260) / 10000), 7),
                'lng' => round($area->lng + (random_int(-260, 260) / 10000), 7),
                'size' => random_int(2, 9),
                'frequency' => $freqs[array_rand($freqs)],
                'default_category_id' => $categories->random()->id,
                'status' => random_int(1, 10) > 8 ? 'paused' : 'active',
                'rating' => round(random_int(38, 50) / 10, 2),
            ]);
        }

        return $out;
    }

    private function makeAgents($areas): array
    {
        $out = [];
        $areaList = $areas->values();
        $vehicles = [
            ['Tricycle', 260], ['Tricycle', 260], ['Tricycle', 260],
            ['Motorcycle', 90], ['Motorcycle', 90],
            ['Pickup van', 850],
            ['On foot', 40],
        ];

        for ($i = 0; $i < self::AGENTS; $i++) {
            $code = 'EC-A-' . str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT);

            if ($existing = Agent::where('code', $code)->first()) {
                $out[] = $existing;
                continue;
            }

            $name = $this->name($i + 500);
            [$vehicle, $cap] = $vehicles[$i % count($vehicles)];
            $area = $areaList[$i % $areaList->count()];

            $user = User::create([
                'name' => $name,
                'email' => $this->email($name, $i + 500),
                'phone' => $this->phone(),
                'password' => Hash::make('password'),
                'role' => UserRole::Agent,
                'email_verified_at' => now(),
            ]);

            $out[] = Agent::create([
                'user_id' => $user->id,
                'code' => $code,
                'vehicle_type' => $vehicle,
                'capacity_kg' => $cap,
                'plate' => 'KJA-' . random_int(100, 999) . '-' . Str::upper(Str::random(2)),
                'operating_area' => $area->name,
                'kyc_verified_at' => random_int(1, 10) > 2 ? now()->subDays(random_int(30, 400)) : null,
                'rating' => round(random_int(38, 50) / 10, 2),
                'is_online' => random_int(1, 10) > 4,
                'last_lat' => round($area->lat + (random_int(-200, 200) / 10000), 7),
                'last_lng' => round($area->lng + (random_int(-200, 200) / 10000), 7),
                'last_seen_at' => now()->subMinutes(random_int(1, 600)),
            ]);
        }

        return $out;
    }

    // ------------------------------------------------------------- history

    private function makeHistory(array $households, array $agents, $categories, Facility $facility,
                                 SettlementService $settlement, IntegrityService $integrity): int
    {
        $made = 0;
        $seq = 200000;
        $deliveryPool = [];

        for ($day = self::DAYS; $day >= 0; $day--) {
            $date = now()->subDays($day);

            // Weekends are lighter; Sundays lighter still.
            $perDay = match ($date->dayOfWeek) {
                0 => random_int(2, 5),
                6 => random_int(5, 9),
                default => random_int(8, 14),
            };

            for ($n = 0; $n < $perDay; $n++) {
                $household = $households[array_rand($households)];
                if ($household->status === 'paused' && random_int(1, 4) > 1) {
                    continue;
                }

                $agent = $agents[array_rand($agents)];
                $category = $categories->random();

                $expected = round(random_int(30, 420) / 10, 1);
                // Agents declare a little high more often than a little low.
                $collected = round($expected * (0.9 + random_int(0, 350) / 1000), 1);

                $requestedAt = $date->copy()->setTime(random_int(6, 18), random_int(0, 59));

                $batch = WasteBatch::create([
                    'ulid' => (string) Str::ulid(),
                    'code' => sprintf('EC-W-%d-%07d', $requestedAt->year, ++$seq),
                    'household_id' => $household->id,
                    'agent_id' => null,
                    'waste_category_id' => $category->id,
                    'status' => BatchStatus::Requested,
                    'expected_kg' => $expected,
                    'pickup_lat' => $household->lat,
                    'pickup_lng' => $household->lng,
                    'requested_at' => $requestedAt,
                    'window_end' => $requestedAt->copy()->addHours(random_int(4, 48)),
                ]);
                $made++;

                // How far this batch got depends mostly on how old it is.
                $roll = random_int(1, 100);
                $target = match (true) {
                    $day <= 1 && $roll <= 45 => BatchStatus::Available,
                    $day <= 1 && $roll <= 65 => BatchStatus::Accepted,
                    $day <= 2 && $roll <= 78 => BatchStatus::Collected,
                    $day <= 2 && $roll <= 86 => BatchStatus::InTransit,
                    $day <= 3 && $roll <= 92 => BatchStatus::Received,
                    $day <= 3 && $roll <= 96 => BatchStatus::Inspecting,
                    $roll <= 6               => BatchStatus::Rejected,
                    $roll <= 20              => BatchStatus::Verified,
                    $roll <= 34              => BatchStatus::Processing,
                    default                  => BatchStatus::Processed,
                };

                $this->advance($batch, $target, $agent, $facility, $collected, $requestedAt, $deliveryPool);

                if ($batch->status->isSettleable() && $batch->verified_kg) {
                    $settlement->settle($batch);
                    $integrity->evaluateBatch($batch->refresh());
                }
            }
        }

        return $made;
    }

    /** Walk a batch forward, stamping plausible times as it goes. */
    private function advance(WasteBatch $batch, BatchStatus $target, Agent $agent,
                             Facility $facility, float $collected, $requestedAt, array &$deliveryPool): void
    {
        $step = $target->step();
        $t = $requestedAt->copy();
        $attrs = ['status' => $target->value];

        $attrs['available_at'] = $t = $t->copy()->addMinutes(random_int(2, 40));
        if ($step < 2) { $batch->forceFill($attrs)->save(); return; }

        if ($target === BatchStatus::Available) { $batch->forceFill($attrs)->save(); return; }

        $attrs['accepted_at'] = $t = $t->copy()->addMinutes(random_int(5, 180));
        $attrs['agent_id'] = $agent->id;
        if ($target === BatchStatus::Accepted) { $batch->forceFill($attrs)->save(); return; }

        $attrs['collected_at'] = $t = $t->copy()->addMinutes(random_int(20, 240));
        $attrs['collected_kg'] = $collected;
        if ($target === BatchStatus::Collected) { $batch->forceFill($attrs)->save(); return; }

        $attrs['in_transit_at'] = $t = $t->copy()->addMinutes(random_int(5, 90));
        if ($target === BatchStatus::InTransit) { $batch->forceFill($attrs)->save(); return; }

        // Group arrivals into one weighbridge event per agent per day.
        $key = $agent->id . ':' . $t->toDateString();
        if (! isset($deliveryPool[$key])) {
            $deliveryPool[$key] = Delivery::create([
                'code' => 'EC-D-' . $t->year . '-' . str_pad((string) (count($deliveryPool) + 100), 6, '0', STR_PAD_LEFT),
                'agent_id' => $agent->id,
                'facility_id' => $facility->id,
                'weighbridge' => 'Gate ' . random_int(1, 2),
                'weighed_at' => $t,
                'status' => 'weighed',
            ])->id;
        }
        $attrs['delivery_id'] = $deliveryPool[$key];
        $attrs['facility_id'] = $facility->id;
        $attrs['received_at'] = $t = $t->copy()->addMinutes(random_int(10, 120));
        if ($target === BatchStatus::Received) { $batch->forceFill($attrs)->save(); return; }

        $attrs['inspecting_at'] = $t = $t->copy()->addMinutes(random_int(5, 90));
        if ($target === BatchStatus::Inspecting) { $batch->forceFill($attrs)->save(); return; }

        // The scale reads a little under the declaration far more often than over.
        $verified = round($collected * (0.86 + random_int(0, 220) / 1000), 1);

        if ($target === BatchStatus::Rejected) {
            $attrs['rejected_at'] = $t->copy()->addMinutes(random_int(3, 40));
            $attrs['rejection_reason'] = [
                'Contaminated — non-recoverable material',
                'Category mismatch',
                'Hazardous content',
                'Spoiled beyond processing window',
            ][random_int(0, 3)];
            $attrs['grade'] = 'D';
            $batch->forceFill($attrs)->save();

            return;
        }

        $attrs['verified_at'] = $t = $t->copy()->addMinutes(random_int(3, 40));
        $attrs['verified_kg'] = $verified;
        $attrs['grade'] = ['A', 'A', 'B', 'B', 'C'][random_int(0, 4)];
        $attrs['confirmed_category_id'] = $batch->waste_category_id;
        if ($target === BatchStatus::Verified) { $batch->forceFill($attrs)->save(); return; }

        $attrs['processing_at'] = $t = $t->copy()->addHours(random_int(1, 20));
        if ($target === BatchStatus::Processing) { $batch->forceFill($attrs)->save(); return; }

        $attrs['processed_at'] = $t->copy()->addHours(random_int(2, 26));
        $batch->forceFill($attrs)->save();
    }

    // -------------------------------------------------------------- payouts

    private function makePayouts(LedgerService $ledger, array $households, array $agents): void
    {
        // Pay out roughly half the parties so the settlements screen has both
        // an outstanding balance and a payment history.
        $made = 0;

        foreach (array_slice($households, 0, (int) (count($households) * 0.5)) as $h) {
            if ($payout = $ledger->openPayout($h)) {
                $made++;
                if (random_int(1, 10) > 3) {
                    $ledger->markPaid($payout);
                }
            }
        }

        foreach (array_slice($agents, 0, (int) (count($agents) * 0.6)) as $a) {
            if ($payout = $ledger->openPayout($a)) {
                $made++;
                if (random_int(1, 10) > 3) {
                    $ledger->markPaid($payout);
                }
            }
        }

        $this->command->info("  {$made} payouts");
    }

    // ---------------------------------------------------------------- utils

    private function name(int $i): string
    {
        // Stride the surname list by a co-prime so first and last names do not
        // march in lockstep and repeat every cycle.
        return $this->first[$i % count($this->first)] . ' '
            . $this->last[($i * 7 + intdiv($i, count($this->first))) % count($this->last)];
    }

    private function email(string $name, int $i): string
    {
        return Str::slug($name, '.') . '.' . $i . '@ecocycle.test';
    }

    private function phone(): string
    {
        $prefix = ['0803', '0806', '0703', '0813', '0902', '0705', '0810', '0906'][random_int(0, 7)];

        return $prefix . ' ' . random_int(100, 999) . ' ' . random_int(1000, 9999);
    }

    private function street(): string
    {
        return [
            'Allen Avenue', 'Awolowo Road', 'Adeniyi Jones', 'Opebi Road', 'Toyin Street',
            'Herbert Macaulay Way', 'Bode Thomas', 'Adeola Odeku', 'Ogunlana Drive', 'Isaac John',
            'Kudirat Abiola Way', 'Oba Akran', 'Acme Road', 'Ladipo Street', 'Coker Road',
        ][random_int(0, 14)];
    }

    private function summary(): void
    {
        $rows = [
            ['users', User::count()],
            ['households', Household::count()],
            ['agents', Agent::count()],
            ['batches', WasteBatch::count()],
            ['deliveries', Delivery::count()],
            ['ledger entries', DB::table('ledger_entries')->count()],
            ['payouts', DB::table('payouts')->count()],
            ['integrity flags', DB::table('integrity_flags')->count()],
        ];

        $this->command->newLine();
        foreach ($rows as [$label, $n]) {
            $this->command->line(sprintf('  %-18s %s', $label, number_format($n)));
        }

        $verified = (float) WasteBatch::sum('verified_kg');
        $settled = (float) DB::table('ledger_entries')->where('direction', 'credit')->sum('amount');
        $this->command->line(sprintf('  %-18s %s kg', 'verified weight', number_format($verified, 1)));
        $this->command->line(sprintf('  %-18s NGN %s', 'settled', number_format($settled, 2)));
    }
}
