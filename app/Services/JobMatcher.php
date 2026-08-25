<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Models\Agent;
use App\Models\WasteBatch;
use Illuminate\Support\Collection;

/**
 * Ranks the open marketplace for one agent.
 *
 * Deliberately arithmetic rather than a model. Distance, capacity fit, stream
 * value and urgency explain themselves to a collector who asks why a job was
 * offered, which matters more at pilot scale than a marginal accuracy gain.
 *
 * The value weighting is not incidental: organics are most of the tonnage and
 * almost none of the margin, so biasing rounds toward the streams that pay is
 * what keeps a round profitable.
 */
class JobMatcher
{
    public function rank(Agent $agent, int $limit = 20): Collection
    {
        $lat = $agent->last_lat ?? config('ecocycle.facility.lat');
        $lng = $agent->last_lng ?? config('ecocycle.facility.lng');
        $remaining = $agent->remainingCapacityKg();

        $batches = WasteBatch::query()
            ->where('status', BatchStatus::Available->value)
            ->with(['household.user', 'household.area', 'category'])
            ->get();

        return $batches
            ->map(function (WasteBatch $b) use ($lat, $lng, $remaining) {
                $km = $this->distanceKm($lat, $lng, $b->pickup_lat ?? $b->household?->lat, $b->pickup_lng ?? $b->household?->lng);
                $b->setAttribute('distance_km', round($km, 1));
                $b->setAttribute('match_score', $this->score($b, $km, $remaining));
                $b->setAttribute('fits_capacity', ($b->expected_kg ?? 0) <= $remaining);

                return $b;
            })
            ->sortByDesc('match_score')
            ->take($limit)
            ->values();
    }

    /** 0-100. Higher is a better job for this agent right now. */
    private function score(WasteBatch $batch, float $km, float $remaining): int
    {
        // Proximity: full marks under 1 km, nothing beyond 10.
        $proximity = max(0, 1 - ($km / 10)) * 45;

        // Capacity fit: a job that does not fit is close to useless this trip.
        $kg = (float) ($batch->expected_kg ?? 0);
        $capacity = $kg <= 0 ? 10 : ($kg <= $remaining ? 25 : 2);

        // Value density: naira per kilometre travelled.
        $rate = (float) ($batch->category?->rate_per_kg ?? 0);
        $value = $kg * $rate;
        $perKm = $km > 0.2 ? $value / $km : $value;
        $valueScore = min(20, $perKm / 200);

        // Urgency: a job whose collection window is closing scores higher.
        $urgency = 0;
        if ($batch->window_end) {
            $hours = max(0, now()->diffInHours($batch->window_end, false));
            $urgency = $hours <= 0 ? 10 : max(0, 10 - $hours);
        }

        return (int) round(min(100, $proximity + $capacity + $valueScore + $urgency));
    }

    /** Haversine. At a few hundred households this beats needing PostGIS. */
    public function distanceKm(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): float
    {
        if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
            return 99.0;
        }

        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Order a set of stops nearest-neighbour from the agent's position, then
     * home to the facility. Good enough for five stops; swap for a real solver
     * when rounds get long.
     */
    public function orderRoute(Agent $agent, Collection $batches): Collection
    {
        $lat = $agent->last_lat ?? config('ecocycle.facility.lat');
        $lng = $agent->last_lng ?? config('ecocycle.facility.lng');

        $remaining = $batches->values();
        $ordered = collect();

        while ($remaining->isNotEmpty()) {
            $next = $remaining->sortBy(fn ($b) => $this->distanceKm(
                $lat, $lng, $b->pickup_lat ?? $b->household?->lat, $b->pickup_lng ?? $b->household?->lng
            ))->first();

            $ordered->push($next);
            $lat = $next->pickup_lat ?? $next->household?->lat ?? $lat;
            $lng = $next->pickup_lng ?? $next->household?->lng ?? $lng;
            $remaining = $remaining->reject(fn ($b) => $b->id === $next->id)->values();
        }

        return $ordered;
    }
}
