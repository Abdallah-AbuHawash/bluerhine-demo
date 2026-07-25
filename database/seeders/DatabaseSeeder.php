<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Single demo user — no auth work beyond a trivial login.
        User::updateOrCreate(
            ['email' => 'demo@cuttosize.test'],
            ['name' => 'Demo Estimator', 'password' => Hash::make('password')],
        );

        $this->call([
            MaterialSeeder::class,
            CuttingRateSeeder::class,
            ShopFloorSeeder::class,
        ]);
    }
}
