<?php

namespace App\Models;

use App\Services\Cutting\CutConfig;
use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row holding the shop-floor parameters. Admin edits affect new
 * quotes only — issued quotes read their frozen snapshot.
 */
class CutParameter extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kerf_mm' => 'float',
            'trim_mm' => 'float',
            'vat_pct' => 'float',
            'quote_validity_days' => 'integer',
            'include_trim_in_cut_length' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], []);
    }

    public function toCutConfig(bool $rotationAllowed = false): CutConfig
    {
        return new CutConfig(
            kerfMm: $this->kerf_mm,
            trimMm: $this->trim_mm,
            rotationAllowed: $rotationAllowed,
            includeTrimInCutLength: $this->include_trim_in_cut_length,
        );
    }
}
