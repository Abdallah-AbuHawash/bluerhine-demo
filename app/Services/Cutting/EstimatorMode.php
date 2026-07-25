<?php

namespace App\Services\Cutting;

enum EstimatorMode: string
{
    /** Pieces placed exactly as given. Must reproduce the client workbook. */
    case FixedOrientation = 'fixed';

    /** May rotate pieces 90 degrees where the material allows it. */
    case Optimized = 'optimized';
}
