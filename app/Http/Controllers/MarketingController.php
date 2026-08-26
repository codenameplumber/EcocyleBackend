<?php

namespace App\Http\Controllers;

use App\Enums\BatchStatus;
use App\Models\Agent;
use App\Models\Area;
use App\Models\Facility;
use App\Models\Household;
use App\Models\LedgerEntry;
use App\Models\ProcessingLine;
use App\Models\WasteBatch;
use App\Models\WasteCategory;
use App\Services\InsightService;
use App\Support\Geo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The four public pages.
 *
 * The prototype filled these sections from fixtures in the browser. Every figure
 * here is a query instead, which is the point of the exercise: a visitor reading
 * "82.4 t collected this month" is reading the same number the facility console
 * is reading.
 *
 * Nothing on these pages is behind auth, so the aggregates are cached briefly
 * rather than recomputed for every crawler.
 */
class MarketingController extends Controller
{
    public function __construct(private InsightService $insights)
    {
    }

    public function home()
    {
        return view('marketing.home', [
            'stats' => $this->stats(),
            'stages' => $this->stages(),
            'categories' => $this->categories(),
            'pins' => $this->pins(),
            'insights' => array_slice($this->insights->all(), 0, 3),
        ]);
    }

    public function about()
    {
        return view('marketing.about', [
            'stats' => $this->stats(),
            'categories' => $this->categories(),
        ]);
    }

    public function facilities()
    {
        return view('marketing.facilities', [
            'facility' => Facility::first(),
            'lines' => ProcessingLine::with('category')->orderBy('id')->get(),
            'categories' => $this->categories(),
            'forecast' => $this->insights->tagged(['Capacity', 'Forecast']),
            'output' => $this->output(),
            'stats' => $this->stats(),
        ]);
    }

    public function location()
    {
        return view('marketing.location', [
            'pins' => $this->pins(),
            'areas' => $this->areas(),
            'facility' => Facility::first(),
            'stats' => $this->stats(),
        ]);
    }

    // ------------------------------------------------------------------ data

    /** @return array<string, float|int> */
    private function stats(): array
    {
        return Cache::remember('ecocycle.public.stats', now()->addMinutes(5), function () {
            $monthKg = (float) WasteBatch::whereNotNull('verified_kg')
                ->where('verified_at', '>=', now()->startOfMonth())
                ->sum('verified_kg');

            return [
                'households' => Household::count(),
                'agents' => Agent::count(),
                'agents_online' => Agent::where('is_online', true)->count(),
                'areas' => Area::count(),
                'month_kg' => $monthKg,
                'total_kg' => (float) WasteBatch::sum('verified_kg'),
                // Statuses are stored upper-case (the BatchStatus enum's backing
                // values). Lower-case here matched nothing, so the hero read
                // "0.0 kg in transit" no matter what was on the road.
                'in_transit_kg' => (float) WasteBatch::whereIn('status', [
                    BatchStatus::InTransit->value, BatchStatus::Collected->value,
                ])->sum('collected_kg'),
                'settled' => (float) LedgerEntry::where('direction', 'credit')->sum('amount'),
                // Landfill diversion at 0.86 kg CO2e per kg of organic waste kept
                // out of an uncontrolled dump, the figure LAWMA works from.
                'co2e_kg' => $monthKg * 0.86,
            ];
        });
    }

    /**
     * Seven rows off an indexed table. Caching it would mean serialising Eloquent
     * models into the cache store, which costs more than the query it saves and
     * breaks the moment a model's shape changes.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, WasteCategory>
     */
    private function categories()
    {
        return WasteCategory::active()->orderBy('sort')->get();
    }

    /**
     * Household and agent positions, plus the plant. Capped: the canvas is a
     * schematic and two hundred overlapping pins says less than sixty.
     *
     * @return list<array<string, mixed>>
     */
    private function pins(): array
    {
        return Cache::remember('ecocycle.public.pins', now()->addMinutes(5), function () {
            $pins = Household::query()
                ->whereNotNull('lat')
                ->inRandomOrder()
                ->limit(60)
                ->get(['lat', 'lng'])
                ->map(fn ($h) => Geo::pin((float) $h->lat, (float) $h->lng, 'house'))
                ->all();

            foreach (Agent::where('is_online', true)->whereNotNull('last_lat')->limit(20)->get(['last_lat', 'last_lng']) as $a) {
                $pins[] = Geo::pin((float) $a->last_lat, (float) $a->last_lng, 'agent', live: true);
            }

            $f = Facility::first();
            $pins[] = Geo::pin((float) ($f?->lat ?? 6.5794), (float) ($f?->lng ?? 3.3792), 'plant', label: $f?->name ?? 'Lagos Facility');

            return $pins;
        });
    }

