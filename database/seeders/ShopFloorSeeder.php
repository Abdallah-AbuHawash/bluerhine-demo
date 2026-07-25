<?php

namespace Database\Seeders;

use App\Models\CutParameter;
use App\Models\LeadTimeRule;
use Illuminate\Database\Seeder;

class ShopFloorSeeder extends Seeder
{
    public function run(): void
    {
        CutParameter::updateOrCreate(['id' => 1], [
            'kerf_mm' => 4.4,
            'trim_mm' => 10.0,
            'vat_pct' => 5.0,
            'quote_validity_days' => 7,
            'include_trim_in_cut_length' => true,
        ]);

        // ISO weekdays: 1 = Monday ... 7 = Sunday. The saw runs Mon-Fri.
        foreach ([1 => 400, 2 => 400, 3 => 400, 4 => 400, 5 => 400, 6 => 0, 7 => 0] as $weekday => $capacity) {
            LeadTimeRule::updateOrCreate(
                ['weekday' => $weekday],
                ['capacity_cut_metres' => $capacity],
            );
        }
    }
}
