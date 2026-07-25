<?php

namespace App\Services\Cutting\Strategy;

/**
 * Deterministic candidate strategies. Optimized mode runs all of the ones the
 * material allows and keeps the best result; AsGiven is always among them,
 * which is what makes "never worse than FixedOrientation" structural.
 */
enum OrientationPolicy: string
{
    case AsGiven = 'as_given';
    case PreferLandscape = 'prefer_landscape';
    case PreferPortrait = 'prefer_portrait';
    case BestFitPerPiece = 'best_fit_per_piece';

    public function usesRotation(): bool
    {
        return $this !== self::AsGiven;
    }
}
