<?php

namespace App\Livewire\Console;

use App\Models\Agent;
use App\Models\IntegrityFlag;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.console')]
class Agents extends Component
{
    #[Url] public string $q = '';

    public function render()
    {
        $agents = Agent::query()
            ->with('user')
            ->withCount('batches')
            ->when($this->q, function ($q) {
                $t = '%' . $this->q . '%';
                $q->where(fn ($w) => $w->where('code', 'like', $t)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $t)));
            })
            ->orderByDesc('is_online')
            ->orderBy('code')
            ->get();

        return view('livewire.console.agents', [
            'agents'   => $agents,
            'fleet'    => $agents->groupBy('vehicle_type')->map->count(),
            'online'   => $agents->where('is_online', true)->count(),
            'avgScore' => round((float) $agents->avg('integrity_score'), 1),
            'flagged'  => IntegrityFlag::open()->whereNotNull('agent_id')->distinct()->count('agent_id'),
        ])->layoutData(['page' => 'agents', 'heading' => 'Agents']);
    }
}
