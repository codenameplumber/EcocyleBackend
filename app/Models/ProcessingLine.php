<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessingLine extends Model
{
    protected $fillable = [
        'facility_id', 'waste_category_id', 'name', 'capacity_kg_day',
        'output_desc', 'status', 'utilisation', 'note',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WasteCategory::class, 'waste_category_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ProcessingRun::class);
    }

    public function badgeClass(): string
    {
        return match ($this->status) {
            'running' => 'badge-green',
            'idle'    => 'badge-amber',
            default   => '',
        };
    }
}
