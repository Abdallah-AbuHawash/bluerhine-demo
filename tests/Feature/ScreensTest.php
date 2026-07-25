<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Quote;
use App\Models\User;
use Database\Seeders\CuttingRateSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\ShopFloorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([MaterialSeeder::class, CuttingRateSeeder::class, ShopFloorSeeder::class]);
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_guests_are_sent_to_the_login_screen(): void
    {
        $this->get('/estimates/new')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    /**
     * The Inertia client (@inertiajs/react 3.x) reads the initial page from a
     * JSON script tag. An older server adapter renders it as a div attribute
     * instead, which fails only in a browser — every server-side assertion
     * still passes. This pins the two halves together.
     */
    public function test_the_root_view_ships_the_page_payload_the_client_expects(): void
    {
        $html = $this->get('/login')->getContent();

        $this->assertStringContainsString('<script data-page="app" type="application/json">', $html);
        $this->assertStringContainsString('<div id="app"></div>', $html);
    }

    public function test_the_new_estimate_screen_lists_cut_eligible_materials(): void
    {
        $this->actingAs($this->user())
            ->get('/estimates/new')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Estimates/New')
                ->has('materials', 13)
                ->where('materials.0.available_sheets', fn ($value) => is_int($value)));
    }

    public function test_an_estimate_creates_a_draft_quote_with_a_renderable_layout(): void
    {
        $material = Material::where('sku', 'PC6.0-000-84-XX')->firstOrFail();

        $response = $this->actingAs($this->user())->post('/estimates', [
            'customer_name' => 'Aisle One Contracting',
            'customer_reference' => 'PO-4471',
            'lines' => [[
                'material_id' => $material->id,
                'mode' => 'optimized',
                'rows' => [
                    ['width' => 1200, 'height' => 1200, 'qty' => 4, 'label' => 'PS1-PNL-CT-1212'],
                    ['width' => 1200, 'height' => 200, 'qty' => 4, 'label' => 'PS1-PNL-IF-1220'],
                ],
            ]],
        ]);

        $quote = Quote::latest('id')->firstOrFail();
        $response->assertRedirect("/quotes/{$quote->id}");

        $this->actingAs($this->user())
            ->get("/quotes/{$quote->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Quotes/Show')
                ->where('quote.status', 'draft')
                ->has('lines.0.snapshot.engine.layouts.0.placements')
                ->has('lines.0.snapshot.engine.layouts.0.cuts')
                ->has('lines.0.snapshot.engine.layouts.0.tree')
                ->has('availability'));
    }

    public function test_an_oversized_piece_is_rejected_with_a_readable_message(): void
    {
        $material = Material::where('sku', 'AS3.0-430-84-XX')->firstOrFail();

        $this->actingAs($this->user())
            ->post('/estimates', [
                'customer_name' => 'Oversize Co',
                'lines' => [[
                    'material_id' => $material->id,
                    'mode' => 'fixed',
                    'rows' => [['width' => 2500, 'height' => 1400, 'qty' => 1, 'label' => 'TOO-BIG']],
                ]],
            ])
            ->assertSessionHasErrors('lines');

        $this->assertSame(0, Quote::count());
    }

    public function test_issuing_a_quote_from_the_screen_freezes_it_and_allocates_stock(): void
    {
        $material = Material::where('sku', 'AS3.0-430-84-XX')->firstOrFail();
        $before = $material->availableSheets();

        $this->actingAs($this->user())->post('/estimates', [
            'customer_name' => 'Aisle One Contracting',
            'lines' => [[
                'material_id' => $material->id,
                'mode' => 'optimized',
                'rows' => [['width' => 600, 'height' => 400, 'qty' => 12, 'label' => 'OPAL']],
            ]],
        ]);

        $quote = Quote::latest('id')->firstOrFail();

        $this->actingAs($this->user())
            ->post("/quotes/{$quote->id}/issue")
            ->assertRedirect("/quotes/{$quote->id}");

        $issued = $quote->fresh('lines');
        $this->assertSame('issued', $issued->status);
        $this->assertLessThan($before, $material->fresh()->availableSheets());

        $this->actingAs($this->user())
            ->get("/quotes/{$issued->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('quote.status', 'issued')
                ->where('availability.'.$material->id.'.available_sheets', $material->fresh()->availableSheets()));
    }

    public function test_the_quote_list_screen_renders(): void
    {
        $this->actingAs($this->user())
            ->get('/quotes')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Quotes/Index')->has('quotes'));
    }
}
