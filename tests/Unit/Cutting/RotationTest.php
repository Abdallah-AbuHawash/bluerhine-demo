<?php

namespace Tests\Unit\Cutting;

use App\Services\Cutting\CutConfig;
use App\Services\Cutting\Estimator;
use App\Services\Cutting\EstimatorMode;
use App\Services\Cutting\Exceptions\PieceExceedsSheetException;
use App\Services\Cutting\Piece;
use App\Services\Cutting\Sheet;
use PHPUnit\Framework\TestCase;

class RotationTest extends TestCase
{
    public function test_fixed_orientation_never_rotates(): void
    {
        $config = new CutConfig(kerfMm: 4.4, trimMm: 10.0, rotationAllowed: true);

        $result = (new Estimator($config))->estimate(
            new Sheet(2440, 1220),
            [new Piece(300, 1100, 2, 'TALL')],
            EstimatorMode::FixedOrientation,
        );

        foreach ($result->layouts[0]->placements as $placement) {
            $this->assertFalse($placement->rotated);
            $this->assertEqualsWithDelta(300.0, $placement->width, 0.001);
            $this->assertEqualsWithDelta(1100.0, $placement->height, 0.001);
        }
    }

    public function test_optimized_rotates_a_piece_that_only_fits_sideways(): void
    {
        $config = new CutConfig(kerfMm: 4.4, trimMm: 10.0, rotationAllowed: true);
        $sheet = new Sheet(2440, 1220);
        $pieces = [new Piece(1180, 2400, 1, 'PORTRAIT')];

        $result = (new Estimator($config))->estimate($sheet, $pieces, EstimatorMode::Optimized);

        $this->assertSame(1, $result->sheetsConsumed);
        $placement = $result->layouts[0]->placements[0];
        $this->assertTrue($placement->rotated);
        $this->assertEqualsWithDelta(2400.0, $placement->width, 0.001);
        $this->assertEqualsWithDelta(1180.0, $placement->height, 0.001);
    }

    public function test_the_same_piece_is_a_validation_error_in_fixed_orientation(): void
    {
        $config = new CutConfig(kerfMm: 4.4, trimMm: 10.0, rotationAllowed: true);

        try {
            (new Estimator($config))->estimate(
                new Sheet(2440, 1220),
                [new Piece(1180, 2400, 1, 'PORTRAIT')],
                EstimatorMode::FixedOrientation,
            );
            $this->fail('Expected PieceExceedsSheetException.');
        } catch (PieceExceedsSheetException $e) {
            $this->assertStringContainsString('PORTRAIT', $e->getMessage());
            $this->assertSame('PORTRAIT', $e->pieces[0]['label']);
            $this->assertEqualsWithDelta(2420.0, $e->usableWidthMm, 0.001);
            $this->assertEqualsWithDelta(1200.0, $e->usableHeightMm, 0.001);
        }
    }

    public function test_rotation_locked_material_optimizes_to_exactly_the_fixed_layout(): void
    {
        // Mirror acrylic: rotation_allowed = false. Optimized must not cheat.
        $config = new CutConfig(kerfMm: 4.4, trimMm: 10.0, rotationAllowed: false);
        $sheet = new Sheet(2440, 1220);
        $pieces = [new Piece(500, 500, 4, 'MIRROR'), new Piece(900, 300, 3, 'MIRROR-BAND')];

        $fixed = (new Estimator($config))->estimate($sheet, $pieces, EstimatorMode::FixedOrientation);
        $optimized = (new Estimator($config))->estimate($sheet, $pieces, EstimatorMode::Optimized);

        $fixedArray = $fixed->toArray();
        $optimizedArray = $optimized->toArray();
        unset($fixedArray['mode'], $optimizedArray['mode']);

        $this->assertSame($fixedArray, $optimizedArray);

        foreach ($optimized->layouts as $layout) {
            foreach ($layout->placements as $placement) {
                $this->assertFalse($placement->rotated);
            }
        }
    }

    public function test_optimized_rotation_can_save_a_sheet(): void
    {
        $config = new CutConfig(kerfMm: 4.4, trimMm: 10.0, rotationAllowed: true);
        $sheet = new Sheet(2440, 1220);
        // 1150 x 700 pieces: two per shelf as given (1150 + 4.4 + 1150 = 2304.4),
        // one shelf per sheet. Rotated to 700 x 1150 three fit across a shelf.
        $pieces = [new Piece(1150, 700, 3, 'PANEL')];

        $fixed = (new Estimator($config))->estimate($sheet, $pieces, EstimatorMode::FixedOrientation);
        $optimized = (new Estimator($config))->estimate($sheet, $pieces, EstimatorMode::Optimized);

        $this->assertSame(2, $fixed->sheetsConsumed);
        $this->assertSame(1, $optimized->sheetsConsumed);
    }
}
