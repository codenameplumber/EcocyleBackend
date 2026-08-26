<?php

namespace App\Models;

use App\Enums\FlagSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrityFlag extends Model
{
    protected $fillable = [
        'waste_batch_id', 'agent_id', 'household_id', 'kind', 'severity',
        'detail', 'evidence', 'status', 'resolved_at', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'severity' => FlagSeverity::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WasteBatch::class, 'waste_batch_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function scopeOpen($q)
    {
        return $q->where('status', 'open');
    }

    /** Human label for the detection type. */
    public function kindLabel(): string
    {
        // Labels come from the detector registry so the case list and the
        // "what the system watches for" grid can never name the same signal
        // two different ways.
        foreach (\App\Services\IntegrityService::SIGNALS as $s) {
            if ($s['key'] === $this->kind) {
                return $s['name'];
            }
        }

        return ucfirst(str_replace('_', ' ', (string) $this->kind));
    }
}
