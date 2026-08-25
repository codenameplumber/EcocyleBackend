<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\WasteBatch;
use Illuminate\Support\Str;

/**
 * Human-readable codes.
 *
 * The ULID is the real identity and can be minted on a phone with no signal;
 * this code is the label a person reads off a QR sticker. It is assigned when
 * the record reaches the server, so an offline collector is never blocked.
 */
class BatchCodeGenerator
{
    public function batchCode(): string
    {
        $year = now()->year;
        $seq  = WasteBatch::whereYear('created_at', $year)->count() + 91_000;

        return sprintf('EC-W-%d-%07d', $year, $seq);
    }

    public function deliveryCode(): string
    {
        $year = now()->year;
        $seq  = Delivery::whereYear('created_at', $year)->count() + 100;

        return sprintf('EC-D-%d-%06d', $year, $seq);
    }

    public function ulid(): string
    {
        return (string) Str::ulid();
    }
}
