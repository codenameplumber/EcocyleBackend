<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WasteCategory extends Model
{
    protected $fillable = ['key', 'label', 'rate_per_kg', 'color', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['rate_per_kg' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('sort');
    }
}
