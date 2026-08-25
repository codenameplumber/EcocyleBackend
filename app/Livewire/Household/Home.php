<?php

namespace App\Livewire\Household;

use App\Models\WasteBatch;
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

        return view('livewire.household.home', [
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
