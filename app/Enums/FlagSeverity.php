<?php

namespace App\Enums;

enum FlagSeverity: string
{
    case Low    = 'low';
    case Medium = 'medium';
    case High   = 'high';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Low    => '',
            self::Medium => 'badge-amber',
            self::High   => 'badge-red',
        };
    }
}
