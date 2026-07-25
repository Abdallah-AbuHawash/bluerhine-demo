<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadTimeRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'capacity_cut_metres' => 'integer',
        ];
    }

    public function weekdayName(): string
    {
        return [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ][$this->weekday] ?? 'Unknown';
    }
}
