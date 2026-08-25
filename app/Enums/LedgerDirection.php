<?php

namespace App\Enums;

enum LedgerDirection: string
{
    case Credit = 'credit';
    case Debit  = 'debit';

    public function sign(): int
    {
        return $this === self::Credit ? 1 : -1;
    }
}
