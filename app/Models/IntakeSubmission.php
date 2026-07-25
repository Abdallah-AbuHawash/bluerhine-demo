<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntakeSubmission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'parse_result' => 'array',
            'confidence' => 'float',
            'offline_fallback' => 'boolean',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
