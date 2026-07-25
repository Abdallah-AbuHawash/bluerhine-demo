<?php

namespace App\Services\Cutting;

use App\Services\Cutting\Layout\Rect;
use App\Services\Cutting\Layout\SheetLayout;
use App\Services\Cutting\Strategy\OrientationPolicy;

/**
 * Engine output. toArray() is the serialization the frontend renders and the
 * quote snapshot freezes, so its shape and ordering are part of the contract:
 * identical input must always produce an identical array.
 */
final class EstimateResult
{
    public readonly int $sheetsConsumed;

    public readonly float $trimCutLengthMm;

    public readonly float $pieceCutLengthMm;

    public readonly float $totalCutLengthMm;

    /** @var array<int, array{sheet_index: int, pieces: array<string, int>, total: int}> */
    public readonly array $perSheetPieces;

    /** @var array<int, array{sheet_index: int, x: float, y: float, w: float, h: float}> */
    public readonly array $offcuts;

    public readonly int $piecesPlaced;

    /**
     * @param  array<int, SheetLayout>  $layouts
     * @param  array<int, array{label: string, width_mm: float, height_mm: float}>  $unplaceablePieces
     */
    public function __construct(
        public readonly EstimatorMode $mode,
        public readonly OrientationPolicy $strategy,
        public readonly Sheet $sheet,
        public readonly CutConfig $config,
        public readonly array $layouts,
        public readonly array $unplaceablePieces = [],
    ) {
        $trim = 0.0;
        $piece = 0.0;
        $perSheet = [];
        $offcuts = [];
        $placed = 0;

        foreach ($layouts as $layout) {
            $trim += $layout->trimCutLengthMm();
            $piece += $layout->pieceCutLengthMm();
            $counts = $layout->pieceCounts();
            $placed += count($layout->placements);

            $perSheet[] = [
                'sheet_index' => $layout->index,
                'pieces' => $counts,
                'total' => count($layout->placements),
            ];

            foreach ($layout->offcuts as $offcut) {
                $offcuts[] = ['sheet_index' => $layout->index] + $offcut->toArray();
            }
        }

        $this->trimCutLengthMm = round($trim, 4);
        $this->pieceCutLengthMm = round($piece, 4);
        $this->totalCutLengthMm = round($piece + ($config->includeTrimInCutLength ? $trim : 0.0), 4);
        $this->sheetsConsumed = count($layouts);
        $this->perSheetPieces = $perSheet;
        $this->offcuts = $offcuts;
        $this->piecesPlaced = $placed;
    }

    public function totalCutMetres(): float
    {
        return round($this->totalCutLengthMm / 1000, 4);
    }

    public function totalOffcutAreaMm2(): float
    {
        $area = 0.0;

        foreach ($this->layouts as $layout) {
            foreach ($layout->offcuts as $offcut) {
                $area += $offcut->areaMm2();
            }
        }

        return round($area, 2);
    }

    public function largestOffcut(): ?Rect
    {
        $largest = null;

        foreach ($this->layouts as $layout) {
            foreach ($layout->offcuts as $offcut) {
                if ($largest === null || $offcut->areaMm2() > $largest->areaMm2()) {
                    $largest = $offcut;
                }
            }
        }

        return $largest;
    }

    public function toArray(): array
    {
        return [
            'mode' => $this->mode->value,
            'strategy' => $this->strategy->value,
            'sheet' => $this->sheet->toArray(),
            'config' => $this->config->toArray(),
            'sheets_consumed' => $this->sheetsConsumed,
            'pieces_placed' => $this->piecesPlaced,
            'trim_cut_length_mm' => round($this->trimCutLengthMm, 2),
            'piece_cut_length_mm' => round($this->pieceCutLengthMm, 2),
            'total_cut_length_mm' => round($this->totalCutLengthMm, 2),
            'total_cut_metres' => $this->totalCutMetres(),
            'per_sheet_pieces' => $this->perSheetPieces,
            'offcuts' => $this->offcuts,
            'total_offcut_area_mm2' => $this->totalOffcutAreaMm2(),
            'unplaceable_pieces' => $this->unplaceablePieces,
            'layouts' => array_map(fn (SheetLayout $layout) => $layout->toArray(), $this->layouts),
        ];
    }

    public function toJson(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this->toArray(), $flags);
    }
}
