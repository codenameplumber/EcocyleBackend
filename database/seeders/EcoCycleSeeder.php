<?php

namespace Database\Seeders;

use App\Enums\BatchStatus;
use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Area;
use App\Models\Facility;
use App\Models\Household;
use App\Models\IntegrityFlag;
use App\Models\ProcessingLine;
use App\Models\User;
use App\Models\WasteBatch;
use App\Models\WasteCategory;
use App\Services\IntegrityService;
use App\Services\SettlementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Ports the prototype fixtures into the database.
 *
 * Verified batches are settled through the real SettlementService rather than
 * having ledger rows inserted directly, so a seeded database exercises the same
 * money path production will.
 */
class EcoCycleSeeder extends Seeder
{
    private array $fx;

    public function run(): void
    {
        $this->fx = json_decode(file_get_contents(database_path('seeders/fixtures.json')), true);

        $categories = $this->categories();
        $areas      = $this->areas();
        $facility   = $this->facility($categories);
        $households = $this->households($areas, $categories);
        $agents     = $this->agents();
        $this->staff();
        $this->batches($households, $agents, $categories, $facility);

        $this->command->info('Seeded: ' . User::count() . ' users, '
            . Household::count() . ' households, ' . Agent::count() . ' agents, '
            . WasteBatch::count() . ' batches.');
    }

    private function categories(): array
    {
        $rows = [
            ['organic', 'Organic',  42,  'var(--c1)'],
            ['plastic', 'Plastic',  118, 'var(--c2)'],
            ['paper',   'Paper',    65,  'var(--c3)'],
            ['metal',   'Metal',    210, 'var(--c4)'],
            ['glass',   'Glass',    54,  'var(--c5)'],
            ['ewaste',  'E-waste',  320, 'var(--c6)'],
            ['mixed',   'Mixed',    38,  'var(--text-3)'],
        ];

        $out = [];
        foreach ($rows as $i => [$key, $label, $rate, $color]) {
            $out[$key] = WasteCategory::updateOrCreate(
                ['key' => $key],
                ['label' => $label, 'rate_per_kg' => $rate, 'color' => $color, 'sort' => $i]
            );
        }

        return $out;
    }

