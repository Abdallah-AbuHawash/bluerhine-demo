<?php

namespace App\Services\Cutting\Layout;

/**
 * One straight, edge-to-edge cut on the sub-rectangle it splits.
 *
 * kind: trim   — one of the four edge-trim cuts
 *       shelf  — cross cut releasing a shelf from the remaining board
 *       rip    — cut separating a piece column inside a shelf
 *       size   — cut bringing a piece down to height inside its column
 */
final class CutSegment
{
    public function __construct(
        public readonly string $kind,
        public readonly string $axis,
        public readonly float $x1,
        public readonly float $y1,
        public readonly float $x2,
        public readonly float $y2,
    ) {}

    public function lengthMm(): float
    {
        return $this->axis === 'horizontal'
            ? abs($this->x2 - $this->x1)
            : abs($this->y2 - $this->y1);
    }

    public function isTrim(): bool
    {
        return $this->kind === 'trim';
    }

    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'axis' => $this->axis,
            'x1' => round($this->x1, 2),
            'y1' => round($this->y1, 2),
            'x2' => round($this->x2, 2),
            'y2' => round($this->y2, 2),
            'length_mm' => round($this->lengthMm(), 2),
        ];
    }
}
