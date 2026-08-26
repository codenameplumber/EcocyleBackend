<?php

namespace App\Livewire\Household;

use App\Models\Area;
use Illuminate\Validation\Rule;
use App\Support\Geo;
use App\Models\Household;
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

    /** @var array<string, bool> */
    public array $prefs = [];

    // --- payout account ---------------------------------------------------
    public ?string $payoutBank = null;
    public string $payoutAccount = '';
    public string $payoutCycle = 'On request';
    public bool $payoutAuto = false;
    public float $payoutThreshold = 10000;
    public bool $showPayout = false;

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
        $this->prefs = $h->prefs();

        $pa = $h->payoutAccount();
        $this->payoutBank = $pa['bank'];
        $this->payoutCycle = $pa['cycle'];
        $this->payoutAuto = $pa['auto'];
        $this->payoutThreshold = $pa['threshold'];
    }

    public function openPayout(): void
    {
        $this->showPayout = true;
    }

    /**
     * Save the bank arrangement money is actually paid into.
     *
     * Wallet refuses a withdrawal without one, so until this existed a household
     * seeded without an account had no way out of that refusal.
     */
    public function savePayout(): void
    {
        $this->validate([
            'payoutBank'      => ['required', 'string', Rule::in(Household::PAYOUT_BANKS)],
            'payoutCycle'     => ['required', 'string', Rule::in(Household::PAYOUT_CYCLES)],
            'payoutThreshold' => ['required', 'numeric', 'min:0', 'max:10000000'],
            // Required only when there is nothing on file yet: re-saving to flip
            // the auto switch should not demand the number be retyped.
            'payoutAccount'   => [
                auth()->user()->household->hasPayoutAccount() ? 'nullable' : 'required',
                'string', 'min:4', 'max:32',
            ],
        ], [], [
            'payoutBank' => 'bank', 'payoutAccount' => 'account number',
            'payoutCycle' => 'payout cycle', 'payoutThreshold' => 'threshold',
        ]);

        auth()->user()->household->savePayoutAccount(
            $this->payoutBank, $this->payoutAccount ?: null,
            $this->payoutCycle, $this->payoutAuto, $this->payoutThreshold,
        );

        $this->payoutAccount = '';
        $this->showPayout = false;

        $this->dispatch('toast', message: 'Payout account saved', kind: 'ok');
    }

    /**
     * Switches save on the flip rather than waiting for the form.
     *
     * A toggle that silently needs a separate Save is the classic way a
     * notification preference gets ignored — the user turns it off, leaves, and
     * keeps getting the emails.
     */
    public function toggle(string $key): void
    {
        if (! array_key_exists($key, Household::PREFERENCE_DEFAULTS)) {
            return;
        }

        $this->prefs[$key] = ! ($this->prefs[$key] ?? false);

        // Only the flipped key is written. `prefs` is a public Livewire array
        // that also carries the payout arrangement, and writing the whole blob
        // back would let an edited payload rewrite a bank account.
        auth()->user()->household->mergePreferences([$key => $this->prefs[$key]]);

        $this->dispatch('toast', message: 'Preference saved', kind: 'ok');
    }

    public function setCategory(int $id): void
    {
        $this->default_category_id = $id;
        auth()->user()->household->update(['default_category_id' => $id]);

        $this->dispatch('toast', message: 'Usual stream updated', kind: 'ok');
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
        $u->household->mergePreferences(
            array_intersect_key($this->prefs, Household::PREFERENCE_DEFAULTS)
        );
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
            // Agents match on these coordinates, not on the written address, so
            // the household should be able to see where the pin actually sits.
            'pins'       => $h->lat
                ? [Geo::pin((float) $h->lat, (float) $h->lng, 'house', label: 'Home')]
                : [],
            'areas'      => Area::orderBy('name')->get(),
            'categories' => WasteCategory::active()->get(),
            'account'    => $h->payoutAccount(),
            'banks'      => Household::PAYOUT_BANKS,
            'cycles'     => Household::PAYOUT_CYCLES,
            'lifetimeKg' => $h->lifetimeKg(),
            'batchCount' => $h->batches()->count(),
        ])->layoutData([
            'app' => 'household', 'page' => 'profile',
            'heading' => 'Profile', 'sub' => 'Household ' . $h->code,
        ]);
    }
}
