<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuttingRate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'thickness_mm' => 'float',
            'rate' => 'float',
        ];
    }

    /**
     * Exact group + thickness match first; otherwise the nearest thickness in
     * the same group, so a new SKU never leaves a quote unpriced.
     */
    public static function forMaterial(Material $material): ?self
    {
        $exact = static::where('material_group', $material->material_group)
            ->where('thickness_mm', $material->thickness_mm)
            ->first();

        if ($exact !== null) {
            return $exact;
        }

        return static::where('material_group', $material->material_group)
            ->get()
            ->sortBy([
                fn (self $rate) => abs($rate->thickness_mm - $material->thickness_mm),
                fn (self $rate) => $rate->thickness_mm,
            ])
            ->first();
    }

    public function label(): string
    {
        return match ($this->rate_unit) {
            'per_piece' => 'AED '.number_format($this->rate, 2).' per piece',
            'per_sheet' => 'AED '.number_format($this->rate, 2).' per sheet',
            default => 'AED '.number_format($this->rate, 2).' per cut metre',
        };
    }
}
