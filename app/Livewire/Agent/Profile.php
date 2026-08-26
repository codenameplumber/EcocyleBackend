<?php

namespace App\Livewire\Agent;

use App\Models\Agent;
use Illuminate\Validation\Rule;
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

    /** @var array<string, bool> */
    public array $prefs = [];

    /** @var list<int> 1 = Monday */
    public array $days = [];

    public string $from = '07:00';
    public string $until = '19:00';

    // --- payout account ---------------------------------------------------
    public ?string $payoutBank = null;
    public string $payoutAccount = '';
    public string $payoutCycle = 'Weekly, every Monday';
    public bool $payoutAuto = true;
    public float $payoutThreshold = 0;
    public bool $showPayout = false;

    public function mount(): void
    {
        $u = auth()->user(); $a = $u->agent;
        $this->name = $u->name;
        $this->phone = (string) $u->phone;
        $this->vehicle_type = $a->vehicle_type;
        $this->capacity_kg = $a->capacity_kg;
        $this->plate = (string) $a->plate;
        $this->operating_area = (string) $a->operating_area;

        $this->prefs = $a->prefs();
        $av = $a->availability();
        $this->days = $av['days'];
        $this->from = $av['from'];
        $this->until = $av['until'];

        $pa = $a->payoutAccount();
        $this->payoutBank = $pa['bank'];
        $this->payoutCycle = $pa['cycle'];
        $this->payoutAuto = $pa['auto'];
        $this->payoutThreshold = $pa['threshold'];
    }

    public function openPayout(): void
    {
        $this->showPayout = true;
    }

    /** The bank arrangement every settlement this agent earns is paid into. */
    public function savePayout(): void
    {
        $this->validate([
            'payoutBank'      => ['required', 'string', Rule::in(Agent::PAYOUT_BANKS)],
            'payoutCycle'     => ['required', 'string', Rule::in(Agent::PAYOUT_CYCLES)],
            'payoutThreshold' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'payoutAccount'   => [
                auth()->user()->agent->hasPayoutAccount() ? 'nullable' : 'required',
                'string', 'min:4', 'max:32',
            ],
        ], [], [
            'payoutBank' => 'bank', 'payoutAccount' => 'account number',
            'payoutCycle' => 'payout cycle', 'payoutThreshold' => 'threshold',
        ]);

        auth()->user()->agent->savePayoutAccount(
            $this->payoutBank, $this->payoutAccount ?: null,
            $this->payoutCycle, $this->payoutAuto, $this->payoutThreshold,
        );

        $this->payoutAccount = '';
        $this->showPayout = false;

        $this->dispatch('toast', message: 'Payout account saved', kind: 'ok');
    }

    /** Switches and shift days save on the flip, not on the form's Save. */
    public function toggle(string $key): void
    {
        if (! array_key_exists($key, Agent::PREFERENCE_DEFAULTS)) {
            return;
        }

        $this->prefs[$key] = ! ($this->prefs[$key] ?? false);
        $this->persist();

        $this->dispatch('toast', message: 'Preference saved', kind: 'ok');
    }

    public function toggleDay(int $day): void
    {
        $this->days = in_array($day, $this->days, true)
            ? array_values(array_diff($this->days, [$day]))
            : array_values(array_merge($this->days, [$day]));

        sort($this->days);
        $this->persist();
    }

    public function saveHours(): void
    {
        $this->validate([
            'from'  => ['required', 'date_format:H:i'],
            'until' => ['required', 'date_format:H:i', 'after:from'],
        ]);

        $this->persist();
        $this->dispatch('toast', message: 'Shift hours saved', kind: 'ok');
    }

    private function persist(): void
    {
        // Only this screen's own keys. `prefs` is a public Livewire array that
        // also carries the payout arrangement, and writing the whole blob back
        // would let an edited payload rewrite a bank account.
        auth()->user()->agent->mergePreferences(
            array_intersect_key($this->prefs, Agent::PREFERENCE_DEFAULTS) + [
                'days'  => $this->days,
                'from'  => $this->from,
                'until' => $this->until,
            ]
        );
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
            'docs'     => $a->docs(),
            'account'  => $a->payoutAccount(),
            'banks'    => Agent::PAYOUT_BANKS,
            'cycles'   => Agent::PAYOUT_CYCLES,
        ])->layoutData([
            'app' => 'agent', 'page' => 'profile',
            'heading' => 'Profile', 'sub' => 'Agent ' . $a->code,
        ]);
    }
}
