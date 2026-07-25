<?php

namespace Tests\Unit\Cutting;

use App\Services\Cutting\CutConfig;
use App\Services\Cutting\Estimator;
use App\Services\Cutting\EstimatorMode;
use App\Services\Cutting\Piece;
use App\Services\Cutting\Sheet;
use PHPUnit\Framework\TestCase;

class EstimatorBasicTest extends TestCase
{
    /**
     * One 600x400 piece on a 2440x1220 sheet, fixed orientation.
     *
     * Usable area after 10 mm trim on all four sides: 2420 x 1200 at (10, 10).
     * Cut length:
     *   trim   = 2 * (2440 + 1220)            = 7320 mm
     *   shelf  = one cross cut, usable width  = 2420 mm
     *   piece  = one rip cut, shelf height     =  400 mm
     */
    public function test_single_piece_single_sheet_fixed_orientation(): void
    {
        $result = (new Estimator(new CutConfig(kerfMm: 4.4, trimMm: 10.0)))
            ->estimate(
                new Sheet(2440, 1220),
                [new Piece(600, 400, 1, 'A')],
                EstimatorMode::FixedOrientation,
            );

        $this->assertSame(1, $result->sheetsConsumed);
        $this->assertCount(1, $result->layouts);
        $this->assertSame([], $result->unplaceablePieces);

        $placement = $result->layouts[0]->placements[0];
        $this->assertSame('A', $placement->label);
        $this->assertEqualsWithDelta(10.0, $placement->x, 0.001);
        $this->assertEqualsWithDelta(10.0, $placement->y, 0.001);
        $this->assertEqualsWithDelta(600.0, $placement->width, 0.001);
        $this->assertEqualsWithDelta(400.0, $placement->height, 0.001);
        $this->assertFalse($placement->rotated);

        $this->assertEqualsWithDelta(7320.0, $result->trimCutLengthMm, 0.001);
        $this->assertEqualsWithDelta(2820.0, $result->pieceCutLengthMm, 0.001);
        $this->assertEqualsWithDelta(10140.0, $result->totalCutLengthMm, 0.001);
    }
}
