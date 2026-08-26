<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\WasteBatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Volume prediction, and the honest accounting of how well it has done.
 *
 * The method is a seasonal mean: for each weekday, the average verified weight
 * that weekday produced over the trailing window. Rubbish arrives on a weekly
 * rhythm — Saturday is not Tuesday — so a weekday mean beats a global mean, and
 * both beat a fitted curve on ninety days of data.
 *
 * This is deliberately not a model. It is one query and a division, it is
 * explainable to a household in a sentence, and `accuracy()` reports its real
 * error rather than a number chosen to look good on a dashboard.
 */
class ForecastService
{
    /** How far back the weekday means are computed over. */
    private const WINDOW_DAYS = 56;

    /**
     * The next seven days of predicted intake.
     *
     * @return list<array{date: Carbon, label: string, kg: float}>
     */
    public function week(): array
    {
        $means = $this->weekdayMeans();
        $overall = count($means) ? array_sum($means) / count($means) : 0.0;

        $out = [];
        for ($i = 1; $i <= 7; $i++) {
            $date = now()->addDays($i)->startOfDay();
            $out[] = [
                'date' => $date,
                'label' => $date->format('D'),
                // A weekday with no history falls back to the overall mean rather
                // than predicting zero arrivals.
                'kg' => round($means[(int) $date->dayOfWeek] ?? $overall, 1),
            ];
        }

        return $out;
    }

    /** Total predicted intake over the next seven days, in kg. */
    public function weekTotal(): float
    {
        return round(array_sum(array_column($this->week(), 'kg')), 1);
    }

    /** What the plant can take over the same seven days, in kg. */
    public function weekCapacity(): float
    {
        $daily = (float) (Facility::first()?->daily_capacity_kg ?: 9500);

        // Intake runs Monday to Saturday; Sunday the gate is shut.
        return round($daily * 6, 1);
    }

    /**
     * How far the prediction has been off, as a percentage of actual.
     *
     * Computed by replaying the same weekday-mean method against days we now
     * know the answer for. Returns null when there is not enough history to say
     * anything — a confidence badge with no evidence behind it is worse than no
     * badge at all.
     */
    public function accuracy(): ?float
    {
        $actual = $this->dailyTotals(28);
        if (count($actual) < 10) {
            return null;
        }

        $means = $this->weekdayMeans();
        $errors = [];

        foreach ($actual as $date => $kg) {
            $predicted = $means[(int) Carbon::parse($date)->dayOfWeek] ?? null;
            if ($predicted === null || $kg <= 0) {
                continue;
            }
            $errors[] = abs($predicted - $kg) / $kg;
        }

        if (! $errors) {
            return null;
        }

        return round(max(0, 100 - array_sum($errors) / count($errors) * 100), 1);
    }

    /**
     * Arrivals by hour of day, 0..23, in kg — what drives inspection staffing.
     *
     * @return list<float>
     */
    public function byHour(): array
    {
        $rows = DB::table('waste_batches')
            ->whereNotNull('received_at')
            ->where('received_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->groupByRaw($this->hourExpr())
            ->selectRaw($this->hourExpr() . ' as h, SUM(COALESCE(collected_kg, 0)) as kg')
            ->pluck('kg', 'h');

        return collect(range(0, 23))->map(fn ($h) => round((float) ($rows[$h] ?? 0), 1))->all();
    }

    /**
     * The two busiest hours, so the copy under the chart can name them rather
     * than describe a shape the reader has to find for themselves.
     *
     * @return list<int>
     */
    public function peakHours(): array
    {
        $hours = $this->byHour();
        arsort($hours);

        return array_slice(array_keys($hours), 0, 2);
    }

    // ----------------------------------------------------------------- shared

    /**
     * Hour-of-day extraction, spelled the way the current driver spells it.
     * The test suite runs on SQLite and production on MySQL; this is the one
     * place the difference shows up.
     */
    private function hourExpr(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', received_at) AS INTEGER)"
            : 'HOUR(received_at)';
    }

    /**
     * Mean verified kg per calendar weekday over the trailing window.
     *
     * @return array<int, float> 0 = Sunday
     */
    private function weekdayMeans(): array
    {
        $totals = $this->dailyTotals(self::WINDOW_DAYS);

        $buckets = [];
        foreach ($totals as $date => $kg) {
            $buckets[(int) Carbon::parse($date)->dayOfWeek][] = $kg;
        }

        return array_map(fn ($v) => array_sum($v) / count($v), $buckets);
    }

    /** @return array<string, float> date => verified kg */
    private function dailyTotals(int $days): array
    {
        return WasteBatch::query()
            ->whereNotNull('verified_at')
            ->where('verified_at', '>=', now()->subDays($days)->startOfDay())
            ->groupByRaw('DATE(verified_at)')
            ->selectRaw('DATE(verified_at) as d, SUM(verified_kg) as kg')
            ->pluck('kg', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
