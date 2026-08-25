<?php

namespace App\Livewire\Agent;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.phone')]
class Profile extends Component
{
    public string $name = '';
    public string $phone = '';
    public string $vehicle_type = '';
    public int $capacity_kg = 260;
    public string $plate = '';
    public string $operating_area = '';

    public function mount(): void
    {
        $u = auth()->user(); $a = $u->agent;
        $this->name = $u->name;
        $this->phone = (string) $u->phone;
        $this->vehicle_type = $a->vehicle_type;
        $this->capacity_kg = $a->capacity_kg;
        $this->plate = (string) $a->plate;
        $this->operating_area = (string) $a->operating_area;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|min:2|max:120',
            'phone' => 'required|string|min:7|max:20',
            'capacity_kg' => 'required|integer|min:10|max:5000',
        ]);

        $u = auth()->user();
        $u->update(['name' => $this->name, 'phone' => $this->phone]);
        $u->agent->update([
            'vehicle_type' => $this->vehicle_type,
            'capacity_kg' => $this->capacity_kg,
            'plate' => $this->plate ?: null,
            'operating_area' => $this->operating_area ?: null,
        ]);

        $this->dispatch('toast', message: 'Profile updated', kind: 'ok');
    }

    public function render()
    {
        $a = auth()->user()->agent;

        return view('livewire.agent.profile', [
            'agent'    => $a,
            'accuracy' => $a->weightAccuracy(),
            'trips'    => $a->tripsCompleted(),
            'lifetime' => (float) $a->batches()->sum('verified_kg'),
            'earned'   => $a->totalEarned(),
            'rejected' => $a->batches()->where('status', 'REJECTED')->count(),
            'flags'    => $a->flags()->open()->count(),
        ])->layoutData([
            'app' => 'agent', 'page' => 'profile',
            'heading' => 'Profile', 'sub' => 'Agent ' . $a->code,
        ]);
    }
}
