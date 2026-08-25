<?php

namespace App\Models;

use App\Concerns\HasLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use HasLedger;

    protected $fillable = [
        'user_id', 'code', 'vehicle_type', 'capacity_kg', 'plate',
        'operating_area', 'kyc_verified_at', 'integrity_score', 'rating',
        'is_online', 'last_lat', 'last_lng', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'kyc_verified_at' => 'datetime', 'last_seen_at' => 'datetime',
            'is_online' => 'boolean', 'integrity_score' => 'float',
            'rating' => 'float', 'last_lat' => 'float', 'last_lng' => 'float',
        ];
    }

    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function batches(): HasMany     { return $this->hasMany(WasteBatch::class); }
    public function deliveries(): HasMany  { return $this->hasMany(Delivery::class); }
    public function flags(): HasMany       { return $this->hasMany(IntegrityFlag::class); }

    public function name(): string
    {
        return $this->user?->name ?? $this->code;
    }

    /** Kilograms currently on board -- collected but not yet delivered. */
    public function loadOnBoardKg(): float
    {
        return (float) $this->batches()
            ->whereIn('status', ['COLLECTED', 'IN_TRANSIT'])
            ->sum('collected_kg');
    }

    public function remainingCapacityKg(): float
    {
        return max(0, $this->capacity_kg - $this->loadOnBoardKg());
    }

    /** Mean absolute variance between what this agent declares and the scale. */
    public function weightAccuracy(): float
    {
        $avg = $this->batches()->whereNotNull('variance_pct')->avg(\Illuminate\Support\Facades\DB::raw('ABS(variance_pct)'));

        return $avg === null ? 100.0 : round(100 - (float) $avg, 1);
    }

    public function tripsCompleted(): int
    {
        return $this->batches()->whereIn('status', ['VERIFIED', 'PROCESSING', 'PROCESSED'])->count();
    }
}
