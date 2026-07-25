<?php

namespace App\Services\Cutting\Strategy;

use App\Services\Cutting\CutConfig;
use App\Services\Cutting\Layout\CutNode;
use App\Services\Cutting\Layout\CutSegment;
use App\Services\Cutting\Layout\Placement;
use App\Services\Cutting\Layout\Rect;
use App\Services\Cutting\Layout\SheetLayout;
use App\Services\Cutting\PieceInstance;
use App\Services\Cutting\Sheet;

/**
 * Guillotine shelf packer: pieces are packed into full-width horizontal
 * shelves, so every cut is edge-to-edge on the rectangle it splits.
 *
 * The placement policy is documented in app/Services/Cutting/README.md — that
 * document is the one to compare against the client's costing workbook.
 */
final class ShelfPacker
{
    private const EPS = 1e-6;

    public function __construct(private readonly CutConfig $config) {}

    /**
     * @param  array<int, PieceInstance>  $instances  already sorted
     */
    public function pack(Sheet $sheet, array $instances, OrientationPolicy $policy): PackResult
    {
        $usable = $this->usableRect($sheet);
        $rotationAllowed = $this->config->rotationAllowed && $policy === OrientationPolicy::BestFitPerPiece;

        /** @var array<int, array<int, array{y: float, height: float, used_right: float, items: array<int, array{instance: PieceInstance, x: float}>}>> $sheets */
        $sheets = [];
        $unplaceable = [];

        foreach ($instances as $instance) {
            $candidates = [$instance];

            if ($rotationAllowed && abs($instance->widthMm - $instance->heightMm) > self::EPS) {
                $candidates[] = $instance->rotate();
            }

            if (! $this->placeInExistingSheet($sheets, $candidates, $usable)) {
                $fresh = [];

                if ($this->openShelf($fresh, $candidates, $usable)) {
                    $sheets[] = $fresh;
                } else {
                    $unplaceable[] = $instance;
                }
            }
        }

        $layouts = [];

        foreach ($sheets as $index => $shelves) {
            $layouts[] = $this->buildLayout($index, $sheet, $usable, $shelves);
        }

        return new PackResult($policy, $layouts, $unplaceable);
    }

    public function usableRect(Sheet $sheet): Rect
    {
        return new Rect(
            $this->config->trimLeftMm,
            $this->config->trimTopMm,
            $sheet->widthMm - $this->config->trimLeftMm - $this->config->trimRightMm,
            $sheet->heightMm - $this->config->trimTopMm - $this->config->trimBottomMm,
        );
    }

    /**
     * @param  array<int, array<int, array>>  $sheets
     * @param  array<int, PieceInstance>  $candidates
     */
    private function placeInExistingSheet(array &$sheets, array $candidates, Rect $usable): bool
    {
        foreach ($sheets as $sheetIndex => $shelves) {
            if ($this->placeOnShelves($shelves, $candidates, $usable)
                || $this->openShelf($shelves, $candidates, $usable)) {
                $sheets[$sheetIndex] = $shelves;

                return true;
            }
        }

        return false;
    }

    /**
     * Best fit: the open shelf leaving the least trailing width wins;
     * ties break on the lowest shelf index, then on the unrotated candidate.
     *
     * @param  array<int, array>  $shelves
     * @param  array<int, PieceInstance>  $candidates
     */
    private function placeOnShelves(array &$shelves, array $candidates, Rect $usable): bool
    {
        $best = null;

        foreach ($candidates as $candidateIndex => $candidate) {
            foreach ($shelves as $shelfIndex => $shelf) {
                if ($candidate->heightMm > $shelf['height'] + self::EPS) {
                    continue;
                }

                $x = $shelf['items'] === []
                    ? $usable->x
                    : $shelf['used_right'] + $this->config->kerfMm;

                $leftover = $usable->right() - ($x + $candidate->widthMm);

                if ($leftover < -self::EPS) {
                    continue;
                }

                $key = [$leftover, $shelfIndex, $candidateIndex];

                if ($best === null || $this->isBetterFit($key, $best['key'])) {
                    $best = ['key' => $key, 'shelf' => $shelfIndex, 'candidate' => $candidate, 'x' => $x];
                }
            }
        }

        if ($best === null) {
            return false;
        }

        $shelf = &$shelves[$best['shelf']];
        $shelf['items'][] = ['instance' => $best['candidate'], 'x' => $best['x']];
        $shelf['used_right'] = $best['x'] + $best['candidate']->widthMm;

        return true;
    }

