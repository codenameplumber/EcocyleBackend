<?php

namespace App\Livewire\Agent;

use App\Services\JobMatcher;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.phone')]
class RoutePlan extends Component
{
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

        return view('livewire.agent.route-plan', [
            'agent'    => $agent,
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
