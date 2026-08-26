<?php

namespace App\Livewire\Household;

use App\Models\WasteBatch;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.phone')]
class Home extends Component
{
    public function render()
    {
        $household = auth()->user()->household;

        $active = $household->batches()
            ->open()
            ->with(['agent.user', 'category'])
            ->latest('requested_at')
            ->first();

        $recent = $household->batches()
            ->with('category')
            ->latest('requested_at')
            ->limit(4)
            ->get();

        $monthKg = (float) $household->batches()
            ->where('verified_at', '>=', now()->startOfMonth())
            ->sum('verified_kg');

        // The household's own stream mix over the last 90 days, off the
        // inspector's confirmed category rather than what was declared.
        $mix = $household->batches()
            ->whereNotNull('verified_kg')
            ->where('verified_at', '>=', now()->subDays(90))
            ->join('waste_categories', 'waste_categories.id', '=',
                DB::raw('COALESCE(waste_batches.confirmed_category_id, waste_batches.waste_category_id)'))
            ->groupBy('waste_categories.label', 'waste_categories.color')
            ->selectRaw('waste_categories.label, waste_categories.color, SUM(verified_kg) AS kg')
            ->orderByDesc('kg')
            ->get();

        $lifetime = $household->lifetimeKg();

        // Only the organic fraction becomes biogas, so the impact figures are
        // computed off that share rather than off everything ever sent.
        $organicKg = (float) $household->batches()
            ->whereNotNull('verified_kg')
            ->join('waste_categories', 'waste_categories.id', '=',
                DB::raw('COALESCE(waste_batches.confirmed_category_id, waste_batches.waste_category_id)'))
            ->where('waste_categories.key', 'organic')
            ->sum('verified_kg');

        return view('livewire.household.home', [
            'mix'        => $mix,
            // Last eight verified weights, oldest first — the same series the
            // prediction on the request screen is computed from.
            'trend'      => $household->batches()->whereNotNull('verified_kg')
                ->latest('verified_at')->limit(8)->pluck('verified_kg')
                ->reverse()->values()->all(),
            'mixTotal'   => max(0.01, (float) $mix->sum('kg')),
            // 120 m3 of biogas per tonne of organics; 0.86 kg CO2e avoided per kg
            // kept out of an uncontrolled dump.
            'biogasM3'   => $organicKg / 1000 * 120,
            'co2eKg'     => $lifetime * 0.86,
            'household' => $household,
            'balance'   => $household->balance(),
            'pending'   => $household->pendingAmount(),
            'monthKg'   => $monthKg,
            'monthBatches' => $household->batches()->where('verified_at', '>=', now()->startOfMonth())->count(),
            'lifetimeKg'   => $household->lifetimeKg(),
            'predicted'    => $household->predictedKg(),
            'active'       => $active,
            'recent'       => $recent,
        ])->layoutData([
            'app'     => 'household',
            'page'    => 'home',
            'heading' => 'Good ' . (now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening')),
            'sub'     => auth()->user()->name . ' · ' . ($household->area?->name ?? 'Lagos'),
        ]);
    }
}
