<?php

namespace App\Services\Cutting\Layout;

final class SheetLayout
{
    /**
     * @param  array<int, Placement>  $placements
     * @param  array<int, Rect>  $offcuts
     * @param  array<int, CutSegment>  $cuts
     */
    public function __construct(
        public readonly int $index,
        public readonly float $sheetWidthMm,
        public readonly float $sheetHeightMm,
        public readonly Rect $usable,
        public readonly array $placements,
        public readonly array $offcuts,
        public readonly array $cuts,
        public readonly CutNode $tree,
    ) {}

    public function trimCutLengthMm(): float
    {
        return $this->sumCuts(fn (CutSegment $cut) => $cut->isTrim());
    }

    public function pieceCutLengthMm(): float
    {
        return $this->sumCuts(fn (CutSegment $cut) => ! $cut->isTrim());
    }

    public function usedAreaMm2(): float
    {
        return array_sum(array_map(fn (Placement $p) => $p->width * $p->height, $this->placements));
    }

    public function yieldPct(): float
    {
        $sheetArea = $this->sheetWidthMm * $this->sheetHeightMm;

        return $sheetArea > 0 ? round(100 * $this->usedAreaMm2() / $sheetArea, 2) : 0.0;
    }

    /** @return array<string, int> label => qty on this sheet */
    public function pieceCounts(): array
    {
        $counts = [];

        foreach ($this->placements as $placement) {
            $counts[$placement->label] = ($counts[$placement->label] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'sheet' => [
                'width_mm' => round($this->sheetWidthMm, 2),
                'height_mm' => round($this->sheetHeightMm, 2),
            ],
            'usable' => $this->usable->toArray(),
            'placements' => array_map(fn (Placement $p) => $p->toArray(), $this->placements),
            'offcuts' => array_map(fn (Rect $r) => $r->toArray(), $this->offcuts),
            'cuts' => array_map(fn (CutSegment $c) => $c->toArray(), $this->cuts),
            'piece_counts' => $this->pieceCounts(),
            'trim_cut_length_mm' => round($this->trimCutLengthMm(), 2),
            'piece_cut_length_mm' => round($this->pieceCutLengthMm(), 2),
            'yield_pct' => $this->yieldPct(),
            'tree' => $this->tree->toArray(),
        ];
    }

    private function sumCuts(callable $filter): float
    {
        $total = 0.0;

        foreach ($this->cuts as $cut) {
            if ($filter($cut)) {
                $total += $cut->lengthMm();
            }
        }

        return $total;
    }
}
