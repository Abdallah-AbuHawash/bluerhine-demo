<?php

namespace Tests\Unit\Cutting;

use App\Services\Cutting\CutConfig;
use App\Services\Cutting\Estimator;
use App\Services\Cutting\EstimatorMode;
use App\Services\Cutting\Layout\CutNode;
use App\Services\Cutting\Layout\Placement;
use App\Services\Cutting\Layout\Rect;
use App\Services\Cutting\Layout\SheetLayout;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\CutLists;

/**
 * Structural guarantees of the layout: no overlaps, nothing outside the usable
 * area, and every cut edge-to-edge on the rectangle it splits (guillotine).
 */
class GuillotineInvariantTest extends TestCase
{
    private const EPS = 1e-6;

    public static function cutListProvider(): array
    {
        $cases = [];

        foreach (CutLists::all() as $name => $pieces) {
            foreach ([EstimatorMode::FixedOrientation, EstimatorMode::Optimized] as $mode) {
                $cases["{$name}:{$mode->value}"] = [$pieces, $mode];
            }
        }

        return $cases;
    }

    #[DataProvider('cutListProvider')]
    public function test_layout_invariants_hold(array $pieces, EstimatorMode $mode): void
    {
        $config = new CutConfig(kerfMm: 4.4, trimMm: 10.0, rotationAllowed: true);
        $result = (new Estimator($config))->estimate(CutLists::standardSheet(), $pieces, $mode);

        foreach ($result->layouts as $layout) {
            $this->assertPlacementsInsideUsableArea($layout);
            $this->assertNoOverlaps($layout);
            $this->assertEveryCutIsEdgeToEdge($layout->tree);
            $this->assertTreeMatchesPlacements($layout);
            $this->assertKerfBetweenSiblings($layout->tree, $config->kerfMm);
        }
    }

    private function assertPlacementsInsideUsableArea(SheetLayout $layout): void
    {
        foreach ($layout->placements as $placement) {
            $this->assertGreaterThanOrEqual($layout->usable->x - self::EPS, $placement->x);
            $this->assertGreaterThanOrEqual($layout->usable->y - self::EPS, $placement->y);
            $this->assertLessThanOrEqual($layout->usable->right() + self::EPS, $placement->x + $placement->width);
            $this->assertLessThanOrEqual($layout->usable->bottom() + self::EPS, $placement->y + $placement->height);
        }
    }

    private function assertNoOverlaps(SheetLayout $layout): void
    {
        $rects = array_map(fn (Placement $p) => $p->rect(), $layout->placements);

        for ($i = 0; $i < count($rects); $i++) {
            for ($j = $i + 1; $j < count($rects); $j++) {
                $this->assertFalse(
                    $this->overlaps($rects[$i], $rects[$j]),
                    "placements {$i} and {$j} overlap on sheet {$layout->index}",
                );
            }
        }
    }

    private function overlaps(Rect $a, Rect $b): bool
    {
        return $a->x < $b->right() - self::EPS
            && $b->x < $a->right() - self::EPS
            && $a->y < $b->bottom() - self::EPS
            && $b->y < $a->bottom() - self::EPS;
    }

    /** A guillotine cut spans the full width or height of the rect it splits. */
    private function assertEveryCutIsEdgeToEdge(CutNode $node): void
    {
        if ($node->cut !== null) {
            $cut = $node->cut;
            $rect = $node->rect;

            if ($cut->axis === 'horizontal') {
                $this->assertGreaterThanOrEqual($rect->width - self::EPS, abs($cut->x2 - $cut->x1));
                $this->assertGreaterThanOrEqual($rect->y - self::EPS, $cut->y1);
                $this->assertLessThanOrEqual($rect->bottom() + self::EPS, $cut->y1);
            } else {
                $this->assertGreaterThanOrEqual($rect->height - self::EPS, abs($cut->y2 - $cut->y1));
                $this->assertGreaterThanOrEqual($rect->x - self::EPS, $cut->x1);
                $this->assertLessThanOrEqual($rect->right() + self::EPS, $cut->x1);
            }
        }

        foreach ($node->children as $child) {
            $this->assertContainedIn($child->rect, $node->rect);
            $this->assertEveryCutIsEdgeToEdge($child);
        }
    }

    private function assertContainedIn(Rect $child, Rect $parent): void
    {
        $this->assertGreaterThanOrEqual($parent->x - self::EPS, $child->x);
        $this->assertGreaterThanOrEqual($parent->y - self::EPS, $child->y);
        $this->assertLessThanOrEqual($parent->right() + self::EPS, $child->right());
        $this->assertLessThanOrEqual($parent->bottom() + self::EPS, $child->bottom());
    }

    /** Sibling rectangles are separated by exactly one kerf, never overlapping. */
    private function assertKerfBetweenSiblings(CutNode $node, float $kerfMm): void
    {
        if (count($node->children) === 2 && $node->cut !== null && $node->cut->kind !== 'trim') {
            [$a, $b] = $node->children;

            $gap = $node->cut->axis === 'horizontal'
                ? $b->rect->y - $a->rect->bottom()
                : $b->rect->x - $a->rect->right();

            if (! $b->rect->isEmpty()) {
                $this->assertEqualsWithDelta($kerfMm, $gap, 1e-6);
            }
        }

        foreach ($node->children as $child) {
            $this->assertKerfBetweenSiblings($child, $kerfMm);
        }
    }

    private function assertTreeMatchesPlacements(SheetLayout $layout): void
    {
        $fromTree = [];
        $this->collectPieces($layout->tree, $fromTree);

        $fromList = array_map(
            fn (Placement $p) => [$p->label, round($p->x, 4), round($p->y, 4), round($p->width, 4), round($p->height, 4)],
            $layout->placements,
        );

        sort($fromTree);
        sort($fromList);

        $this->assertSame($fromList, $fromTree, 'cut tree and placement list disagree');
    }

    private function collectPieces(CutNode $node, array &$out): void
    {
        if ($node->kind === 'piece' && $node->placement !== null) {
            $p = $node->placement;
            $out[] = [$p->label, round($p->x, 4), round($p->y, 4), round($p->width, 4), round($p->height, 4)];
        }

        foreach ($node->children as $child) {
            $this->collectPieces($child, $out);
        }
    }
}
