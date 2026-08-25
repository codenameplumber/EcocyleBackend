<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    protected $fillable = [
        'code', 'agent_id', 'facility_id', 'gross_kg', 'tare_kg', 'net_kg',
        'weighbridge', 'weighed_at', 'weighed_by', 'status',
    ];

    protected function casts(): array
    {
        return [
            'gross_kg' => 'float', 'tare_kg' => 'float', 'net_kg' => 'float',
            'weighed_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo    { return $this->belongsTo(Agent::class); }
    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function batches(): HasMany    { return $this->hasMany(WasteBatch::class); }

    /** Sum of what the agent declared across the batches on this vehicle. */
    public function declaredKg(): float
    {
        return (float) $this->batches()->sum('collected_kg');
    }
}
