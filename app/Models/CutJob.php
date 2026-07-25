<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CutJob extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cut_metres' => 'float',
            'scheduled_date' => 'date',
        ];
    }

    public function quoteLine(): BelongsTo
    {
        return $this->belongsTo(QuoteLine::class);
    }
}
