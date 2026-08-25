<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Real QR codes, rendered as inline SVG.
 *
 * The prototype drew a decorative block pattern that encoded nothing. This
 * encodes the batch URL, so a phone camera at the gate resolves straight to
 * the batch record.
 */
class QrService
{
    public function svg(string $text, int $size = 132): string
    {
        $matrix = Encoder::encode($text, ErrorCorrectionLevel::M())->getMatrix();

        $n = $matrix->getWidth();
        $cell = $size / $n;
        $rects = '';

        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($matrix->get($x, $y)) {
                    // The half-pixel bleed stops hairline gaps between modules.
                    $rects .= sprintf(
                        '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f"/>',
                        $x * $cell, $y * $cell, $cell + 0.06, $cell + 0.06
                    );
                }
            }
        }

        return sprintf(
            '<svg class="qr" viewBox="0 0 %d %d" role="img" aria-label="Batch tag %s">%s</svg>',
            $size, $size, htmlspecialchars($text, ENT_QUOTES), $rects
        );
    }
}
