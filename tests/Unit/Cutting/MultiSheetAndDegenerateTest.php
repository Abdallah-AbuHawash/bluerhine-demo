<?php

namespace Tests\Unit\Cutting;

use App\Services\Cutting\CutConfig;
use App\Services\Cutting\Estimator;
use App\Services\Cutting\EstimatorMode;
use App\Services\Cutting\Exceptions\InvalidCutListException;
use App\Services\Cutting\Exceptions\PieceExceedsSheetException;
use App\Services\Cutting\Piece;
use App\Services\Cutting\Sheet;
use PHPUnit\Framework\TestCase;

class MultiSheetAndDegenerateTest extends TestCase
{
    private function estimator(): Estimator
    {
        return new Estimator(new CutConfig(kerfMm: 4.4, trimMm: 10.0));
    }

    public function test_overflow_opens_further_sheets(): void
    {
        // Usable 2420 x 1200: two 1200 x 1200 pieces per sheet, third overflows.
        $result = $this->estimator()->estimate(
            new Sheet(2440, 1220),
            [new Piece(1200, 1200, 3, 'BIG')],
            EstimatorMode::FixedOrientation,
        );

        $this->assertSame(2, $result->sheetsConsumed);
        $this->assertSame(2, $result->perSheetPieces[0]['total']);
        $this->assertSame(1, $result->perSheetPieces[1]['total']);
        $this->assertSame(['BIG' => 2], $result->perSheetPieces[0]['pieces']);
        $this->assertSame(3, $result->piecesPlaced);
        $this->assertSame([0, 1], array_column($result->layouts, 'index'));
    }

    public function test_smaller_pieces_backfill_an_earlier_sheet(): void
    {
        $result = $this->estimator()->estimate(
            new Sheet(2440, 1220),
            [new Piece(1200, 1200, 3, 'BIG'), new Piece(300, 300, 2, 'SMALL')],
            EstimatorMode::FixedOrientation,
        );

        $this->assertSame(2, $result->sheetsConsumed);
        // The small pieces fit beside the third BIG piece on sheet 2.
        $this->assertSame(3, $result->perSheetPieces[1]['total']);
    }

    public function test_zero_quantity_rows_are_dropped_without_consuming_a_sheet(): void
    {
        $result = $this->estimator()->estimate(
            new Sheet(2440, 1220),
            [new Piece(600, 400, 0, 'NONE')],
            EstimatorMode::FixedOrientation,
        );

        $this->assertSame(0, $result->sheetsConsumed);
        $this->assertSame([], $result->layouts);
        $this->assertEqualsWithDelta(0.0, $result->totalCutLengthMm, 0.001);
        $this->assertSame(0, $result->piecesPlaced);
    }

    public function test_piece_exactly_equal_to_the_usable_area_needs_only_trim_cuts(): void
    {
        $result = $this->estimator()->estimate(
            new Sheet(2440, 1220),
            [new Piece(2420, 1200, 1, 'EXACT')],
            EstimatorMode::FixedOrientation,
        );

        $this->assertSame(1, $result->sheetsConsumed);
        $this->assertEqualsWithDelta(0.0, $result->pieceCutLengthMm, 0.001);
        $this->assertEqualsWithDelta(7320.0, $result->totalCutLengthMm, 0.001);
        $this->assertSame([], $result->layouts[0]->offcuts);
    }

    public function test_one_millimetre_over_the_usable_area_is_a_structured_error(): void
    {
        $this->expectException(PieceExceedsSheetException::class);

        $this->estimator()->estimate(
            new Sheet(2440, 1220),
            [new Piece(2421, 1200, 1, 'OVERSIZE')],
            EstimatorMode::FixedOrientation,
        );
    }

    public function test_the_error_names_every_offending_piece(): void
    {
        try {
            $this->estimator()->estimate(
                new Sheet(2440, 1220),
                [
                    new Piece(600, 400, 1, 'OK'),
                    new Piece(2500, 400, 1, 'TOO-WIDE'),
                    new Piece(600, 1500, 1, 'TOO-TALL'),
                ],
                EstimatorMode::FixedOrientation,
            );
            $this->fail('Expected PieceExceedsSheetException.');
        } catch (PieceExceedsSheetException $e) {
            $this->assertSame(['TOO-WIDE', 'TOO-TALL'], array_column($e->pieces, 'label'));
            $this->assertSame('piece_exceeds_sheet', $e->toArray()['error']);
        }
    }

    public function test_non_positive_dimensions_are_rejected(): void
    {
        $this->expectException(InvalidCutListException::class);

        new Piece(0, 400, 1, 'ZERO');
    }

    public function test_trim_larger_than_the_sheet_is_rejected(): void
    {
        $this->expectException(InvalidCutListException::class);

        (new Estimator(new CutConfig(kerfMm: 4.4, trimMm: 700.0)))->estimate(
            new Sheet(2440, 1220),
            [new Piece(100, 100, 1, 'A')],
            EstimatorMode::FixedOrientation,
        );
    }

    public function test_offcuts_are_reported_with_sheet_coordinates(): void
    {
        $result = $this->estimator()->estimate(
            new Sheet(2440, 1220),
            [new Piece(600, 400, 1, 'A')],
            EstimatorMode::FixedOrientation,
        );

        $this->assertNotSame([], $result->offcuts);

        foreach ($result->offcuts as $offcut) {
            $this->assertSame(0, $offcut['sheet_index']);
            $this->assertGreaterThan(0, $offcut['w']);
            $this->assertGreaterThan(0, $offcut['h']);
        }

        // Trailing strip beside the piece plus the band below its shelf.
        $this->assertGreaterThanOrEqual(2, count($result->offcuts));
    }
}