    private function areas(): array
    {
        $rows = [
            ['Ikeja', 6.6018, 3.3515], ['Ikoyi', 6.4550, 3.4350],
            ['Agege', 6.6152, 3.3250], ['Yaba', 6.5095, 3.3711],
            ['Ilupeju', 6.5560, 3.3600], ['Magodo', 6.6200, 3.3700],
            ['Mushin', 6.5280, 3.3490], ['Maryland', 6.5710, 3.3670],
            ['Ikorodu', 6.6194, 3.5105], ['Oshodi', 6.5560, 3.3400],
            ['Surulere', 6.4980, 3.3540], ['Ketu', 6.5900, 3.3850],
        ];

        $out = [];
        foreach ($rows as [$name, $lat, $lng]) {
            $out[$name] = Area::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'lat' => $lat, 'lng' => $lng, 'is_active' => true]
            );
        }

        return $out;
    }

    private function facility(array $categories): Facility
    {
        $facility = Facility::updateOrCreate(
            ['code' => 'CO-001'],
            [
                'name'    => config('ecocycle.facility.name'),
                'address' => config('ecocycle.facility.address'),
                'lat'     => config('ecocycle.facility.lat'),
                'lng'     => config('ecocycle.facility.lng'),
                'daily_capacity_kg' => 9500,
            ]
        );

        $lines = [
            ['Anaerobic digester A', 'organic', 5400, 'Biogas · digestate', 'running', 89, 'Approaching ceiling'],
            ['Sorting line B',       'mixed',   2800, 'Recovered recyclables', 'running', 62, 'Nominal'],
            ['Plastic baler',        'plastic', 900,  'Baled PET and HDPE', 'idle', 31, 'Awaiting feedstock'],
            ['Anaerobic digester B', 'organic', 5400, 'Biogas · digestate', 'offline', 0, 'Scheduled maintenance'],
        ];

        foreach ($lines as [$name, $cat, $cap, $out, $status, $util, $note]) {
            ProcessingLine::updateOrCreate(
                ['facility_id' => $facility->id, 'name' => $name],
                [
                    'waste_category_id' => $categories[$cat]->id,
                    'capacity_kg_day' => $cap, 'output_desc' => $out,
                    'status' => $status, 'utilisation' => $util, 'note' => $note,
                ]
            );
        }

        return $facility;
    }

    private function households(array $areas, array $categories): array
    {
        $out = [];
        foreach ($this->fx['households'] as $h) {
            $user = User::updateOrCreate(
                ['email' => $this->email($h['name'])],
                [
                    'name' => $h['name'],
                    'password' => Hash::make('password'),
                    'role' => UserRole::Household,
                    'phone' => $h['phone'],
                    'email_verified_at' => now(),
                ]
            );

            $out[$h['id']] = Household::updateOrCreate(
                ['code' => $h['id']],
                [
                    'user_id' => $user->id,
                    'area_id' => $areas[$h['area']]->id ?? null,
                    'address' => $h['area'] . ', Lagos',
                    'lat' => $h['lat'], 'lng' => $h['lng'],
                    'size' => $h['size'], 'frequency' => $h['freq'],
                    'default_category_id' => $categories[$h['cat']]->id,
                    'status' => $h['status'], 'rating' => $h['rating'],
                ]
            );
        }

        return $out;
    }

    private function agents(): array
    {
        $out = [];
        foreach ($this->fx['agents'] as $a) {
            $user = User::updateOrCreate(
                ['email' => $this->email($a['name'])],
                [
                    'name' => $a['name'],
                    'password' => Hash::make('password'),
                    'role' => UserRole::Agent,
                    'phone' => $a['phone'] ?? null,
                    'email_verified_at' => now(),
                ]
            );

            $out[$a['id']] = Agent::updateOrCreate(
                ['code' => $a['id']],
                [
                    'user_id' => $user->id,
                    'vehicle_type' => $a['vehicle'] ?? 'Tricycle',
                    'capacity_kg' => $a['cap'] ?? 260,
                    'operating_area' => $a['area'] ?? null,
                    'kyc_verified_at' => now()->subMonths(3),
                    'rating' => $a['rating'] ?? 4.8,
                    'is_online' => (bool) ($a['online'] ?? false),
                    'last_lat' => $a['lat'] ?? null,
                    'last_lng' => $a['lng'] ?? null,
                    'last_seen_at' => now()->subMinutes(random_int(1, 90)),
                ]
            );
        }

        return $out;
    }

    private function staff(): void
    {
        User::updateOrCreate(
            ['email' => 'manager@ecocycle.ng'],
            [
                'name' => 'Adaeze Okafor',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'phone' => '0800 123 4567',
                'email_verified_at' => now(),
            ]
        );
    }

    private function batches(array $households, array $agents, array $categories, Facility $facility): void
    {
        $settlement = app(SettlementService::class);
        $integrity  = app(IntegrityService::class);

        foreach ($this->fx['batches'] as $b) {
            $household = $households[$b['hh']] ?? null;
            if (! $household) {
                continue;
            }

            $status = BatchStatus::from($b['status']);
            $agent  = isset($b['agent']) ? ($agents[$b['agent']] ?? null) : null;

            // Fixtures store "minutes ago"; turn them back into real instants.
            $at = fn (?int $mins) => $mins === null ? null : now()->subMinutes($mins);

            $batch = WasteBatch::updateOrCreate(
                ['code' => $b['code']],
                [
                    'ulid' => (string) Str::ulid(),
                    'household_id' => $household->id,
                    'agent_id' => $agent?->id,
                    'facility_id' => $status->step() >= 6 ? $facility->id : null,
                    'waste_category_id' => $categories[$b['cat']]->id,
                    'status' => $status,
                    'expected_kg' => $b['expected_kg'] ?? null,
                    'collected_kg' => $b['collected_kg'] ?? null,
                    'verified_kg' => $b['verified_kg'] ?? null,
                    'grade' => $b['quality'] ?? null,
                    'pickup_lat' => $household->lat,
                    'pickup_lng' => $household->lng,
                    'requested_at' => $at($b['created'] ?? null),
                    'accepted_at' => $at($b['accepted_at'] ?? null),
                    'collected_at' => $at($b['collected_at'] ?? null),
                    'received_at' => $at($b['received_at'] ?? null),
                    'inspecting_at' => $at($b['inspected_at'] ?? null),
                    'verified_at' => $status->isSettleable() ? $at($b['inspected_at'] ?? $b['received_at'] ?? null) : null,
                    'rejected_at' => $status === BatchStatus::Rejected ? $at($b['inspected_at'] ?? null) : null,
                    'processed_at' => $at($b['processed_at'] ?? null),
                    'rejection_reason' => $b['reject_reason'] ?? null,
                ]
            );

            // Real settlement, through the real service, so the ledger is real.
            if ($status->isSettleable() && $batch->verified_kg) {
                $settlement->settle($batch);
                $integrity->evaluateBatch($batch->refresh());
            }
        }

        // A couple of open marketplace jobs so the agent app has something to show.
        foreach ($this->fx['jobs'] as $j) {
            $household = $households[$j['hh']] ?? null;
            if (! $household) {
                continue;
            }

            WasteBatch::updateOrCreate(
                ['code' => 'EC-W-' . now()->year . '-J' . substr($j['code'], -5)],
                [
                    'ulid' => (string) Str::ulid(),
                    'household_id' => $household->id,
                    'waste_category_id' => $categories[$j['cat']]->id,
                    'status' => BatchStatus::Available,
                    'expected_kg' => $j['est_kg'],
                    'pickup_lat' => $household->lat,
                    'pickup_lng' => $household->lng,
                    'requested_at' => now()->subMinutes(random_int(10, 300)),
                    'available_at' => now()->subMinutes(random_int(5, 200)),
                    'window_end' => now()->addHours(random_int(2, 30)),
                ]
            );
        }
    }

    /** Deterministic demo addresses so logins are predictable. */
    private function email(string $name): string
    {
        return Str::slug($name, '.') . '@ecocycle.test';
    }
}