    /**
     * @param  array<int, array>  $shelves
     * @param  array<int, PieceInstance>  $candidates
     */
    private function openShelf(array &$shelves, array $candidates, Rect $usable): bool
    {
        $y = $shelves === []
            ? $usable->y
            : $this->shelfBottom($shelves[count($shelves) - 1]) + $this->config->kerfMm;

        foreach ($candidates as $candidate) {
            $fitsHeight = $y + $candidate->heightMm <= $usable->bottom() + self::EPS;
            $fitsWidth = $usable->x + $candidate->widthMm <= $usable->right() + self::EPS;

            if ($fitsHeight && $fitsWidth) {
                $shelves[] = [
                    'y' => $y,
                    'height' => $candidate->heightMm,
                    'used_right' => $usable->x + $candidate->widthMm,
                    'items' => [['instance' => $candidate, 'x' => $usable->x]],
                ];

                return true;
            }
        }

        return false;
    }

    private function shelfBottom(array $shelf): float
    {
        return $shelf['y'] + $shelf['height'];
    }

    /**
     * @param  array{0: float, 1: int, 2: int}  $a
     * @param  array{0: float, 1: int, 2: int}  $b
     */
    private function isBetterFit(array $a, array $b): bool
    {
        if (abs($a[0] - $b[0]) > self::EPS) {
            return $a[0] < $b[0];
        }

        if ($a[1] !== $b[1]) {
            return $a[1] < $b[1];
        }

        return $a[2] < $b[2];
    }

    // -----------------------------------------------------------------
    // Cut tree construction — the tree is the single source of truth for
    // placements, offcuts and cut segments, so they can never disagree.
    // -----------------------------------------------------------------

    /**
     * @param  array<int, array>  $shelves
     */
    private function buildLayout(int $index, Sheet $sheet, Rect $usable, array $shelves): SheetLayout
    {
        $collected = ['placements' => [], 'offcuts' => [], 'cuts' => []];

        $usableNode = $this->buildShelvesNode($usable, array_values($shelves), 0, $collected);
        $tree = $this->wrapWithTrim($sheet, $usable, $usableNode, $collected);

        usort(
            $collected['placements'],
            fn (Placement $a, Placement $b) => [$a->y, $a->x, $a->label] <=> [$b->y, $b->x, $b->label],
        );
        usort($collected['offcuts'], fn (Rect $a, Rect $b) => [$a->y, $a->x] <=> [$b->y, $b->x]);
        usort(
            $collected['cuts'],
            fn (CutSegment $a, CutSegment $b) => [$a->y1, $a->x1, $a->y2, $a->x2, $a->kind] <=> [$b->y1, $b->x1, $b->y2, $b->x2, $b->kind],
        );

        return new SheetLayout(
            index: $index,
            sheetWidthMm: $sheet->widthMm,
            sheetHeightMm: $sheet->heightMm,
            usable: $usable,
            placements: $collected['placements'],
            offcuts: $collected['offcuts'],
            cuts: $collected['cuts'],
            tree: $tree,
        );
    }

    private function wrapWithTrim(Sheet $sheet, Rect $usable, CutNode $usableNode, array &$collected): CutNode
    {
        $node = $usableNode;
        $rect = $usable;

        $edges = [
            ['mm' => $this->config->trimRightMm, 'axis' => 'vertical'],
            ['mm' => $this->config->trimBottomMm, 'axis' => 'horizontal'],
            ['mm' => $this->config->trimLeftMm, 'axis' => 'vertical'],
            ['mm' => $this->config->trimTopMm, 'axis' => 'horizontal'],
        ];

        // Grow the rectangle back out one trimmed edge at a time: right,
        // bottom, left, top. Each step is one edge-to-edge trim cut.
        foreach ($edges as $edgeIndex => $edge) {
            if ($edge['mm'] <= self::EPS) {
                continue;
            }

            $waste = match ($edgeIndex) {
                0 => new Rect($rect->right(), $rect->y, $edge['mm'], $rect->height),
                1 => new Rect($rect->x, $rect->bottom(), $rect->width, $edge['mm']),
                2 => new Rect($rect->x - $edge['mm'], $rect->y, $edge['mm'], $rect->height),
                default => new Rect($rect->x, $rect->y - $edge['mm'], $rect->width, $edge['mm']),
            };

            $outer = match ($edgeIndex) {
                0 => new Rect($rect->x, $rect->y, $rect->width + $edge['mm'], $rect->height),
                1 => new Rect($rect->x, $rect->y, $rect->width, $rect->height + $edge['mm']),
                2 => new Rect($rect->x - $edge['mm'], $rect->y, $rect->width + $edge['mm'], $rect->height),
                default => new Rect($rect->x, $rect->y - $edge['mm'], $rect->width, $rect->height + $edge['mm']),
            };

            // Trim cuts run the full sheet edge, not just the current rect.
            $cut = $edge['axis'] === 'vertical'
                ? new CutSegment('trim', 'vertical', $waste->x, 0.0, $waste->x, $sheet->heightMm)
                : new CutSegment('trim', 'horizontal', 0.0, $waste->y, $sheet->widthMm, $waste->y);

            $collected['cuts'][] = $cut;

            $node = CutNode::split($outer, $cut, $node, CutNode::leaf('trim', $waste));
            $rect = $outer;
        }

        return $node;
    }

