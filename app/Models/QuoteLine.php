<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'sheets_consumed' => 'integer',
            'cut_metres' => 'float',
            'material_total_aed' => 'float',
            'cutting_total_aed' => 'float',
            'line_total_aed' => 'float',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function cutJobs(): HasMany
    {
        return $this->hasMany(CutJob::class);
    }

    /**
     * Everything an issued line displays comes from here — never from the live
     * materials, cutting_rates or cut_parameters tables.
     */
    public function frozen(string $key, mixed $default = null): mixed
    {
        return data_get($this->snapshot, $key, $default);
    }
}
