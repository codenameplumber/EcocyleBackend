<?php

namespace App\Livewire\Console;

use App\Models\Area;
use App\Models\LedgerEntry;
use App\Models\WasteBatch;
use App\Services\ClaudeService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Plain-language questions over the waste graph.
 *
 * The model never sees the database and is never asked to compute anything.
 * We aggregate first, hand it real figures, and ask it only to phrase them —
 * which is why an answer here cannot invent a number.
 */
#[Layout('layouts.console')]
class Analytics extends Component
{
    public string $question = '';
    public ?array $answer = null;
    public bool $thinking = false;

    public array $suggestions = [
        'How much have we verified this month?',
        'Which area generates the most waste?',
        'What is our reject rate telling us?',
        'Where is our margin actually coming from?',
    ];

    public function ask(ClaudeService $ai): void
    {
        if (trim($this->question) === '') {
            return;
        }

        $this->thinking = true;
        $this->answer = $ai->answer($this->question, $this->facts());
        $this->thinking = false;
    }

    public function useSuggestion(string $q, ClaudeService $ai): void
    {
        $this->question = $q;
        $this->ask($ai);
    }

    /** Everything the model is allowed to know, computed by us. */
    private function facts(): array
    {
        $month = now()->startOfMonth();

        $byCategory = WasteBatch::query()
            ->settleable()
            ->join('waste_categories', 'waste_categories.id', '=',
                DB::raw('COALESCE(waste_batches.confirmed_category_id, waste_batches.waste_category_id)'))
            ->groupBy('waste_categories.label')
            ->selectRaw('waste_categories.label,
                         ROUND(SUM(verified_kg),1) AS kg,
                         ROUND(SUM(value_naira),2) AS value')
            ->get()
            ->keyBy('label')
            ->map(fn ($r) => ['kg' => (float) $r->kg, 'value_ngn' => (float) $r->value])
            ->toArray();

        $byArea = Area::withCount('households')->get()->mapWithKeys(fn ($a) => [
            $a->name => [
                'households' => $a->households_count,
                'kg' => round((float) WasteBatch::whereHas('household', fn ($h) => $h->where('area_id', $a->id))
                    ->sum('verified_kg'), 1),
            ],
        ])->toArray();

        $total = WasteBatch::count();
        $rejected = WasteBatch::where('status', 'REJECTED')->count();

        return [
            'currency'            => 'NGN',
            'facility'            => config('ecocycle.facility.name'),
            'batch_count'         => $total,
            'area_count'          => Area::count(),
            'household_count'     => \App\Models\Household::count(),
            'agent_count'         => \App\Models\Agent::count(),
            'intake_today_kg'     => round((float) WasteBatch::whereDate('received_at', today())->sum('collected_kg'), 1),
            'verified_kg_month'   => round((float) WasteBatch::where('verified_at', '>=', $month)->sum('verified_kg'), 1),
            'verified_kg_total'   => round((float) WasteBatch::sum('verified_kg'), 1),
            'settled_month_naira' => round((float) LedgerEntry::where('direction', 'credit')
                ->where('created_at', '>=', $month)->sum('amount'), 2),
            'reject_rate_pct'     => $total ? round($rejected / $total * 100, 1) : 0,
            'avg_variance_pct'    => round((float) WasteBatch::whereNotNull('variance_pct')->avg('variance_pct'), 2),
            'split'               => ['household' => 0.6, 'agent' => 0.4],
            'by_category'         => $byCategory,
            'by_area'             => $byArea,
        ];
    }

    public function render(ClaudeService $ai)
    {
        $days = collect(range(6, 0))->map(fn ($d) => now()->subDays($d));
        $intake = $days->map(fn ($d) => (float) WasteBatch::whereDate('received_at', $d)->sum('collected_kg'));

        // Where the margin actually comes from -- the finding that shapes strategy.
        $margin = WasteBatch::query()
            ->settleable()
            ->join('waste_categories', 'waste_categories.id', '=',
                DB::raw('COALESCE(waste_batches.confirmed_category_id, waste_batches.waste_category_id)'))
            ->groupBy('waste_categories.label', 'waste_categories.color')
            ->selectRaw('waste_categories.label, waste_categories.color,
                         SUM(verified_kg) AS kg, SUM(value_naira) AS value')
            ->orderByDesc('value')
            ->get();

        return view('livewire.console.analytics', [
            'aiEnabled'  => $ai->enabled(),
            'labels'     => $days->map(fn ($d) => $d->format('D')),
            'intake'     => $intake,
            'margin'     => $margin,
            'marginKg'   => max(0.01, (float) $margin->sum('kg')),
            'marginNgn'  => max(0.01, (float) $margin->sum('value')),
        ])->layoutData(['page' => 'analytics', 'heading' => 'Analytics']);
    }
}
