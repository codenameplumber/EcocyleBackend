<?php

namespace App\Livewire\Console;

use App\Enums\BatchStatus;
use App\Models\Agent;
use App\Models\IntegrityFlag;
use App\Models\LedgerEntry;
use App\Models\ProcessingLine;
use App\Models\WasteBatch;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.console')]
class Overview extends Component
{
    public function render()
    {
        $today = now()->startOfDay();

        $intakeToday = (float) WasteBatch::whereDate('received_at', $today)->sum('collected_kg');
        $processedToday = (float) WasteBatch::whereDate('processed_at', $today)->sum('verified_kg');
        $awaiting = WasteBatch::awaitingInspection();
        $awaitingKg = (float) $awaiting->clone()->sum(DB::raw('COALESCE(collected_kg, expected_kg, 0)'));

        // Fourteen days of received vs processed, for the intake chart.
        $days = collect(range(13, 0))->map(fn ($d) => now()->subDays($d)->toDateString());
        $received = $days->map(fn ($d) => (float) WasteBatch::whereDate('received_at', $d)->sum('collected_kg'));
        $processed = $days->map(fn ($d) => (float) WasteBatch::whereDate('processed_at', $d)->sum('verified_kg'));

        $mix = WasteBatch::query()
            ->settleable()
            ->join('waste_categories', 'waste_categories.id', '=', DB::raw('COALESCE(waste_batches.confirmed_category_id, waste_batches.waste_category_id)'))
            ->groupBy('waste_categories.label', 'waste_categories.color')
            ->selectRaw('waste_categories.label, waste_categories.color, SUM(verified_kg) AS kg')
            ->orderByDesc('kg')
            ->get();
        $mixTotal = max(0.01, (float) $mix->sum('kg'));

        return view('livewire.console.overview', [
            'intakeToday'    => $intakeToday,
            'processedToday' => $processedToday,
            'awaitingCount'  => $awaiting->clone()->count(),
            'awaitingKg'     => $awaitingKg,
            'settledMonth'   => (float) LedgerEntry::where('direction', 'credit')
                ->where('created_at', '>=', now()->startOfMonth())->sum('amount'),
            'openAlerts'     => IntegrityFlag::open()->count(),
            'agentsOnline'   => Agent::where('is_online', true)->count(),
            'inTransit'      => WasteBatch::where('status', BatchStatus::InTransit->value)->count(),
            'chartLabels'    => $days->map(fn ($d) => \Carbon\Carbon::parse($d)->format('j M')),
            'chartReceived'  => $received,
            'chartProcessed' => $processed,
            'mix'            => $mix,
            'mixTotal'       => $mixTotal,
            'lines'          => ProcessingLine::with('category')->get(),
            'counts'         => collect(BatchStatus::pipeline())
                ->mapWithKeys(fn ($s) => [$s->value => WasteBatch::where('status', $s->value)->count()]),
            'alerts'         => IntegrityFlag::open()->with(['agent.user', 'batch'])->latest()->limit(5)->get(),
            'latest'         => WasteBatch::with(['household.user', 'agent.user', 'category'])
                ->latest('created_at')->limit(8)->get(),
        ])->layoutData(['page' => 'overview', 'heading' => 'Overview']);
    }
}
