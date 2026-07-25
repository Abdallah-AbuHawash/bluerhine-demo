<?php

namespace App\Services\Cutting\Strategy;

use App\Services\Cutting\Layout\SheetLayout;
use App\Services\Cutting\PieceInstance;

final class PackResult
{
    /**
     * @param  array<int, SheetLayout>  $layouts
     * @param  array<int, PieceInstance>  $unplaceable
     */
    public function __construct(
        public readonly OrientationPolicy $policy,
        public readonly array $layouts,
        public readonly array $unplaceable,
    ) {}

    public function sheetsConsumed(): int
    {
        return count($this->layouts);
    }

    public function totalCutLengthMm(bool $includeTrim): float
    {
        $total = 0.0;

        foreach ($this->layouts as $layout) {
            $total += $layout->pieceCutLengthMm();

            if ($includeTrim) {
                $total += $layout->trimCutLengthMm();
            }
        }

        return $total;
    }
}
