<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'material_total_aed' => 'float',
            'cutting_total_aed' => 'float',
            'subtotal_aed' => 'float',
            'vat_pct' => 'float',
            'vat_aed' => 'float',
            'total_aed' => 'float',
            'promised_date' => 'date',
            'valid_until' => 'date',
            'issued_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class);
    }

    public function softAllocations(): HasMany
    {
        return $this->hasMany(SoftAllocation::class);
    }

    public function isIssued(): bool
    {
        return in_array($this->status, ['issued', 'ordered'], true);
    }

    public static function nextReference(): string
    {
        $year = now()->year;
        $count = static::whereYear('created_at', $year)->count() + 1;

        return sprintf('Q-%d-%04d', $year, $count);
    }
}
