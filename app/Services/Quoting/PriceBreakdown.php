<?php

namespace App\Services\Quoting;

final class PriceBreakdown
{
    public function __construct(
        public readonly int $sheets,
        public readonly float $sheetPriceAed,
        public readonly float $materialTotalAed,
        public readonly float $cutMetres,
        public readonly int $piecesPlaced,
        public readonly float $rate,
        public readonly string $rateUnit,
        public readonly float $cuttingTotalAed,
        public readonly float $subtotalAed,
        public readonly float $vatPct,
        public readonly float $vatAed,
        public readonly float $totalAed,
    ) {}

    /** Cutting charge basis depends on the (still unconfirmed) rate unit. */
    public static function make(
        int $sheets,
        float $sheetPriceAed,
        float $cutMetres,
        int $piecesPlaced,
        float $rate,
        string $rateUnit,
        float $vatPct,
    ): self {
        $materialTotal = round($sheets * $sheetPriceAed, 2);

        $cuttingTotal = round(match ($rateUnit) {
            'per_piece' => $piecesPlaced * $rate,
            'per_sheet' => $sheets * $rate,
            default => $cutMetres * $rate,
        }, 2);

        $subtotal = round($materialTotal + $cuttingTotal, 2);
        $vat = round($subtotal * $vatPct / 100, 2);

        return new self(
            sheets: $sheets,
            sheetPriceAed: $sheetPriceAed,
            materialTotalAed: $materialTotal,
            cutMetres: $cutMetres,
            piecesPlaced: $piecesPlaced,
            rate: $rate,
            rateUnit: $rateUnit,
            cuttingTotalAed: $cuttingTotal,
            subtotalAed: $subtotal,
            vatPct: $vatPct,
            vatAed: $vat,
            totalAed: round($subtotal + $vat, 2),
        );
    }

    public function cuttingBasisLabel(): string
    {
        return match ($this->rateUnit) {
            'per_piece' => sprintf('%d pieces x AED %.2f', $this->piecesPlaced, $this->rate),
            'per_sheet' => sprintf('%d sheets x AED %.2f', $this->sheets, $this->rate),
            default => sprintf('%.3f cut metres x AED %.2f', $this->cutMetres, $this->rate),
        };
    }

    public function toArray(): array
    {
        return [
            'sheets' => $this->sheets,
            'sheet_price_aed' => $this->sheetPriceAed,
            'material_total_aed' => $this->materialTotalAed,
            'cut_metres' => $this->cutMetres,
            'pieces_placed' => $this->piecesPlaced,
            'rate' => $this->rate,
            'rate_unit' => $this->rateUnit,
            'cutting_basis' => $this->cuttingBasisLabel(),
            'cutting_total_aed' => $this->cuttingTotalAed,
            'subtotal_aed' => $this->subtotalAed,
            'vat_pct' => $this->vatPct,
            'vat_aed' => $this->vatAed,
            'total_aed' => $this->totalAed,
        ];
    }
}
