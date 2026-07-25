<?php

namespace App\Services\Cutting;

use App\Services\Cutting\Exceptions\InvalidCutListException;

final class Piece
{
    public function __construct(
        public readonly float $widthMm,
        public readonly float $heightMm,
        public readonly int $qty,
        public readonly string $label,
    ) {
        if ($widthMm <= 0 || $heightMm <= 0) {
            throw new InvalidCutListException("Piece \"{$label}\" must have positive width and height.");
        }

        if ($qty < 0) {
            throw new InvalidCutListException("Piece \"{$label}\" cannot have a negative quantity.");
        }
    }

    public static function fromArray(array $row, int $index = 0): self
    {
        return new self(
            widthMm: (float) ($row['width'] ?? $row['width_mm']),
            heightMm: (float) ($row['height'] ?? $row['height_mm']),
            qty: (int) ($row['qty'] ?? 1),
            label: (string) ($row['label'] ?? 'Piece '.($index + 1)),
        );
    }

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'width_mm' => $this->widthMm,
            'height_mm' => $this->heightMm,
            'qty' => $this->qty,
        ];
    }
}
