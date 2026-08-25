<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payout extends Model
{
    protected $fillable = [
        'reference', 'party_type', 'party_id', 'amount',
        'method', 'status', 'note', 'paid_at', 'actioned_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function party(): MorphTo { return $this->morphTo(); }

    public function entries(): HasMany { return $this->hasMany(LedgerEntry::class); }

    public function badgeClass(): string
    {
        return match ($this->status) {
            'paid'       => 'badge-green',
            'pending'    => 'badge-amber',
            'processing' => 'badge-blue',
            'held'       => 'badge-red',
            'failed'     => 'badge-red',
            default      => '',
        };
    }

    public function partyLabel(): string
    {
        return class_basename($this->party_type);
    }
}
