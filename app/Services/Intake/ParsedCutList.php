<?php

namespace App\Services\Intake;

/**
 * Normalised result of parsing a customer cut list, whatever the source.
 */
final class ParsedCutList
{
    /**
     * @param  array<int, array{material_hint: ?string, thickness_mm: ?float, width_mm: ?float, height_mm: ?float, qty: int, notes: ?string}>  $pieces
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public readonly array $pieces,
        public readonly float $confidence,
        public readonly array $warnings,
        public readonly string $source,
    ) {}

    public static function fromArray(array $data, string $source): self
    {
        $pieces = array_values(array_map(
            fn (array $piece) => [
                'material_hint' => isset($piece['material_hint']) ? (string) $piece['material_hint'] : null,
                'thickness_mm' => isset($piece['thickness_mm']) ? (float) $piece['thickness_mm'] : null,
                'width_mm' => isset($piece['width_mm']) ? (float) $piece['width_mm'] : null,
                'height_mm' => isset($piece['height_mm']) ? (float) $piece['height_mm'] : null,
                'qty' => (int) ($piece['qty'] ?? 1),
                'notes' => isset($piece['notes']) ? (string) $piece['notes'] : null,
            ],
            $data['pieces'] ?? [],
        ));

        return new self(
            pieces: $pieces,
            confidence: (float) ($data['confidence'] ?? 0.0),
            warnings: array_values(array_map('strval', $data['warnings'] ?? [])),
            source: $source,
        );
    }

    public function isOffline(): bool
    {
        return $this->source === 'offline_fixture';
    }

    public function toArray(): array
    {
        return [
            'pieces' => $this->pieces,
            'confidence' => $this->confidence,
            'warnings' => $this->warnings,
            'source' => $this->source,
        ];
    }
}
