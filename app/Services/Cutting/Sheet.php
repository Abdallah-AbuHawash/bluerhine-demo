<?php

namespace App\Services\Cutting;

use App\Services\Cutting\Exceptions\InvalidCutListException;

final class Sheet
{
    public function __construct(
        public readonly float $widthMm,
        public readonly float $heightMm,
    ) {
        if ($widthMm <= 0 || $heightMm <= 0) {
            throw new InvalidCutListException('Sheet dimensions must be positive.');
        }
    }

    public function toArray(): array
    {
        return ['width_mm' => $this->widthMm, 'height_mm' => $this->heightMm];
    }
}
