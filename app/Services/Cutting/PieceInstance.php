<?php

namespace App\Services\Cutting;

/**
 * One physical piece to cut. A Piece with qty 4 expands into four of these,
 * each carrying a stable index so sorting can never depend on input order.
 */
final class PieceInstance
{
    public function __construct(
        public readonly string $label,
        public readonly float $widthMm,
        public readonly float $heightMm,
        public readonly int $index,
        public readonly int $pieceIndex,
        public readonly bool $rotated = false,
    ) {}

    public function rotate(): self
    {
        return new self(
            label: $this->label,
            widthMm: $this->heightMm,
            heightMm: $this->widthMm,
            index: $this->index,
            pieceIndex: $this->pieceIndex,
            rotated: ! $this->rotated,
        );
    }

    public function fitsWithin(float $widthMm, float $heightMm, float $tolerance = 1e-6): bool
    {
        return $this->widthMm <= $widthMm + $tolerance
            && $this->heightMm <= $heightMm + $tolerance;
    }
}
