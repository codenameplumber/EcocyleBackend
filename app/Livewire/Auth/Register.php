<?php

namespace App\Livewire\Auth;

use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Area;
use App\Models\Household;
use App\Models\User;
use App\Models\WasteCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Four steps: role, details, location, done.
 *
 * Coordinates matter more than the written address -- they are what decides
 * which agents ever see this household's jobs -- so step three captures them
 * from the browser and stores them on the profile.
 */
#[Layout('layouts.auth')]
class Register extends Component
{
    public int $step = 1;

    public string $role = 'household';
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';

    // Household
    public int $size = 4;
    public string $frequency = 'Weekly';
    public string $category = 'organic';

    // Agent
    public string $vehicle = 'Tricycle';
    public int $capacity = 260;

    // Location
    public ?int $area_id = null;
    public string $address = '';
    public ?float $lat = null;
    public ?float $lng = null;
    public ?string $locateMessage = null;

    public ?string $createdCode = null;

    public function mount(): void
    {
        $this->area_id = Area::where('is_active', true)->value('id');
    }

    public function next(): void
    {
        if ($this->step === 2) {
            $this->validate([
                'name'     => 'required|string|min:2|max:120',
                'email'    => 'required|email|unique:users,email',
                'phone'    => 'required|string|min:7|max:20',
                'password' => 'required|string|min:8',
            ]);
        }

        if ($this->step === 3) {
            $this->validate([
                'area_id' => 'required|exists:areas,id',
                'address' => 'required|string|min:4|max:180',
            ]);

            $this->createAccount();
        }

        $this->step = min(4, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    /** Coordinates pushed up from navigator.geolocation in the browser. */
    public function setCoords(float $lat, float $lng, ?float $accuracy = null): void
    {
        $this->lat = round($lat, 7);
        $this->lng = round($lng, 7);
        $this->locateMessage = $accuracy
            ? 'Coordinates captured (±' . round($accuracy) . ' m)'
            : 'Coordinates captured';
    }

    private function createAccount(): void
    {
        DB::transaction(function () {
            $role = UserRole::from($this->role);

            $user = User::create([
                'name'     => $this->name,
                'email'    => $this->email,
                'phone'    => $this->phone,
                'password' => Hash::make($this->password),
                'role'     => $role,
            ]);

            $area = Area::find($this->area_id);

            if ($role === UserRole::Agent) {
                $agent = Agent::create([
                    'user_id'        => $user->id,
                    'code'           => 'EC-A-' . str_pad((string) (Agent::count() + 43), 4, '0', STR_PAD_LEFT),
                    'vehicle_type'   => $this->vehicle,
                    'capacity_kg'    => $this->capacity,
                    'operating_area' => $area?->name,
                    'last_lat'       => $this->lat ?? $area?->lat,
                    'last_lng'       => $this->lng ?? $area?->lng,
                ]);
                $this->createdCode = $agent->code;
            } else {
                $household = Household::create([
                    'user_id'   => $user->id,
                    'code'      => 'EC-H-' . str_pad((string) (Household::count() + 184), 4, '0', STR_PAD_LEFT),
                    'area_id'   => $area?->id,
                    'address'   => $this->address,
                    'lat'       => $this->lat ?? $area?->lat,
                    'lng'       => $this->lng ?? $area?->lng,
                    'size'      => $this->size,
                    'frequency' => $this->frequency,
                    'default_category_id' => WasteCategory::where('key', $this->category)->value('id'),
                ]);
                $this->createdCode = $household->code;
            }

            Auth::login($user);
        });
    }

    public function finish()
    {
        return redirect(Auth::user()->role->home());
    }

    public function render()
    {
        return view('livewire.auth.register', [
            'areas'      => Area::where('is_active', true)->orderBy('name')->get(),
            'categories' => WasteCategory::active()->get(),
        ]);
    }
}
