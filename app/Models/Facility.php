<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    protected $fillable = [
        'code', 'name', 'address', 'lat', 'lng',
        'daily_capacity_kg', 'intake_open', 'intake_close',
    ];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProcessingLine::class);
    }
}
