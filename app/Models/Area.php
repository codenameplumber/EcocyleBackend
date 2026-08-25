<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    protected $fillable = ['name', 'slug', 'lat', 'lng', 'is_active'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'is_active' => 'boolean'];
    }

    public function households(): HasMany
    {
        return $this->hasMany(Household::class);
    }
}
