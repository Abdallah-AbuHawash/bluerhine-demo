<?php

namespace App\Models;

use App\Services\Cutting\Sheet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['available_sheets'];

    protected function casts(): array
    {
        return [
            'thickness_mm' => 'float',
            'sheet_w_mm' => 'float',
            'sheet_h_mm' => 'float',
            'selling_price_aed' => 'float',
            'stock_qty' => 'integer',
            'rotation_allowed' => 'boolean',
            'is_cut_eligible' => 'boolean',
        ];
    }

    public function softAllocations(): HasMany
    {
        return $this->hasMany(SoftAllocation::class);
    }

    public function cuttingRate(): ?CuttingRate
    {
        return CuttingRate::forMaterial($this);
    }

    public function sheet(): Sheet
    {
        return new Sheet($this->sheet_w_mm, $this->sheet_h_mm);
    }

    /** Stock minus every soft allocation that has not expired yet. */
    public function availableSheets(): int
    {
        $allocated = $this->relationLoaded('softAllocations')
            ? $this->softAllocations->filter(fn (SoftAllocation $a) => $a->isActive())->sum('qty_sheets')
            : $this->softAllocations()->active()->sum('qty_sheets');

        return max(0, $this->stock_qty - (int) $allocated);
    }

    /** Same value, exposed to Inertia/JSON as `available_sheets`. */
    public function getAvailableSheetsAttribute(): int
    {
        return $this->availableSheets();
    }

    public function scopeCutEligible($query)
    {
        return $query->where('is_cut_eligible', true);
    }
}