    /**
     * Per-area volume, growth against the prior window, and share of network.
     *
     * @return list<array<string, mixed>>
     */
    private function areas(): array
    {
        return Cache::remember('ecocycle.public.areas', now()->addMinutes(10), function () {
            $now = $this->areaKg(30, 0);
            $prior = $this->areaKg(60, 30);

            $rows = Area::withCount('households')->get()->map(function ($a) use ($now, $prior) {
                $kg = $now[$a->id] ?? 0.0;
                $was = $prior[$a->id] ?? 0.0;

                return [
                    'name' => $a->name,
                    'households' => $a->households_count,
                    'kg' => $kg,
                    // A percentage off a near-zero base is noise, so a cold area
                    // reports no growth rather than a spectacular one.
                    'growth' => $was >= 20 ? ($kg - $was) / $was * 100 : 0.0,
                ];
            })->sortByDesc('kg')->values();

            $total = max(1.0, (float) $rows->sum('kg'));
            $max = max(1.0, (float) $rows->max('kg'));

            return $rows->map(fn ($r) => $r + [
                'share' => $r['kg'] / $total * 100,
                'bar' => $r['kg'] / $max * 100,
            ])->all();
        });
    }

    /** @return array<int, float> */
    private function areaKg(int $from, int $until): array
    {
        return DB::table('waste_batches')
            ->join('households', 'households.id', '=', 'waste_batches.household_id')
            ->whereNotNull('waste_batches.verified_kg')
            ->where('waste_batches.verified_at', '>=', now()->subDays($from))
            ->when($until > 0, fn ($q) => $q->where('waste_batches.verified_at', '<', now()->subDays($until)))
            ->groupBy('households.area_id')
            ->selectRaw('households.area_id, SUM(waste_batches.verified_kg) as kg')
            ->pluck('kg', 'area_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * What an average day produces, derived from the streams actually verified
     * over the last 30 days rather than a brochure figure.
     *
     * The conversion factors are the plant's rated yields: 120 m3 of biogas and
     * 0.38 t of digestate per tonne of organics, 0.82 recovery on dry
     * recyclables. They are constants because they are engineering, not data --
     * but the tonnage they multiply is real.
     *
     * @return array<string, float>
     */
    private function output(): array
    {
        return Cache::remember('ecocycle.public.output', now()->addMinutes(30), function () {
            $byStream = DB::table('waste_batches')
                ->join('waste_categories', 'waste_categories.id', '=',
                    DB::raw('COALESCE(waste_batches.confirmed_category_id, waste_batches.waste_category_id)'))
                ->whereNotNull('waste_batches.verified_kg')
                ->where('waste_batches.verified_at', '>=', now()->subDays(30))
                ->groupBy('waste_categories.key')
                ->selectRaw('waste_categories.key, SUM(waste_batches.verified_kg) as kg')
                ->pluck('kg', 'key')
                ->map(fn ($v) => (float) $v);

            $days = 30;
            $organic = ($byStream['organic'] ?? 0) / $days;
            $dry = collect(['plastic', 'paper', 'metal', 'glass', 'ewaste'])
                ->sum(fn ($k) => ($byStream[$k] ?? 0)) / $days;
            $mixed = ($byStream['mixed'] ?? 0) / $days;

            $recovered = $dry * 0.82;
            $residual = $mixed * 0.62 + $dry * 0.18;
            $total = max(0.001, $organic + $dry + $mixed);

            return [
                'biogas_m3' => $organic / 1000 * 120,
                'digestate_kg' => $organic * 0.38,
                'recovered_kg' => $recovered,
                'residual_kg' => $residual,
                'diversion_pct' => (1 - $residual / $total) * 100,
                'co2e_kg' => ($total - $residual) * 0.86,
                'total_kg' => $total,
            ];
        });
    }

    /** The six-stage editorial sequence. Copy, not data — it describes the machine. */
    private function stages(): array
    {
        return [
            ['img' => 'flow-requested', 't' => 'Requested', 's' => 'A household signals that waste is ready and estimates the volume.'],
            ['img' => 'flow-assigned', 't' => 'Assigned', 's' => 'The job is ranked and offered to the agents best placed to take it.'],
            ['img' => 'flow-collected', 't' => 'Collected', 's' => 'The agent weighs the load, tags it with a QR batch code and declares a weight.'],
            ['img' => 'flow-transit', 't' => 'In transit', 's' => 'The batch travels to the facility on whatever the agent drives.'],
            ['img' => 'flow-inspected', 't' => 'Inspected', 's' => 'The gate scale gives the verified weight. Everything settles on this number.'],
            ['img' => 'flow-processed', 't' => 'Processed', 's' => 'The material enters a line and the output is recorded against the batch.'],
        ];
    }
}
