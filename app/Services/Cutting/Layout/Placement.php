<?php

namespace App\Services\Cutting\Layout;

final class Placement
{
    public function __construct(
        public readonly string $label,
        public readonly float $x,
        public readonly float $y,
        public readonly float $width,
        public readonly float $height,
        public readonly bool $rotated,
        public readonly int $instanceIndex,
    ) {}

    public function rect(): Rect
    {
        return new Rect($this->x, $this->y, $this->width, $this->height);
    }

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'x' => round($this->x, 2),
            'y' => round($this->y, 2),
            'w' => round($this->width, 2),
            'h' => round($this->height, 2),
            'rotated' => $this->rotated,
            'instance_index' => $this->instanceIndex,
        ];
    }
}