    /**
     * @param  array<int, array>  $shelves
     */
    private function buildShelvesNode(Rect $region, array $shelves, int $index, array &$collected): CutNode
    {
        if ($region->isEmpty()) {
            return CutNode::leaf('waste', $region);
        }

        if (! isset($shelves[$index])) {
            return $this->offcutLeaf($region, $collected);
        }

        $shelf = $shelves[$index];
        $shelfRect = new Rect($region->x, $region->y, $region->width, $shelf['height']);

        if ($shelfRect->bottom() >= $region->bottom() - self::EPS) {
            return $this->buildShelfNode($shelfRect, $shelf, $collected);
        }

        $cut = new CutSegment(
            'shelf',
            'horizontal',
            $region->x,
            $shelfRect->bottom(),
            $region->right(),
            $shelfRect->bottom(),
        );
        $collected['cuts'][] = $cut;

        $remainderTop = $shelfRect->bottom() + $this->config->kerfMm;
        $remainder = new Rect($region->x, $remainderTop, $region->width, $region->bottom() - $remainderTop);

        return CutNode::split(
            $region,
            $cut,
            $this->buildShelfNode($shelfRect, $shelf, $collected),
            $this->buildShelvesNode($remainder, $shelves, $index + 1, $collected),
        );
    }

    private function buildShelfNode(Rect $shelfRect, array $shelf, array &$collected, int $itemIndex = 0): CutNode
    {
        if (! isset($shelf['items'][$itemIndex])) {
            return $this->offcutLeaf($shelfRect, $collected);
        }

        $item = $shelf['items'][$itemIndex];
        $instance = $item['instance'];
        $columnRight = $item['x'] + $instance->widthMm;

        if ($columnRight >= $shelfRect->right() - self::EPS) {
            $column = new Rect($shelfRect->x, $shelfRect->y, $shelfRect->width, $shelfRect->height);

            return $this->buildColumnNode($column, $instance, $collected);
        }

        $cut = new CutSegment('rip', 'vertical', $columnRight, $shelfRect->y, $columnRight, $shelfRect->bottom());
        $collected['cuts'][] = $cut;

        $column = new Rect($shelfRect->x, $shelfRect->y, $instance->widthMm, $shelfRect->height);
        $remainderLeft = $columnRight + $this->config->kerfMm;
        $remainder = new Rect($remainderLeft, $shelfRect->y, $shelfRect->right() - $remainderLeft, $shelfRect->height);

        return CutNode::split(
            $shelfRect,
            $cut,
            $this->buildColumnNode($column, $instance, $collected),
            $this->buildShelfNode($remainder, $shelf, $collected, $itemIndex + 1),
        );
    }

    private function buildColumnNode(Rect $column, PieceInstance $instance, array &$collected): CutNode
    {
        $pieceRect = new Rect($column->x, $column->y, $instance->widthMm, $instance->heightMm);
        $placement = new Placement(
            label: $instance->label,
            x: $pieceRect->x,
            y: $pieceRect->y,
            width: $pieceRect->width,
            height: $pieceRect->height,
            rotated: $instance->rotated,
            instanceIndex: $instance->index,
        );
        $collected['placements'][] = $placement;

        $pieceLeaf = CutNode::leaf('piece', $pieceRect, $placement);

        if ($pieceRect->bottom() >= $column->bottom() - self::EPS) {
            return $pieceLeaf;
        }

        $cut = new CutSegment(
            'size',
            'horizontal',
            $column->x,
            $pieceRect->bottom(),
            $column->right(),
            $pieceRect->bottom(),
        );
        $collected['cuts'][] = $cut;

        $wasteTop = $pieceRect->bottom() + $this->config->kerfMm;
        $waste = new Rect($column->x, $wasteTop, $column->width, $column->bottom() - $wasteTop);

        return CutNode::split($column, $cut, $pieceLeaf, $this->offcutLeaf($waste, $collected));
    }

    private function offcutLeaf(Rect $rect, array &$collected): CutNode
    {
        if ($rect->isEmpty()) {
            return CutNode::leaf('waste', $rect);
        }

        if ($rect->width < $this->config->minOffcutMm || $rect->height < $this->config->minOffcutMm) {
            return CutNode::leaf('waste', $rect);
        }

        $collected['offcuts'][] = $rect;

        return CutNode::leaf('offcut', $rect);
    }
}
