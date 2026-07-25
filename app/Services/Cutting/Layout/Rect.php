<?php

namespace App\Services\Cutting\Layout;

final class Rect
{
    public function __construct(
        public readonly float $x,
        public readonly float $y,
        public readonly float $width,
        public readonly float $height,
    ) {}

    public function right(): float
    {
        return $this->x + $this->width;
    }

    public function bottom(): float
    {
        return $this->y + $this->height;
    }

    public function areaMm2(): float
    {
        return $this->width * $this->height;
    }

    public function isEmpty(float $tolerance = 1e-6): bool
    {
        return $this->width <= $tolerance || $this->height <= $tolerance;
    }

    public function toArray(): array
    {
        return [
            'x' => round($this->x, 2),
            'y' => round($this->y, 2),
            'w' => round($this->width, 2),
            'h' => round($this->height, 2),
        ];
    }
}
