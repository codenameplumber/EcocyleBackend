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
use Database\Seeders\Support\TrailBuilder;
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

    /** agent id => how far their declarations drift from the scale, in percent */
    private array $honesty = [];

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

        // Most collectors are accurate. A few are sloppy and a couple are not
        // honest -- which is the whole point of keeping the variance.
        foreach ($agents as $i => $a) {
            $this->honesty[$a->id] = match (true) {
                $i % 20 === 0 => ['bias' => -11.0, 'spread' => 5.0],   // 5%  dishonest
                $i % 7 === 0  => ['bias' => -4.0,  'spread' => 4.0],   // ~14% sloppy
                default       => ['bias' => -1.0,  'spread' => 2.0],   // the rest
            };
        }

        $this->command->info('  ' . count($households) . ' households, ' . count($agents) . ' agents');

        $made = $this->makeHistory($households, $agents, $categories, $facility, $settlement, $integrity);

        $this->command->info("  {$made} batches across " . self::DAYS . ' days');

        // Scores are derived from the weighing history, so they can only be
        // computed once that history exists.
        foreach ($agents as $a) {
            $integrity->refreshAgentScore($a);
        }

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
                $category = $this->pickCategory($categories);

                $expected = round(random_int(30, 420) / 10, 1);
                // Agents declare a little high more often than a little low.
                $collected = round($expected * (0.9 + random_int(0, 350) / 1000), 1);

                $requestedAt = $date->copy()->setTime(random_int(6, 18), random_int(0, 59));
                $pickup = $this->pickupPoint($household);

                $batch = WasteBatch::create([
                    'ulid' => (string) Str::ulid(),
                    'code' => sprintf('EC-W-%d-%07d', $requestedAt->year, ++$seq),
                    'household_id' => $household->id,
                    'agent_id' => null,
                    'waste_category_id' => $category->id,
                    'status' => BatchStatus::Requested,
                    'expected_kg' => $expected,
                    'pickup_lat' => $pickup[0],
                    'pickup_lng' => $pickup[1],
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
        if ($step < 2) { $this->commit($batch, $attrs); return; }

        if ($target === BatchStatus::Available) { $this->commit($batch, $attrs); return; }

        $attrs['accepted_at'] = $t = $t->copy()->addMinutes(random_int(5, 180));
        $attrs['agent_id'] = $agent->id;
        if ($target === BatchStatus::Accepted) { $this->commit($batch, $attrs); return; }

        $attrs['collected_at'] = $t = $t->copy()->addMinutes(random_int(20, 240));
        $attrs['collected_kg'] = $collected;
        if ($target === BatchStatus::Collected) { $this->commit($batch, $attrs); return; }

        $attrs['in_transit_at'] = $t = $t->copy()->addMinutes(random_int(5, 90));
        if ($target === BatchStatus::InTransit) { $this->commit($batch, $attrs); return; }

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
        if ($target === BatchStatus::Received) { $this->commit($batch, $attrs); return; }

        $attrs['inspecting_at'] = $t = $t->copy()->addMinutes(random_int(5, 90));
        if ($target === BatchStatus::Inspecting) { $this->commit($batch, $attrs); return; }

        // The scale reads against this agent's own accuracy profile, so the
        // flags that come out the other end land on the same few collectors.
        $h = $this->honesty[$agent->id] ?? ['bias' => -1.0, 'spread' => 2.0];
        $drift = $h['bias'] + (random_int(-1000, 1000) / 1000) * $h['spread'];
        $verified = max(0.1, round($collected * (1 + $drift / 100), 1));

        if ($target === BatchStatus::Rejected) {
            $attrs['rejected_at'] = $t->copy()->addMinutes(random_int(3, 40));
            $attrs['rejection_reason'] = [
                'Contaminated — non-recoverable material',
                'Category mismatch',
                'Hazardous content',
                'Spoiled beyond processing window',
            ][random_int(0, 3)];
            $attrs['grade'] = 'D';
            $this->commit($batch, $attrs);

            return;
        }

        $attrs['verified_at'] = $t = $t->copy()->addMinutes(random_int(3, 40));
        $attrs['verified_kg'] = $verified;
        $attrs['grade'] = ['A', 'A', 'B', 'B', 'C'][random_int(0, 4)];
        $attrs['confirmed_category_id'] = $batch->waste_category_id;
        if ($target === BatchStatus::Verified) { $this->commit($batch, $attrs); return; }

        $attrs['processing_at'] = $t = $t->copy()->addHours(random_int(1, 20));
        if ($target === BatchStatus::Processing) { $this->commit($batch, $attrs); return; }

        $attrs['processed_at'] = $t->copy()->addHours(random_int(2, 26));
        $this->commit($batch, $attrs);
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

    /**
     * Where the agent's phone said the pickup happened.
     *
     * A confirmation is never exactly on the registered pin: handsets drift and
     * the bags are usually at the gate rather than the door. Most land within a
     * street's width; a small tail lands nowhere near, which is the case the
     * integrity screen exists to catch.
     *
     * @return array{0: float, 1: float}
     */
    /**
     * Save a batch's stamped state, then rebuild the audit trail that goes with
     * it. See TrailBuilder for why the trail is derived rather than replayed.
     *
     * @param  array<string, mixed>  $attrs
     */
    private function commit(WasteBatch $batch, array $attrs): void
    {
        $batch->forceFill($attrs)->save();

        TrailBuilder::rebuild($batch);
    }

    /**
     * Weighted stream selection.
     *
     * Roughly the composition Lagos household waste actually has: organics
     * dominate the weight, the high-rate streams are a thin slice. Picking
     * uniformly would make every stream about a seventh of tonnage and quietly
     * invert the margin picture the analytics screen is meant to show.
     */
    private function pickCategory($categories)
    {
        static $weights = [
            'organic' => 52, 'mixed' => 14, 'paper' => 12,
            'plastic' => 13, 'glass' => 5, 'metal' => 3, 'ewaste' => 1,
        ];

        $roll = random_int(1, 100);
        $running = 0;

        foreach ($weights as $key => $w) {
            $running += $w;
            if ($roll <= $running) {
                if ($hit = $categories->firstWhere('key', $key)) {
                    return $hit;
                }
            }
        }

        return $categories->random();
    }

    private function pickupPoint($household): array
    {
        // 0.0018 degrees is roughly 200 m at this latitude, 0.00005 about 5 m.
        $spread = random_int(1, 100) > 96 ? 180 : 5;

        return [
            round((float) $household->lat + random_int(-$spread, $spread) / 10000, 7),
            round((float) $household->lng + random_int(-$spread, $spread) / 10000, 7),
        ];
    }

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
