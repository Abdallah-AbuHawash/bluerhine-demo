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
        // Single demo user — no auth work beyond a trivial login. Credentials
        // come from config/demo.php so a hosted demo can set its own without
        // them ever being committed.
        User::updateOrCreate(
            ['email' => config('demo.user.email')],
            [
                'name' => config('demo.user.name'),
                'password' => Hash::make(config('demo.user.password')),
            ],
        );

        $this->call([
            MaterialSeeder::class,
            CuttingRateSeeder::class,
            ShopFloorSeeder::class,
        ]);
    }
}
