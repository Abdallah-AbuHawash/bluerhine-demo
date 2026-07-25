<?php

namespace Tests\Fixtures;

use App\Services\Cutting\Piece;
use App\Services\Cutting\Sheet;

/**
 * Shared cut lists for the engine tests. Every piece here fits the usable area
 * of the standard 2440 x 1220 sheet unrotated, so FixedOrientation never
 * errors and the never-worse property can be compared list by list.
 */
final class CutLists
{
    public static function standardSheet(): Sheet
    {
        return new Sheet(2440, 1220);
    }

    /** @return array<string, array<int, Piece>> */
    public static function all(): array
    {
        return [
            'ps1_panels' => self::ps1Panels(),
            'whatsapp_mix' => self::whatsappMix(),
            'tall_strips' => [
                new Piece(300, 1150, 6, 'STRIP-300'),
                new Piece(220, 900, 4, 'STRIP-220'),
            ],
            'wide_bands' => [
                new Piece(2400, 150, 5, 'BAND-2400'),
                new Piece(1200, 300, 3, 'BAND-1200'),
            ],
            'square_mix' => [
                new Piece(600, 600, 7, 'SQ-600'),
                new Piece(400, 400, 5, 'SQ-400'),
                new Piece(250, 250, 9, 'SQ-250'),
            ],
            'single_large' => [
                new Piece(2400, 1180, 2, 'FULL'),
            ],
            'slivers' => [
                new Piece(1000, 60, 12, 'SLIVER'),
                new Piece(800, 45, 8, 'SLIVER-S'),
            ],
        ];
    }

    /** Fixture A — the PS-1 drawing pack BOM, polycarbonate lines only. */
    public static function ps1Panels(): array
    {
        return [
            new Piece(1200, 1200, 4, 'PS1-PNL-CT-1212'),
            new Piece(1200, 200, 4, 'PS1-PNL-IF-1220'),
            new Piece(1200, 200, 2, 'PS1-PNL-EC-200'),
        ];
    }

    /** Fixture B — the WhatsApp paste, converted to mm. */
    public static function whatsappMix(): array
    {
        return [
            new Piece(600, 400, 6, 'OPAL-600x400'),
            new Piece(1200, 800, 2, 'OPAL-1200x800'),
            new Piece(500, 500, 4, 'MIRROR-500x500'),
        ];
    }
}
