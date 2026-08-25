<?php

namespace App\Enums;

/**
 * The WasteBatch state machine.
 *
 * A direct port of EC.STATUS from the prototype, with one upgrade: the
 * prototype registry only described states, this one enforces the transitions
 * between them.
 */
enum BatchStatus: string
{
    case Requested  = 'REQUESTED';
    case Available  = 'AVAILABLE';
    case Accepted   = 'ACCEPTED';
    case Collected  = 'COLLECTED';
    case InTransit  = 'IN_TRANSIT';
    case Received   = 'RECEIVED';
    case Inspecting = 'INSPECTING';
    case Verified   = 'VERIFIED';
    case Rejected   = 'REJECTED';
    case Processing = 'PROCESSING';
    case Processed  = 'PROCESSED';
    case Cancelled  = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Requested  => 'Requested',
            self::Available  => 'Available',
            self::Accepted   => 'Accepted',
            self::Collected  => 'Collected',
            self::InTransit  => 'In transit',
            self::Received   => 'Received',
            self::Inspecting => 'Inspecting',
            self::Verified   => 'Verified',
            self::Rejected   => 'Rejected',
            self::Processing => 'Processing',
            self::Processed  => 'Processed',
            self::Cancelled  => 'Cancelled',
        };
    }

    /** Maps onto the .st-* classes already in the prototype stylesheet. */
    public function cssClass(): string
    {
        return match ($this) {
            self::Requested, self::Cancelled => 'st-requested',
            self::Available  => 'st-available',
            self::Accepted   => 'st-accepted',
            self::Collected  => 'st-collected',
            self::InTransit  => 'st-transit',
            self::Received   => 'st-received',
            self::Inspecting => 'st-inspecting',
            self::Verified   => 'st-verified',
            self::Rejected   => 'st-rejected',
            self::Processing => 'st-processing',
            self::Processed  => 'st-processed',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Requested  => 'bell',
            self::Available  => 'boxes',
            self::Accepted   => 'check',
            self::Collected  => 'package',
            self::InTransit  => 'truck',
            self::Received   => 'building',
            self::Inspecting => 'inspect',
            self::Verified   => 'shield',
            self::Rejected, self::Cancelled => 'ban',
            self::Processing => 'factory',
            self::Processed  => 'recycle',
        };
    }

    /** Position on the household-facing progress rail. */
    public function step(): int
    {
        return match ($this) {
            self::Requested  => 1,
            self::Available  => 2,
            self::Accepted   => 3,
            self::Collected  => 4,
            self::InTransit  => 5,
            self::Received   => 6,
            self::Inspecting => 7,
            self::Verified, self::Rejected => 8,
            self::Processing => 9,
            self::Processed  => 10,
            self::Cancelled  => 0,
        };
    }

    /** @return array<int, self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Requested  => [self::Available, self::Cancelled],
            self::Available  => [self::Accepted, self::Cancelled],
            self::Accepted   => [self::Collected, self::Available, self::Cancelled],
            self::Collected  => [self::InTransit],
            self::InTransit  => [self::Received],
            self::Received   => [self::Inspecting],
            self::Inspecting => [self::Verified, self::Rejected],
            self::Verified   => [self::Processing],
            self::Processing => [self::Processed],
            self::Processed, self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedNext() === [];
    }

    /** Money is only ever released once a batch has been verified. */
    public function isSettleable(): bool
    {
        return in_array($this, [self::Verified, self::Processing, self::Processed], true);
    }

    public function isOpen(): bool
    {
        return ! $this->isTerminal();
    }

    /** The ten states shown on the pipeline board. @return array<int, self> */
    public static function pipeline(): array
    {
        return [
            self::Requested, self::Available, self::Accepted, self::Collected,
            self::InTransit, self::Received, self::Inspecting, self::Verified,
            self::Processing, self::Processed,
        ];
    }
}
