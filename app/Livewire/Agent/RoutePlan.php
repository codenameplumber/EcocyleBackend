<?php

namespace App\Livewire\Agent;

use App\Services\JobMatcher;
use Livewire\Attributes\Layout;
use App\Models\Facility;
use App\Support\Geo;
use App\Livewire\Concerns\TracksAgentPosition;
use Livewire\Component;

#[Layout('layouts.phone')]
class RoutePlan extends Component
{
    use TracksAgentPosition;

    public function render(JobMatcher $matcher)
    {
        $agent = auth()->user()->agent;

        $stops = $agent->batches()
            ->whereIn('status', ['ACCEPTED', 'COLLECTED'])
            ->with(['household.user', 'household.area', 'category'])
            ->get();

        $ordered = $matcher->orderRoute($agent, $stops);

        // Cumulative load along the round, so an over-capacity stop is visible
        // before the agent drives to it.
        $running = $agent->loadOnBoardKg();
        $load = [];
        foreach ($ordered as $s) {
            $running += (float) ($s->collected_kg ?? $s->expected_kg ?? 0);
            $load[] = round($running, 1);
        }

        $distance = 0;
        $lat = $agent->last_lat; $lng = $agent->last_lng;
        foreach ($ordered as $s) {
            $distance += $matcher->distanceKm($lat, $lng, $s->pickup_lat, $s->pickup_lng);
            $lat = $s->pickup_lat; $lng = $s->pickup_lng;
        }
        $distance += $matcher->distanceKm($lat, $lng, config('ecocycle.facility.lat'), config('ecocycle.facility.lng'));

        // Start pin, then each stop in the order it will actually be driven,
        // then the plant. The numbers on the pins and the numbers in the list
        // below are the same ordering, which is the whole point of the screen.
        $facility = Facility::first();
        $pins = [];

        if ($agent->last_lat) {
            $pins[] = Geo::pin((float) $agent->last_lat, (float) $agent->last_lng,
                'agent', live: true, label: 'Start');
        }

        foreach ($ordered->values() as $i => $stop) {
            if ($stop->household?->lat) {
                $pins[] = Geo::pin((float) $stop->household->lat, (float) $stop->household->lng,
                    'house', label: (string) ($i + 1));
            }
        }

        $pins[] = Geo::pin(
                (float) ($facility?->lat ?? config('ecocycle.facility.lat')),
                (float) ($facility?->lng ?? config('ecocycle.facility.lng')),
                'plant', label: 'Facility'
            );

        return view('livewire.agent.route-plan', [
            'agent'    => $agent,
            'pins'     => $pins,
            'stops'    => $ordered,
            'load'     => $load,
            'distance' => round($distance, 1),
            'toCollect'=> (float) $ordered->sum(fn ($s) => $s->collected_kg ?? $s->expected_kg ?? 0),
        ])->layoutData([
            'app' => 'agent', 'page' => 'route',
            'heading' => "Today's route",
            'sub' => $ordered->count() . ' stops · ends at the facility',
        ]);
    }
}
