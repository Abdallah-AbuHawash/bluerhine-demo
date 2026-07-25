<?php

namespace App\Services\Cutting;

use App\Services\Cutting\Exceptions\InvalidCutListException;
use App\Services\Cutting\Exceptions\PieceExceedsSheetException;
use App\Services\Cutting\Strategy\OrientationPolicy;
use App\Services\Cutting\Strategy\PackResult;
use App\Services\Cutting\Strategy\ShelfPacker;

/**
 * Guillotine cut-to-size estimator.
 *
 * Determinism is a hard requirement: no randomness, no time, no dependence on
 * input ordering (pieces are sorted by a total order before packing).
 */
final class Estimator
{
    private const EPS = 1e-6;

    private readonly ShelfPacker $packer;

    public function __construct(private readonly CutConfig $config = new CutConfig)
    {
        $this->packer = new ShelfPacker($this->config);
    }

    /**
     * @param  array<int, Piece|array>  $pieces
     */
    public function estimate(
        Sheet $sheet,
        array $pieces,
        EstimatorMode $mode = EstimatorMode::FixedOrientation,
    ): EstimateResult {
        $pieces = $this->normalisePieces($pieces);
        $usable = $this->packer->usableRect($sheet);

        if ($usable->width <= 0 || $usable->height <= 0) {
            throw new InvalidCutListException(
                'Edge trim leaves no usable area on this sheet — check trim settings.',
            );
        }

        $rotationAvailable = $this->config->rotationAllowed && $mode === EstimatorMode::Optimized;

        $this->assertPiecesFit($pieces, $usable->width, $usable->height, $rotationAvailable);

        $instances = $this->expand($pieces);

        if ($instances === []) {
            return new EstimateResult($mode, OrientationPolicy::AsGiven, $sheet, $this->config, []);
        }

        $policies = $mode === EstimatorMode::Optimized && $this->config->rotationAllowed
            ? [
                OrientationPolicy::AsGiven,
                OrientationPolicy::PreferLandscape,
                OrientationPolicy::PreferPortrait,
                OrientationPolicy::BestFitPerPiece,
            ]
            : [OrientationPolicy::AsGiven];

        $best = null;

        foreach ($policies as $rank => $policy) {
            $candidate = $this->packer->pack($sheet, $this->orderFor($instances, $policy), $policy);

            if ($best === null || $this->isBetter($candidate, $best['result'], $rank, $best['rank'])) {
                $best = ['result' => $candidate, 'rank' => $rank];
            }
        }

        /** @var PackResult $winner */
        $winner = $best['result'];

        return new EstimateResult(
            mode: $mode,
            strategy: $winner->policy,
            sheet: $sheet,
            config: $this->config,
            layouts: $winner->layouts,
            unplaceablePieces: array_map(
                fn (PieceInstance $instance) => [
                    'label' => $instance->label,
                    'width_mm' => $instance->widthMm,
                    'height_mm' => $instance->heightMm,
                ],
                $winner->unplaceable,
            ),
        );
    }

    /**
     * Optimized never loses to FixedOrientation because AsGiven — the exact
     * FixedOrientation packing — is always one of the ranked candidates.
     */
    private function isBetter(PackResult $candidate, PackResult $incumbent, int $rank, int $incumbentRank): bool
    {
        $a = [
            count($candidate->unplaceable),
            $candidate->sheetsConsumed(),
            round($candidate->totalCutLengthMm($this->config->includeTrimInCutLength), 4),
            $rank,
        ];
        $b = [
            count($incumbent->unplaceable),
            $incumbent->sheetsConsumed(),
            round($incumbent->totalCutLengthMm($this->config->includeTrimInCutLength), 4),
            $incumbentRank,
        ];

        return ($a <=> $b) < 0;
    }

    /**
     * @param  array<int, Piece|array>  $pieces
     * @return array<int, Piece>
     */
    private function normalisePieces(array $pieces): array
    {
        $normalised = [];

        foreach (array_values($pieces) as $index => $piece) {
            $normalised[] = $piece instanceof Piece ? $piece : Piece::fromArray((array) $piece, $index);
        }

        return $normalised;
    }

    /**
     * @param  array<int, Piece>  $pieces
     */
    private function assertPiecesFit(array $pieces, float $usableWidth, float $usableHeight, bool $rotationAvailable): void
    {
        $violations = [];

        foreach ($pieces as $piece) {
            if ($piece->qty === 0) {
                continue;
            }

            $fits = $piece->widthMm <= $usableWidth + self::EPS && $piece->heightMm <= $usableHeight + self::EPS;

            if (! $fits && $rotationAvailable) {
                $fits = $piece->heightMm <= $usableWidth + self::EPS && $piece->widthMm <= $usableHeight + self::EPS;
            }

            if (! $fits) {
                $violations[] = [
                    'label' => $piece->label,
                    'width_mm' => $piece->widthMm,
                    'height_mm' => $piece->heightMm,
                ];
            }
        }

        if ($violations !== []) {
            throw new PieceExceedsSheetException($violations, $usableWidth, $usableHeight);
        }
    }

    /**
     * @param  array<int, Piece>  $pieces
     * @return array<int, PieceInstance>
     */
    private function expand(array $pieces): array
    {
        $instances = [];
        $index = 0;

        foreach ($pieces as $pieceIndex => $piece) {
            for ($n = 0; $n < $piece->qty; $n++) {
                $instances[] = new PieceInstance(
                    label: $piece->label,
                    widthMm: $piece->widthMm,
                    heightMm: $piece->heightMm,
                    index: $index++,
                    pieceIndex: $pieceIndex,
                );
            }
        }

        return $instances;
    }

    /**
     * Apply the policy's orientation, then sort by a total order: area desc,
     * longest side desc, width desc, height desc, label asc, instance asc.
     *
     * @param  array<int, PieceInstance>  $instances
     * @return array<int, PieceInstance>
     */
    private function orderFor(array $instances, OrientationPolicy $policy): array
    {
        $oriented = array_map(
            fn (PieceInstance $instance) => $this->orient($instance, $policy),
            $instances,
        );

        usort($oriented, function (PieceInstance $a, PieceInstance $b): int {
            $descending = $this->sizeKey($b) <=> $this->sizeKey($a);

            if ($descending !== 0) {
                return $descending;
            }

            return [$a->label, $a->index] <=> [$b->label, $b->index];
        });

        return $oriented;
    }

    private function orient(PieceInstance $instance, OrientationPolicy $policy): PieceInstance
    {
        return match ($policy) {
            OrientationPolicy::PreferLandscape => $instance->heightMm > $instance->widthMm
                ? $instance->rotate()
                : $instance,
            OrientationPolicy::PreferPortrait => $instance->widthMm > $instance->heightMm
                ? $instance->rotate()
                : $instance,
            default => $instance,
        };
    }

    /** @return array<int, float> size key, compared descending */
    private function sizeKey(PieceInstance $instance): array
    {
        return [
            $instance->widthMm * $instance->heightMm,
            max($instance->widthMm, $instance->heightMm),
            $instance->widthMm,
            $instance->heightMm,
        ];
    }
}
