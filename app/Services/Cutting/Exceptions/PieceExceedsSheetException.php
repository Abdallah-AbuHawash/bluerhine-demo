<?php

namespace App\Services\Cutting\Exceptions;

use RuntimeException;

/**
 * A piece cannot fit the usable sheet area. Structured, never silently fixed:
 * the caller gets the offending labels and the usable area they overflow.
 */
class PieceExceedsSheetException extends RuntimeException
{
    /**
     * @param  array<int, array{label: string, width_mm: float, height_mm: float}>  $pieces
     */
    public function __construct(
        public readonly array $pieces,
        public readonly float $usableWidthMm,
        public readonly float $usableHeightMm,
    ) {
        $labels = implode(', ', array_map(
            fn (array $p) => sprintf('%s (%.1f x %.1f mm)', $p['label'], $p['width_mm'], $p['height_mm']),
            $pieces,
        ));

        parent::__construct(sprintf(
            'Piece larger than the usable sheet area (%.1f x %.1f mm after trim): %s.',
            $usableWidthMm,
            $usableHeightMm,
            $labels,
        ));
    }

    public function toArray(): array
    {
        return [
            'error' => 'piece_exceeds_sheet',
            'message' => $this->getMessage(),
            'usable_width_mm' => $this->usableWidthMm,
            'usable_height_mm' => $this->usableHeightMm,
            'pieces' => $this->pieces,
        ];
    }
}
