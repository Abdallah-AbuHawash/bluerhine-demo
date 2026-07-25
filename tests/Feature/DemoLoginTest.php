<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DemoLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeded_account_comes_from_configuration(): void
    {
        config([
            'demo.user.name' => 'Suhaib',
            'demo.user.email' => 'someone@example.test',
            'demo.user.password' => 'a-configured-password',
        ]);

        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'someone@example.test')->firstOrFail();

        $this->assertSame('Suhaib', $user->name);
        $this->assertTrue(Hash::check('a-configured-password', $user->password));
        $this->assertNull(User::where('email', 'demo@cuttosize.test')->first());
    }

    public function test_the_configured_account_can_log_in(): void
    {
        config([
            'demo.user.email' => 'someone@example.test',
            'demo.user.password' => 'a-configured-password',
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->post('/login', [
            'email' => 'someone@example.test',
            'password' => 'a-configured-password',
        ])->assertRedirect(route('estimates.create'));

        $this->assertAuthenticated();
    }

    public function test_credentials_are_never_pre_filled_once_they_are_configured(): void
    {
        config(['demo.prefill_login' => false, 'demo.user.email' => 'someone@example.test']);

        $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/Login')
            ->where('demoEmail', '')
            ->where('demoPassword', '')
            ->where('showHint', false));
    }

    public function test_local_defaults_still_pre_fill_for_convenience(): void
    {
        config([
            'demo.prefill_login' => true,
            'demo.user.email' => 'demo@cuttosize.test',
            'demo.user.password' => 'password',
        ]);

        $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('demoEmail', 'demo@cuttosize.test')
            ->where('showHint', true));
    }
}
