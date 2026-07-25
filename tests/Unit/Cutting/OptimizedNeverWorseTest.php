<?php

namespace Tests\Unit\Cutting;

use App\Services\Cutting\CutConfig;
use App\Services\Cutting\Estimator;
use App\Services\Cutting\EstimatorMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\CutLists;

/**
 * Property under test: for the same input, Optimized never uses more sheets
 * than FixedOrientation, and on equal sheets never more cut length.
 */
class OptimizedNeverWorseTest extends TestCase
{
    public static function cutListProvider(): array
    {
        $cases = [];

        foreach (CutLists::all() as $name => $pieces) {
            $cases[$name] = [$pieces];
        }

        return $cases;
    }

    #[DataProvider('cutListProvider')]
    public function test_optimized_is_never_worse_with_rotation_allowed(array $pieces): void
    {
        $config = new CutConfig(kerfMm: 4.4, trimMm: 10.0, rotationAllowed: true);
        $sheet = CutLists::standardSheet();

        $fixed = (new Estimator($config))->estimate($sheet, $pieces, EstimatorMode::FixedOrientation);
        $optimized = (new Estimator($config))->estimate($sheet, $pieces, EstimatorMode::Optimized);

        $this->assertLessThanOrEqual($fixed->sheetsConsumed, $optimized->sheetsConsumed);

        if ($optimized->sheetsConsumed === $fixed->sheetsConsumed) {
            $this->assertLessThanOrEqual($fixed->totalCutLengthMm + 1e-6, $optimized->totalCutLengthMm);
        }

        $this->assertSame($fixed->piecesPlaced, $optimized->piecesPlaced);
        $this->assertSame([], $optimized->unplaceablePieces);
    }

    #[DataProvider('cutListProvider')]
    public function test_optimized_equals_fixed_when_the_material_is_directional(array $pieces): void
    {
        $config = new CutConfig(kerfMm: 4.4, trimMm: 10.0, rotationAllowed: false);
        $sheet = CutLists::standardSheet();

        $fixed = (new Estimator($config))->estimate($sheet, $pieces, EstimatorMode::FixedOrientation);
        $optimized = (new Estimator($config))->estimate($sheet, $pieces, EstimatorMode::Optimized);

        $this->assertSame($fixed->sheetsConsumed, $optimized->sheetsConsumed);
        $this->assertEqualsWithDelta($fixed->totalCutLengthMm, $optimized->totalCutLengthMm, 1e-6);
    }

    #[DataProvider('cutListProvider')]
    public function test_every_piece_is_accounted_for(array $pieces): void
    {
        $config = new CutConfig(kerfMm: 4.4, trimMm: 10.0, rotationAllowed: true);
        $expected = array_sum(array_map(fn ($piece) => $piece->qty, $pieces));

        foreach ([EstimatorMode::FixedOrientation, EstimatorMode::Optimized] as $mode) {
            $result = (new Estimator($config))->estimate(CutLists::standardSheet(), $pieces, $mode);

            $this->assertSame($expected, $result->piecesPlaced + count($result->unplaceablePieces));
        }
    }
}
