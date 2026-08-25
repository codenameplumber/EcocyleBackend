<?php

namespace App\Livewire\Household;

use App\Models\Area;
use App\Models\WasteCategory;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.phone')]
class Profile extends Component
{
    public string $name = '';
    public string $phone = '';
    public string $address = '';
    public int $size = 4;
    public string $frequency = 'Weekly';
    public ?int $area_id = null;
    public ?int $default_category_id = null;

    public function mount(): void
    {
        $u = auth()->user();
        $h = $u->household;

        $this->name = $u->name;
        $this->phone = (string) $u->phone;
        $this->address = (string) $h->address;
        $this->size = $h->size;
        $this->frequency = $h->frequency;
        $this->area_id = $h->area_id;
        $this->default_category_id = $h->default_category_id;
    }

    public function save(): void
    {
        $this->validate([
            'name'    => 'required|string|min:2|max:120',
            'phone'   => 'required|string|min:7|max:20',
            'address' => 'nullable|string|max:180',
            'size'    => 'required|integer|min:1|max:30',
        ]);

        $u = auth()->user();
        $u->update(['name' => $this->name, 'phone' => $this->phone]);
        $u->household->update([
            'address' => $this->address,
            'size' => $this->size,
            'frequency' => $this->frequency,
            'area_id' => $this->area_id,
            'default_category_id' => $this->default_category_id,
        ]);

        $this->dispatch('toast', message: 'Profile updated', kind: 'ok');
    }

    public function render()
    {
        $h = auth()->user()->household;

        return view('livewire.household.profile', [
            'household'  => $h,
            'areas'      => Area::orderBy('name')->get(),
            'categories' => WasteCategory::active()->get(),
            'lifetimeKg' => $h->lifetimeKg(),
            'batchCount' => $h->batches()->count(),
        ])->layoutData([
            'app' => 'household', 'page' => 'profile',
            'heading' => 'Profile', 'sub' => 'Household ' . $h->code,
        ]);
    }
}
