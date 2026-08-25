<?php

namespace App\Models;

use App\Enums\LedgerDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'waste_batch_id', 'payout_id', 'party_type', 'party_id',
        'direction', 'amount', 'kind', 'idempotency_key', 'memo', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => LedgerDirection::class,
            'amount'    => 'decimal:2',
            'created_at'=> 'datetime',
        ];
    }

    public function party(): MorphTo    { return $this->morphTo(); }
    public function batch(): BelongsTo  { return $this->belongsTo(WasteBatch::class, 'waste_batch_id'); }
    public function payout(): BelongsTo { return $this->belongsTo(Payout::class); }

    public function signedAmount(): float
    {
        return (float) $this->amount * $this->direction->sign();
    }
}
