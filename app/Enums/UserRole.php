<?php

namespace App\Enums;

enum UserRole: string
{
    case Household = 'household';
    case Agent     = 'agent';
    case Staff     = 'staff';
    case Admin     = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Household => 'Household',
            self::Agent     => 'Collection agent',
            self::Staff     => 'Facility staff',
            self::Admin     => 'Administrator',
        };
    }

    /** Where this role lands after signing in. */
    public function home(): string
    {
        return match ($this) {
            self::Household => '/app',
            self::Agent     => '/agent',
            self::Staff, self::Admin => '/console',
        };
    }

    public function isFacility(): bool
    {
        return in_array($this, [self::Staff, self::Admin], true);
    }
}
