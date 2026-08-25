<?php

namespace App\Models;

use App\Enums\BatchStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The centre of gravity. Everything traceable hangs off this record.
 */
class WasteBatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status'          => BatchStatus::class,
            'expected_kg'     => 'float',
            'collected_kg'    => 'float',
            'verified_kg'     => 'float',
            'variance_pct'    => 'float',
            'rate_applied'    => 'decimal:2',
            'value_naira'     => 'decimal:2',
            'household_share' => 'decimal:2',
            'agent_share'     => 'decimal:2',
            'contamination'   => 'array',
            'classification'  => 'array',
            'pickup_lat'      => 'float',
            'pickup_lng'      => 'float',
            'window_start'    => 'datetime',
            'window_end'      => 'datetime',
            'requested_at'    => 'datetime',
            'available_at'    => 'datetime',
            'accepted_at'     => 'datetime',
            'collected_at'    => 'datetime',
            'in_transit_at'   => 'datetime',
            'received_at'     => 'datetime',
            'inspecting_at'   => 'datetime',
            'verified_at'     => 'datetime',
            'rejected_at'     => 'datetime',
            'processing_at'   => 'datetime',
            'processed_at'    => 'datetime',
        ];
    }

    // --- relations --------------------------------------------------------

    public function household(): BelongsTo { return $this->belongsTo(Household::class); }
    public function agent(): BelongsTo     { return $this->belongsTo(Agent::class); }
    public function facility(): BelongsTo  { return $this->belongsTo(Facility::class); }
    public function delivery(): BelongsTo  { return $this->belongsTo(Delivery::class); }
    public function events(): HasMany      { return $this->hasMany(BatchEvent::class)->orderBy('created_at'); }
    public function flags(): HasMany       { return $this->hasMany(IntegrityFlag::class); }
    public function ledgerEntries(): HasMany { return $this->hasMany(LedgerEntry::class); }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WasteCategory::class, 'waste_category_id');
    }

    public function confirmedCategory(): BelongsTo
    {
        return $this->belongsTo(WasteCategory::class, 'confirmed_category_id');
    }

    /** What the batch was finally graded as, falling back to the declaration. */
    public function effectiveCategory(): ?WasteCategory
    {
        return $this->confirmedCategory ?: $this->category;
    }

    // --- scopes -----------------------------------------------------------

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNotIn('status', ['PROCESSED', 'REJECTED', 'CANCELLED']);
    }

    public function scopeAwaitingInspection(Builder $q): Builder
    {
        return $q->whereIn('status', ['RECEIVED', 'INSPECTING']);
    }

    public function scopeSettleable(Builder $q): Builder
    {
        return $q->whereIn('status', ['VERIFIED', 'PROCESSING', 'PROCESSED']);
    }

    public function scopeForMarketplace(Builder $q): Builder
    {
        return $q->where('status', BatchStatus::Available->value);
    }

    // --- derived ----------------------------------------------------------

    /** The weight a screen should show: verified if we have it, else declared. */
    public function bestKnownKg(): ?float
    {
        return $this->verified_kg ?? $this->collected_kg ?? $this->expected_kg;
    }

    public function varianceIsOutOfTolerance(): bool
    {
        $tolerance = (float) config('ecocycle.variance_tolerance');

        return $this->variance_pct !== null && abs($this->variance_pct) > $tolerance;
    }

    public function isSettled(): bool
    {
        return $this->ledgerEntries()->where('kind', 'settlement')->exists();
    }

    /** Short display form, e.g. EC-W-20...91827 */
    public function shortCode(): string
    {
        $c = (string) $this->code;

        return strlen($c) > 14
            ? substr($c, 0, 8) . '…' . substr($c, -5)
            : $c;
    }

    public function timeline(): array
    {
        $map = [
            'requested_at'  => BatchStatus::Requested,
            'available_at'  => BatchStatus::Available,
            'accepted_at'   => BatchStatus::Accepted,
            'collected_at'  => BatchStatus::Collected,
            'in_transit_at' => BatchStatus::InTransit,
            'received_at'   => BatchStatus::Received,
            'inspecting_at' => BatchStatus::Inspecting,
            'verified_at'   => BatchStatus::Verified,
            'rejected_at'   => BatchStatus::Rejected,
            'processing_at' => BatchStatus::Processing,
            'processed_at'  => BatchStatus::Processed,
        ];

        $out = [];
        foreach ($map as $column => $state) {
            if ($this->{$column}) {
                $out[] = ['status' => $state, 'at' => $this->{$column}];
            }
        }

        return $out;
    }
}
