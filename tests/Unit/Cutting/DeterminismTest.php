<?php

namespace Tests\Unit\Cutting;

use App\Services\Cutting\CutConfig;
use App\Services\Cutting\Estimator;
use App\Services\Cutting\EstimatorMode;
use App\Services\Cutting\Layout\Placement;
use App\Services\Cutting\Piece;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\CutLists;

/**
 * Determinism is non-negotiable: this engine has to reproduce the client's
 * costing workbook line for line, so the same input must always serialize to
 * the same bytes.
 */
class DeterminismTest extends TestCase
{
    private const SNAPSHOT = __DIR__.'/../../Fixtures/engine-snapshot-ps1.json';

    public function test_the_same_input_serializes_identically_every_run(): void
    {
        foreach (CutLists::all() as $name => $pieces) {
            $first = $this->estimate($pieces, EstimatorMode::Optimized)->toJson();
            $second = $this->estimate($pieces, EstimatorMode::Optimized)->toJson();
            $third = $this->estimate($pieces, EstimatorMode::Optimized)->toJson();

            $this->assertSame($first, $second, "cut list {$name} is not stable");
            $this->assertSame($second, $third, "cut list {$name} is not stable");
        }
    }

    public function test_input_row_order_does_not_change_the_layout(): void
    {
        $pieces = CutLists::whatsappMix();

        $forward = $this->estimate($pieces, EstimatorMode::FixedOrientation);
        $reversed = $this->estimate(array_reverse($pieces), EstimatorMode::FixedOrientation);

        $this->assertSame($this->geometry($forward), $this->geometry($reversed));
        $this->assertSame($forward->sheetsConsumed, $reversed->sheetsConsumed);
        $this->assertEqualsWithDelta($forward->totalCutLengthMm, $reversed->totalCutLengthMm, 0.001);
    }

    public function test_expanding_quantities_by_hand_does_not_change_the_layout(): void
    {
        $grouped = [new Piece(600, 400, 3, 'A')];
        $expanded = [
            new Piece(600, 400, 1, 'A'),
            new Piece(600, 400, 1, 'A'),
            new Piece(600, 400, 1, 'A'),
        ];

        $this->assertSame(
            $this->geometry($this->estimate($grouped, EstimatorMode::FixedOrientation)),
            $this->geometry($this->estimate($expanded, EstimatorMode::FixedOrientation)),
        );
    }

    /**
     * Golden-file guard: any change in engine output has to be an explicit,
     * reviewed change to this fixture, never an accident.
     */
    public function test_serialized_output_matches_the_committed_snapshot(): void
    {
        $this->assertFileExists(self::SNAPSHOT);

        $actual = $this->estimate(CutLists::ps1Panels(), EstimatorMode::Optimized)->toJson();

        $this->assertJsonStringEqualsJsonFile(
            self::SNAPSHOT,
            $actual,
            'Engine output drifted from the committed snapshot. If the change is intended, '
            .'regenerate it with: docker compose exec app php artisan cutting:snapshot',
        );
    }

    /** @param array<int, Piece> $pieces */
    private function estimate(array $pieces, EstimatorMode $mode)
    {
        $config = new CutConfig(kerfMm: 4.4, trimMm: 10.0, rotationAllowed: true);

        return (new Estimator($config))->estimate(CutLists::standardSheet(), $pieces, $mode);
    }

    /** Geometry only — instance indices legitimately follow input order. */
    private function geometry($result): array
    {
        return array_map(
            fn ($layout) => array_map(
                fn (Placement $p) => [$p->label, $p->x, $p->y, $p->width, $p->height, $p->rotated],
                $layout->placements,
            ),
            $result->layouts,
        );
    }
}
