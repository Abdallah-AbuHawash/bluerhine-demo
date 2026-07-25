<?php

namespace App\Services\Cutting;

/**
 * Cutting parameters. Every value here is client-configurable — the defaults
 * are the ones quoted in the Phase 1 brief (kerf 4.4 mm, 10 mm edge trim).
 */
final class CutConfig
{
    public readonly float $kerfMm;

    public readonly float $trimTopMm;

    public readonly float $trimRightMm;

    public readonly float $trimBottomMm;

    public readonly float $trimLeftMm;

    /** Rotation is a material property (mirror/brushed stock is directional). */
    public readonly bool $rotationAllowed;

    /**
     * Whether the four edge-trim cuts count as billable cut length.
     * Assumption pending client confirmation — it moves the cutting charge.
     */
    public readonly bool $includeTrimInCutLength;

    /** Offcut rects smaller than this in either dimension are treated as dust. */
    public readonly float $minOffcutMm;

    public function __construct(
        float $kerfMm = 4.4,
        ?float $trimMm = null,
        ?float $trimTopMm = null,
        ?float $trimRightMm = null,
        ?float $trimBottomMm = null,
        ?float $trimLeftMm = null,
        bool $rotationAllowed = false,
        bool $includeTrimInCutLength = true,
        float $minOffcutMm = 1.0,
    ) {
        $this->kerfMm = $kerfMm;
        $this->trimTopMm = $trimTopMm ?? $trimMm ?? 10.0;
        $this->trimRightMm = $trimRightMm ?? $trimMm ?? 10.0;
        $this->trimBottomMm = $trimBottomMm ?? $trimMm ?? 10.0;
        $this->trimLeftMm = $trimLeftMm ?? $trimMm ?? 10.0;
        $this->rotationAllowed = $rotationAllowed;
        $this->includeTrimInCutLength = $includeTrimInCutLength;
        $this->minOffcutMm = $minOffcutMm;
    }

    public function withRotationAllowed(bool $rotationAllowed): self
    {
        return new self(
            kerfMm: $this->kerfMm,
            trimTopMm: $this->trimTopMm,
            trimRightMm: $this->trimRightMm,
            trimBottomMm: $this->trimBottomMm,
            trimLeftMm: $this->trimLeftMm,
            rotationAllowed: $rotationAllowed,
            includeTrimInCutLength: $this->includeTrimInCutLength,
            minOffcutMm: $this->minOffcutMm,
        );
    }

    /** Build from the cut_parameters singleton / a material row. */
    public static function fromArray(array $values): self
    {
        return new self(
            kerfMm: (float) ($values['kerf_mm'] ?? 4.4),
            trimMm: isset($values['trim_mm']) ? (float) $values['trim_mm'] : null,
            trimTopMm: isset($values['trim_top_mm']) ? (float) $values['trim_top_mm'] : null,
            trimRightMm: isset($values['trim_right_mm']) ? (float) $values['trim_right_mm'] : null,
            trimBottomMm: isset($values['trim_bottom_mm']) ? (float) $values['trim_bottom_mm'] : null,
            trimLeftMm: isset($values['trim_left_mm']) ? (float) $values['trim_left_mm'] : null,
            rotationAllowed: (bool) ($values['rotation_allowed'] ?? false),
            includeTrimInCutLength: (bool) ($values['include_trim_in_cut_length'] ?? true),
            minOffcutMm: (float) ($values['min_offcut_mm'] ?? 1.0),
        );
    }

    public function toArray(): array
    {
        return [
            'kerf_mm' => $this->kerfMm,
            'trim_top_mm' => $this->trimTopMm,
            'trim_right_mm' => $this->trimRightMm,
            'trim_bottom_mm' => $this->trimBottomMm,
            'trim_left_mm' => $this->trimLeftMm,
            'rotation_allowed' => $this->rotationAllowed,
            'include_trim_in_cut_length' => $this->includeTrimInCutLength,
            'min_offcut_mm' => $this->minOffcutMm,
        ];
    }
}
