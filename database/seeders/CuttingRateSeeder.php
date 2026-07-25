<?php

namespace Database\Seeders;

use App\Models\CuttingRate;
use Illuminate\Database\Seeder;

/**
 * Rates in AED per cut metre — thicker stock cuts slower, so it costs more.
 * The unit itself is unconfirmed by the client, hence rate_unit.
 */
class CuttingRateSeeder extends Seeder
{
    public function run(): void
    {
        $rates = [
            ['acrylic_cast', 2.8, 8.00],
            ['acrylic_cast', 3.0, 8.50],
            ['acrylic_cast', 5.0, 11.00],
            ['acrylic_cast', 10.0, 16.00],
            ['acrylic_mirror', 3.0, 10.50],
            ['polycarbonate', 3.0, 9.00],
            ['polycarbonate', 4.0, 10.50],
            ['polycarbonate', 6.0, 13.00],
            ['hdpe', 5.0, 12.00],
            ['hdpe', 10.0, 17.50],
        ];

        foreach ($rates as [$group, $thickness, $rate]) {
            CuttingRate::updateOrCreate(
                ['material_group' => $group, 'thickness_mm' => $thickness],
                ['rate' => $rate, 'rate_unit' => 'per_cut_metre'],
            );
        }
    }
}
