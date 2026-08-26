<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessingRun extends Model
{
    protected $fillable = [
        'code', 'processing_line_id', 'input_kg', 'output_kg',
        'output_desc', 'yield_pct', 'status', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'input_kg' => 'float', 'output_kg' => 'float', 'yield_pct' => 'float',
            'started_at' => 'datetime', 'finished_at' => 'datetime',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(ProcessingLine::class, 'processing_line_id');
    }

    /** The batches this run consumed -- what makes the output traceable. */
    public function batches(): HasMany
    {
        return $this->hasMany(WasteBatch::class);
    }

    public function badgeClass(): string
    {
        return match ($this->status) {
            'complete' => 'badge-green',
            'running'  => 'badge-cyan',
            'failed'   => 'badge-red',
            default    => '',
        };
    }
}
