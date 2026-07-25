<?php

namespace Tests\Unit\Cutting;

use App\Services\Cutting\CutConfig;
use App\Services\Cutting\Estimator;
use App\Services\Cutting\EstimatorMode;
use App\Services\Cutting\Piece;
use App\Services\Cutting\Sheet;
use PHPUnit\Framework\TestCase;

class KerfAndTrimTest extends TestCase
{
    public function test_kerf_is_consumed_between_neighbouring_pieces(): void
    {
        $result = (new Estimator(new CutConfig(kerfMm: 4.4, trimMm: 10.0)))
            ->estimate(new Sheet(2440, 1220), [new Piece(600, 400, 2, 'A')], EstimatorMode::FixedOrientation);

        $this->assertSame(1, $result->sheetsConsumed);

        [$first, $second] = $result->layouts[0]->placements;

        $this->assertEqualsWithDelta(10.0, $first->x, 0.001);
        // 10 trim + 600 piece + 4.4 kerf
        $this->assertEqualsWithDelta(614.4, $second->x, 0.001);
        $this->assertEqualsWithDelta($first->y, $second->y, 0.001);
    }

    public function test_kerf_is_consumed_between_shelves(): void
    {
        $result = (new Estimator(new CutConfig(kerfMm: 4.4, trimMm: 10.0)))
            ->estimate(new Sheet(2440, 1220), [new Piece(2000, 500, 2, 'A')], EstimatorMode::FixedOrientation);

        [$first, $second] = $result->layouts[0]->placements;

        $this->assertEqualsWithDelta(10.0, $first->y, 0.001);
        // 10 trim + 500 shelf height + 4.4 kerf
        $this->assertEqualsWithDelta(514.4, $second->y, 0.001);
    }

    public function test_a_wider_kerf_pushes_a_piece_onto_a_second_sheet(): void
    {
        // Two 1205 mm pieces plus kerf: 1205 + 4.4 + 1205 = 2414.4 <= 2420 usable.
        $narrowKerf = (new Estimator(new CutConfig(kerfMm: 4.4, trimMm: 10.0)))
            ->estimate(new Sheet(2440, 1220), [new Piece(1205, 1200, 2, 'A')], EstimatorMode::FixedOrientation);

        $this->assertSame(1, $narrowKerf->sheetsConsumed);

        // With a 12 mm kerf the pair no longer fits: 1205 + 12 + 1205 = 2422 > 2420.
        $wideKerf = (new Estimator(new CutConfig(kerfMm: 12.0, trimMm: 10.0)))
            ->estimate(new Sheet(2440, 1220), [new Piece(1205, 1200, 2, 'A')], EstimatorMode::FixedOrientation);

        $this->assertSame(2, $wideKerf->sheetsConsumed);
    }

    public function test_trim_reduces_the_usable_area_and_is_charged_as_cut_length(): void
    {
        $trimmed = (new Estimator(new CutConfig(kerfMm: 4.4, trimMm: 10.0)))
            ->estimate(new Sheet(2440, 1220), [new Piece(600, 400, 1, 'A')], EstimatorMode::FixedOrientation);

        $this->assertEqualsWithDelta(10.0, $trimmed->layouts[0]->usable->x, 0.001);
        $this->assertEqualsWithDelta(2420.0, $trimmed->layouts[0]->usable->width, 0.001);
        $this->assertEqualsWithDelta(1200.0, $trimmed->layouts[0]->usable->height, 0.001);
        $this->assertEqualsWithDelta(2 * (2440 + 1220), $trimmed->trimCutLengthMm, 0.001);

        $untrimmed = (new Estimator(new CutConfig(kerfMm: 4.4, trimMm: 0.0)))
            ->estimate(new Sheet(2440, 1220), [new Piece(600, 400, 1, 'A')], EstimatorMode::FixedOrientation);

        $this->assertEqualsWithDelta(0.0, $untrimmed->layouts[0]->placements[0]->x, 0.001);
        $this->assertEqualsWithDelta(0.0, $untrimmed->trimCutLengthMm, 0.001);
        $this->assertEqualsWithDelta($untrimmed->pieceCutLengthMm, $untrimmed->totalCutLengthMm, 0.001);
    }

    public function test_trim_can_be_excluded_from_billable_cut_length(): void
    {
        $config = new CutConfig(kerfMm: 4.4, trimMm: 10.0, includeTrimInCutLength: false);

        $result = (new Estimator($config))
            ->estimate(new Sheet(2440, 1220), [new Piece(600, 400, 1, 'A')], EstimatorMode::FixedOrientation);

        $this->assertEqualsWithDelta(7320.0, $result->trimCutLengthMm, 0.001);
        $this->assertEqualsWithDelta($result->pieceCutLengthMm, $result->totalCutLengthMm, 0.001);
    }

    public function test_asymmetric_trim_is_honoured_per_edge(): void
    {
        $config = new CutConfig(
            kerfMm: 4.4,
            trimTopMm: 5.0,
            trimRightMm: 20.0,
            trimBottomMm: 15.0,
            trimLeftMm: 0.0,
        );

        $result = (new Estimator($config))
            ->estimate(new Sheet(2440, 1220), [new Piece(600, 400, 1, 'A')], EstimatorMode::FixedOrientation);

        $usable = $result->layouts[0]->usable;

        $this->assertEqualsWithDelta(0.0, $usable->x, 0.001);
        $this->assertEqualsWithDelta(5.0, $usable->y, 0.001);
        $this->assertEqualsWithDelta(2420.0, $usable->width, 0.001);
        $this->assertEqualsWithDelta(1200.0, $usable->height, 0.001);

        // Left trim is zero, so only three trim cuts are made.
        $trimCuts = array_filter($result->layouts[0]->cuts, fn ($cut) => $cut->isTrim());
        $this->assertCount(3, $trimCuts);
        $this->assertEqualsWithDelta(1220.0 + 2440.0 + 2440.0, $result->trimCutLengthMm, 0.001);
    }
}
