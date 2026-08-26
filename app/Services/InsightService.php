<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Models\Agent;
use App\Models\Area;
use App\Models\Facility;
use App\Models\WasteBatch;
use App\Models\WasteCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The insight cards that appear on the marketing site, the facility overview and
 * the analytics screen.
 *
 * These are arithmetic, not a model. Every card states a figure that came out of
 * a query, and its confidence is a function of how much evidence stood behind
 * that query — not a number the card asserts about itself. A card with three
 * batches behind it says so.
 *
 * OpenAiService phrases answers to free-text questions; this computes the
 * standing ones. Keeping them apart means the dashboard still works, and still
 * tells the truth, with no API key configured.
 */
class InsightService
{
    /** @var list<string> the tags a caller can filter on */
    public const TAGS = ['Capacity', 'Forecast', 'Integrity', 'Yield', 'Coverage'];

    /**
     * @return list<array{tag: string, title: string, body: string, conf: float}>
     */
    public function all(): array
    {
        // The marketing pages hit this on every request and the underlying
        // aggregates move on the scale of hours, not seconds.
        return Cache::remember('ecocycle.insights', now()->addMinutes(10), function () {
            return array_values(array_filter([
                $this->capacity(),
                $this->forecast(),
                $this->integrity(),
                $this->yield(),
                $this->coverage(),
            ]));
        });
    }

    /**
     * @param  list<string>  $tags
     * @return list<array{tag: string, title: string, body: string, conf: float}>
     */
    public function tagged(array $tags): array
    {
        return array_values(array_filter($this->all(), fn ($i) => in_array($i['tag'], $tags, true)));
    }

    // ------------------------------------------------------------------ cards

    private function capacity(): ?array
    {
        $facility = Facility::first();
        $daily = (float) ($facility?->daily_capacity_kg ?: 8200);

        $recent = $this->dailyVerified(14);
        if (count($recent) < 3) {
            return null;
        }

        $mean = array_sum($recent) / count($recent);
        $used = $daily > 0 ? min(100, $mean / $daily * 100) : 0;
        $headroom = max(0, $daily - $mean);

        return [
            'tag' => 'Capacity',
            'title' => sprintf('Intake is running at %.0f%% of plant capacity', $used),
            'body' => sprintf(
                'The last %d days averaged %s kg through the gate against a rated %s kg/day, leaving %s kg of headroom. %s',
                count($recent),
                number_format($mean, 0),
                number_format($daily, 0),
                number_format($headroom, 0),
                $used > 85
                    ? 'A second digester line will be needed before the next intake step-up.'
                    : 'Current lines absorb the volume without a second shift.',
            ),
            'conf' => $this->confidence(count($recent), 14),
        ];
    }

    private function forecast(): ?array
    {
        $recent = $this->dailyVerified(28);
        if (count($recent) < 8) {
            return null;
        }

        // Trailing halves, not a regression: with four weeks of data a slope
        // fitted through daily noise claims more precision than it has.
        $half = intdiv(count($recent), 2);
        $older = array_slice($recent, 0, $half);
        $newer = array_slice($recent, $half);

        $a = array_sum($older) / max(1, count($older));
        $b = array_sum($newer) / max(1, count($newer));
        $delta = $a > 0 ? ($b - $a) / $a * 100 : 0;
        $next = $b * 7 * (1 + $delta / 100);

        return [
            'tag' => 'Forecast',
            'title' => sprintf('Next week projects to %s kg', number_format($next, 0)),
            'body' => sprintf(
                'Daily verified weight moved %s%.1f%% between the two halves of the last %d days. Projecting that rate forward gives %s kg over the coming seven days.',
                $delta >= 0 ? '+' : '',
                $delta,
                count($recent),
                number_format($next, 0),
            ),
            'conf' => $this->confidence(count($recent), 28),
        ];
    }

