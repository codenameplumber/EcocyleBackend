<?php

namespace App\Models;

use App\Concerns\HasLedger;
use App\Models\Concerns\HasPayoutAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Household extends Model
{
    use HasLedger;
    use HasPayoutAccount;

    protected $fillable = [
        'user_id', 'code', 'area_id', 'address', 'lat', 'lng',
        'size', 'frequency', 'default_category_id', 'status', 'rating', 'preferences',
    ];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'rating' => 'float', 'preferences' => 'array'];
    }

    /**
     * Preference switches, with the shipped defaults.
     *
     * Reading through a default map rather than off the column means a household
     * created before a switch existed behaves as if it had opted in, instead of
     * the screen showing an empty toggle for a notification they are in fact
     * receiving.
     *
     * @var array<string, bool>
     */
    public const PREFERENCE_DEFAULTS = [
        'remind_when_due'    => true,
        'allow_agent_call'   => true,
        'auto_request'       => false,
        'notify_accepted'    => true,
        'notify_verified'    => true,
        'notify_credited'    => true,
        'notify_weekly'      => false,
    ];

    public function pref(string $key): bool
    {
        return (bool) ($this->preferences[$key] ?? self::PREFERENCE_DEFAULTS[$key] ?? false);
    }

    /** @return array<string, bool> */
    public function prefs(): array
    {
        return array_merge(self::PREFERENCE_DEFAULTS, (array) ($this->preferences ?? []));
    }

    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function area(): BelongsTo      { return $this->belongsTo(Area::class); }
    public function batches(): HasMany     { return $this->hasMany(WasteBatch::class); }

    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(WasteCategory::class, 'default_category_id');
    }

    public function name(): string
    {
        return $this->user?->name ?? $this->code;
    }

    /**
     * Volume prediction, deliberately not machine learning: the mean of the
     * last eight verified weights. One query, explainable to a household, and
     * good enough to beat asking them to guess.
     */
    public function predictedKg(): float
    {
        $recent = $this->batches()
            ->whereNotNull('verified_kg')
            ->latest('verified_at')
            ->limit(8)
            ->pluck('verified_kg');

        if ($recent->isEmpty()) {
            return round(max(4, $this->size * 3.2), 1);
        }

        return round((float) $recent->avg(), 1);
    }

    /**
     * Mean verified weight per collection.
     *
     * Verified only: averaging in what an agent declared, or what the household
     * guessed, would give a figure that no money was ever paid on.
     */
    public function averageKg(): float
    {
        return round((float) $this->batches()->whereNotNull('verified_kg')->avg('verified_kg'), 2);
    }

    public function lifetimeKg(): float
    {
        return (float) $this->batches()->sum('verified_kg');
    }
}
