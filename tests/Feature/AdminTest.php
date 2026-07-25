<?php

namespace Tests\Feature;

use App\Models\CutParameter;
use App\Models\CuttingRate;
use App\Models\LeadTimeRule;
use App\Models\Material;
use App\Models\Quote;
use App\Models\User;
use App\Services\Cutting\EstimatorMode;
use App\Services\Quoting\QuoteBuilder;
use App\Services\Quoting\QuoteIssuer;
use Database\Seeders\CuttingRateSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\ShopFloorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([MaterialSeeder::class, CuttingRateSeeder::class, ShopFloorSeeder::class]);
    }

    private function rows(): array
    {
        return [['width' => 600, 'height' => 400, 'qty' => 12, 'label' => 'OPAL']];
    }

    private function opal(): Material
    {
        return Material::where('sku', 'AS3.0-430-84-XX')->firstOrFail();
    }

    public function test_the_admin_screen_renders_rates_parameters_and_capacity(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Index')
                ->where('parameters.kerf_mm', 4.4)
                // JSON renders a whole-number float as an int.
                ->where('parameters.trim_mm', 10)
                ->has('rates', 10)
                ->has('leadTimes', 7)
                ->has('load', 10));
    }

    public function test_cut_parameters_can_be_edited(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/admin/cut-parameters', [
                'kerf_mm' => 3.2,
                'trim_mm' => 12,
                'vat_pct' => 5,
                'quote_validity_days' => 14,
                'include_trim_in_cut_length' => false,
            ])
            ->assertRedirect();

        $parameters = CutParameter::current()->fresh();

        $this->assertSame(3.2, $parameters->kerf_mm);
        $this->assertSame(14, $parameters->quote_validity_days);
        $this->assertFalse($parameters->include_trim_in_cut_length);
    }

    public function test_the_rate_unit_can_be_switched_from_admin(): void
    {
        $rate = CuttingRate::where('material_group', 'acrylic_cast')->where('thickness_mm', 3.0)->firstOrFail();

        $this->actingAs(User::factory()->create())
            ->put("/admin/cutting-rates/{$rate->id}", ['rate' => 9.25, 'rate_unit' => 'per_piece'])
            ->assertRedirect();

        $this->assertSame(9.25, $rate->fresh()->rate);
        $this->assertSame('per_piece', $rate->fresh()->rate_unit);
    }

    public function test_rates_can_be_added_and_removed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/admin/cutting-rates', [
            'material_group' => 'polycarbonate',
            'thickness_mm' => 8.0,
            'rate' => 14.5,
            'rate_unit' => 'per_cut_metre',
        ])->assertRedirect();

        $rate = CuttingRate::where('material_group', 'polycarbonate')->where('thickness_mm', 8.0)->firstOrFail();

        $this->actingAs($user)->delete("/admin/cutting-rates/{$rate->id}")->assertRedirect();
        $this->assertNull(CuttingRate::find($rate->id));
    }

    public function test_capacity_edits_move_the_promised_date(): void
    {
        $user = User::factory()->create();
        $monday = LeadTimeRule::where('weekday', 1)->firstOrFail();

        $this->actingAs($user)
            ->put("/admin/lead-time-rules/{$monday->id}", ['capacity_cut_metres' => 0])
            ->assertRedirect();

        $this->assertSame(0, $monday->fresh()->capacity_cut_metres);
    }

    /**
     * The centrepiece of the admin milestone: an issued quote is immune to
     * later admin edits, and a new estimate picks the new numbers up.
     */
    public function test_admin_edits_do_not_touch_an_issued_quote(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/estimates', [
            'customer_name' => 'Aisle One Contracting',
            'lines' => [[
                'material_id' => $this->opal()->id,
                'mode' => 'optimized',
                'rows' => $this->rows(),
            ]],
        ]);

        $quote = Quote::latest('id')->firstOrFail();
        $this->actingAs($user)->post("/quotes/{$quote->id}/issue");

        $issued = $quote->fresh('lines');
        $frozenTotal = $issued->total_aed;
        $frozenKerf = $issued->lines->first()->frozen('parameters.kerf_mm');

        // Admin changes everything that feeds pricing.
        $rate = CuttingRate::where('material_group', 'acrylic_cast')->where('thickness_mm', 3.0)->firstOrFail();
        $this->actingAs($user)->put("/admin/cutting-rates/{$rate->id}", ['rate' => 99, 'rate_unit' => 'per_sheet']);
        $this->actingAs($user)->put('/admin/cut-parameters', [
            'kerf_mm' => 8.0,
            'trim_mm' => 25,
            'vat_pct' => 10,
            'quote_validity_days' => 30,
            'include_trim_in_cut_length' => false,
        ]);

        // The issued quote is unchanged, down to the frozen kerf.
        $this->actingAs($user)
            ->get("/quotes/{$issued->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('quote.total_aed', $frozenTotal)
                ->where('lines.0.snapshot.parameters.kerf_mm', $frozenKerf)
                ->where('lines.0.snapshot.cutting_rate.rate', 8.5)
                ->where('lines.0.snapshot.cutting_rate.rate_unit', 'per_cut_metre'));

        // A new estimate uses the new settings.
        $fresh = (new QuoteBuilder)->previewLine($this->opal()->fresh(), $this->rows(), EstimatorMode::Optimized);

        $this->assertSame(99.0, $fresh['cutting_rate']['rate']);
        $this->assertSame('per_sheet', $fresh['cutting_rate']['rate_unit']);
        $this->assertSame(8.0, $fresh['parameters']['kerf_mm']);
        $this->assertSame(10.0, $fresh['pricing']['vat_pct']);
        $this->assertNotSame($frozenTotal, $fresh['pricing']['total_aed']);
    }

    public function test_a_quote_issued_after_the_edit_uses_the_new_numbers(): void
    {
        $rate = CuttingRate::where('material_group', 'acrylic_cast')->where('thickness_mm', 3.0)->firstOrFail();
        $rate->update(['rate' => 20.0]);

        $quote = (new QuoteBuilder)->createDraft('Later Customer', null, [
            ['material' => $this->opal(), 'rows' => $this->rows(), 'mode' => EstimatorMode::Optimized],
        ]);

        $issued = (new QuoteIssuer)->issue($quote);

        $this->assertEquals(20.0, $issued->lines->first()->frozen('cutting_rate.rate'));
    }
}