    private function integrity(): ?array
    {
        $rows = DB::table('waste_batches')
            ->join('agents', 'agents.id', '=', 'waste_batches.agent_id')
            ->whereNotNull('verified_kg')
            ->where('collected_kg', '>', 0)
            ->where('waste_batches.created_at', '>=', now()->subDays(60))
            ->groupBy('agents.id', 'agents.code')
            ->havingRaw('COUNT(*) >= 5')
            ->selectRaw('agents.code, COUNT(*) as n,
                AVG((waste_batches.verified_kg - waste_batches.collected_kg) / waste_batches.collected_kg * 100) as drift')
            ->orderBy('drift')
            ->limit(3)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $tolerance = (float) config('ecocycle.variance_tolerance', 5.0);
        $outside = $rows->filter(fn ($r) => abs((float) $r->drift) > $tolerance);

        if ($outside->isEmpty()) {
            return [
                'tag' => 'Integrity',
                'title' => 'Every agent is declaring inside tolerance',
                'body' => sprintf(
                    'The widest average gap between a declared weight and the gate scale is %.1f%%, against a %.1f%% tolerance. No collector needs a review this cycle.',
                    abs((float) $rows->first()->drift),
                    $tolerance,
                ),
                'conf' => $this->confidence((int) $rows->sum('n'), 60),
            ];
        }

        $worst = $outside->first();

        return [
            'tag' => 'Integrity',
            'title' => sprintf('%d agent%s declaring outside tolerance', $outside->count(), $outside->count() === 1 ? '' : 's'),
            'body' => sprintf(
                'Agent %s averages %.1f%% against the gate scale across %d batches, past the %.1f%% tolerance. Settlement is unaffected — money already follows the verified weight — but the pattern is worth a conversation.',
                $worst->code,
                (float) $worst->drift,
                (int) $worst->n,
                $tolerance,
            ),
            'conf' => $this->confidence((int) $outside->sum('n'), 60),
        ];
    }

    private function yield(): ?array
    {
        $rows = DB::table('waste_batches')
            // The inspector's confirmed stream wins over what the household declared.
            ->join('waste_categories', 'waste_categories.id', '=',
                DB::raw('COALESCE(waste_batches.confirmed_category_id, waste_batches.waste_category_id)'))
            ->whereNotNull('verified_kg')
            ->where('waste_batches.created_at', '>=', now()->subDays(60))
            ->groupBy('waste_categories.id', 'waste_categories.label', 'waste_categories.rate_per_kg')
            ->selectRaw('waste_categories.label, waste_categories.rate_per_kg,
                SUM(waste_batches.verified_kg) as kg,
                SUM(waste_batches.verified_kg * waste_categories.rate_per_kg) as value')
            ->get();

        if ($rows->count() < 2) {
            return null;
        }

        $totalKg = (float) $rows->sum('kg');
        $totalValue = (float) $rows->sum('value');
        if ($totalKg <= 0 || $totalValue <= 0) {
            return null;
        }

        $top = $rows->sortByDesc('value')->first();
        $valueShare = (float) $top->value / $totalValue * 100;
        $weightShare = (float) $top->kg / $totalKg * 100;

        return [
            'tag' => 'Yield',
            'title' => sprintf('%s carries %.0f%% of settled value', $top->label, $valueShare),
            'body' => sprintf(
                '%s is %.0f%% of the tonnage but %.0f%% of the value at ₦%s/kg. Volume and value do not move together, so a tonnage target on its own would steer the network the wrong way.',
                $top->label,
                $weightShare,
                $valueShare,
                number_format((float) $top->rate_per_kg, 0),
            ),
            'conf' => $this->confidence((int) $rows->count() * 20, 140),
        ];
    }

    private function coverage(): ?array
    {
        $recent = $this->areaWeight(30);
        $prior = $this->areaWeight(60, 30);

        if (count($recent) < 3) {
            return null;
        }

        $best = null;
        foreach ($recent as $areaId => $kg) {
            $was = $prior[$areaId] ?? 0.0;
            if ($was < 50) {   // too small a base for a percentage to mean anything
                continue;
            }
            $growth = ($kg - $was) / $was * 100;
            if ($best === null || $growth > $best['growth']) {
                $best = ['area_id' => $areaId, 'growth' => $growth, 'kg' => $kg];
            }
        }

        if ($best === null) {
            return null;
        }

        $area = Area::find($best['area_id']);
        $households = $area?->households()->count() ?? 0;

        return [
            'tag' => 'Coverage',
            'title' => sprintf('%s is growing fastest at %s%.1f%%', $area?->name ?? 'An area', $best['growth'] >= 0 ? '+' : '', $best['growth']),
            'body' => sprintf(
                '%s put %s kg through the gate in the last 30 days from %d registered households. It is the strongest candidate for the next agent posting.',
                $area?->name ?? 'The area',
                number_format($best['kg'], 0),
                $households,
            ),
            'conf' => $this->confidence(count($recent) * 10, 120),
        ];
    }

    // ----------------------------------------------------------------- shared

    /** @return list<float> verified kg per day, most recent last, empty days dropped */
    private function dailyVerified(int $days): array
    {
        return DB::table('waste_batches')
            ->whereNotNull('verified_kg')
            ->where('verified_at', '>=', now()->subDays($days))
            ->groupByRaw('DATE(verified_at)')
            ->orderByRaw('DATE(verified_at)')
            ->selectRaw('SUM(verified_kg) as kg')
            ->pluck('kg')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /** @return array<int, float> area id => verified kg in the window */
    private function areaWeight(int $daysAgo, int $until = 0): array
    {
        return DB::table('waste_batches')
            ->join('households', 'households.id', '=', 'waste_batches.household_id')
            ->whereNotNull('waste_batches.verified_kg')
            ->where('waste_batches.verified_at', '>=', now()->subDays($daysAgo))
            ->when($until > 0, fn ($q) => $q->where('waste_batches.verified_at', '<', now()->subDays($until)))
            ->groupBy('households.area_id')
            ->selectRaw('households.area_id, SUM(waste_batches.verified_kg) as kg')
            ->pluck('kg', 'area_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * Confidence is evidence, expressed on 0..1 and capped short of certainty.
     * A card built on three rows should not read 97%.
     */
    private function confidence(int $observed, int $ideal): float
    {
        return round(0.45 + 0.49 * min(1.0, $observed / max(1, $ideal)), 2);
    }
}
