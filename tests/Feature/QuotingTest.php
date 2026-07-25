<?php

namespace Tests\Feature;

use App\Models\CutJob;
use App\Models\CuttingRate;
use App\Models\Material;
use App\Models\Quote;
use App\Models\SoftAllocation;
use App\Services\Cutting\EstimatorMode;
use App\Services\Quoting\LeadTimeScheduler;
use App\Services\Quoting\QuoteBuilder;
use App\Services\Quoting\QuoteIssuer;
use Carbon\CarbonImmutable;
use Database\Seeders\CuttingRateSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\ShopFloorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([MaterialSeeder::class, CuttingRateSeeder::class, ShopFloorSeeder::class]);
    }

    private function opalAcrylic(): Material
    {
        return Material::where('sku', 'AS3.0-430-84-XX')->firstOrFail();
    }

    private function rows(): array
    {
        return [
            ['width' => 600, 'height' => 400, 'qty' => 6, 'label' => 'OPAL-600x400'],
            ['width' => 1200, 'height' => 800, 'qty' => 2, 'label' => 'OPAL-1200x800'],
        ];
    }

    public function test_a_preview_prices_material_cutting_and_vat(): void
    {
        $material = $this->opalAcrylic();

        $line = (new QuoteBuilder)->previewLine($material, $this->rows(), EstimatorMode::Optimized);

        $sheets = $line['engine']['sheets_consumed'];
        $cutMetres = $line['cut_metres'];
        $pricing = $line['pricing'];

        $this->assertSame(round($sheets * 180.0, 2), $pricing['material_total_aed']);
        $this->assertSame(round($cutMetres * 8.5, 2), $pricing['cutting_total_aed']);
        $this->assertSame(
            round($pricing['material_total_aed'] + $pricing['cutting_total_aed'], 2),
            $pricing['subtotal_aed'],
        );
        $this->assertSame(round($pricing['subtotal_aed'] * 0.05, 2), $pricing['vat_aed']);
        $this->assertSame(round($pricing['subtotal_aed'] * 1.05, 2), $pricing['total_aed']);
    }

    public function test_the_rate_unit_switch_changes_the_cutting_charge(): void
    {
        $material = $this->opalAcrylic();
        $rate = CuttingRate::where('material_group', 'acrylic_cast')->where('thickness_mm', 3.0)->firstOrFail();

        $perMetre = (new QuoteBuilder)->previewLine($material, $this->rows(), EstimatorMode::Optimized);

        $rate->update(['rate_unit' => 'per_piece']);
        $perPiece = (new QuoteBuilder)->previewLine($material, $this->rows(), EstimatorMode::Optimized);

        $rate->update(['rate_unit' => 'per_sheet']);
        $perSheet = (new QuoteBuilder)->previewLine($material, $this->rows(), EstimatorMode::Optimized);

        $this->assertSame(round($perMetre['cut_metres'] * 8.5, 2), $perMetre['pricing']['cutting_total_aed']);
        $this->assertSame(round(8 * 8.5, 2), $perPiece['pricing']['cutting_total_aed']);
        $this->assertSame(round($perSheet['engine']['sheets_consumed'] * 8.5, 2), $perSheet['pricing']['cutting_total_aed']);
    }

    public function test_a_directional_material_is_quoted_without_rotation(): void
    {
        $mirror = Material::where('sku', 'AM3.0-SLV-84-MR')->firstOrFail();

        $line = (new QuoteBuilder)->previewLine(
            $mirror,
            [['width' => 500, 'height' => 900, 'qty' => 4, 'label' => 'MIRROR']],
            EstimatorMode::Optimized,
        );

        $this->assertFalse($line['engine']['config']['rotation_allowed']);

        foreach ($line['engine']['layouts'] as $layout) {
            foreach ($layout['placements'] as $placement) {
                $this->assertFalse($placement['rotated']);
            }
        }
    }

    public function test_a_draft_quote_persists_a_full_snapshot(): void
    {
        $quote = (new QuoteBuilder)->createDraft('Aisle One Contracting', 'PO-4471', [
            ['material' => $this->opalAcrylic(), 'rows' => $this->rows(), 'mode' => EstimatorMode::Optimized],
        ]);

        $line = $quote->lines->first();

        $this->assertSame('draft', $quote->status);
        $this->assertStringStartsWith('Q-', $quote->reference);
        $this->assertSame($quote->total_aed, $line->line_total_aed);

        foreach (['engine', 'material', 'cutting_rate', 'parameters', 'pricing'] as $key) {
            $this->assertArrayHasKey($key, $line->snapshot);
        }

        $this->assertSame('AS3.0-430-84-XX', $line->frozen('material.sku'));
        $this->assertSame(8.5, $line->frozen('cutting_rate.rate'));
        $this->assertSame(4.4, $line->frozen('parameters.kerf_mm'));
        $this->assertNotEmpty($line->frozen('engine.layouts.0.tree'));
    }

    public function test_issuing_freezes_the_quote_and_soft_allocates_stock(): void
    {
        $material = $this->opalAcrylic();
        $before = $material->availableSheets();

        $quote = (new QuoteBuilder)->createDraft('Aisle One Contracting', null, [
            ['material' => $material, 'rows' => $this->rows(), 'mode' => EstimatorMode::Optimized],
        ]);

        $issued = (new QuoteIssuer)->issue($quote);
        $line = $issued->lines->first();

        $this->assertSame('issued', $issued->status);
        $this->assertNotNull($issued->issued_at);
        $this->assertNotNull($line->frozen('frozen_at'));

        $allocation = SoftAllocation::where('quote_id', $issued->id)->firstOrFail();
        $this->assertSame($line->sheets_consumed, $allocation->qty_sheets);
        $this->assertEqualsWithDelta(48, $allocation->created_at->diffInHours($allocation->expires_at), 1);

        $this->assertSame($before - $line->sheets_consumed, $material->fresh()->availableSheets());
    }

    public function test_expired_allocations_release_stock_again(): void
    {
        $material = $this->opalAcrylic();

        SoftAllocation::create([
            'material_id' => $material->id,
            'qty_sheets' => 10,
            'expires_at' => now()->subHour(),
        ]);

        $this->assertSame($material->stock_qty, $material->fresh()->availableSheets());
    }

    public function test_the_promised_date_falls_on_the_first_day_with_free_capacity(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-27 09:00:00')); // Monday

        $scheduler = new LeadTimeScheduler;

        $this->assertSame('2026-07-28', $scheduler->promisedDate(120)->toDateString());

        CarbonImmutable::setTestNow();
    }

    public function test_a_full_day_pushes_the_promise_to_the_next_working_day(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-27 09:00:00')); // Monday

        $quote = (new QuoteBuilder)->createDraft('Filler', null, [
            ['material' => $this->opalAcrylic(), 'rows' => $this->rows(), 'mode' => EstimatorMode::Optimized],
        ]);

        // Book out Tuesday completely.
        CutJob::create([
            'quote_line_id' => $quote->lines->first()->id,
            'cut_metres' => 400,
            'scheduled_date' => '2026-07-28',
        ]);

        $this->assertSame('2026-07-29', (new LeadTimeScheduler)->promisedDate(50)->toDateString());

        CarbonImmutable::setTestNow();
    }

    public function test_the_promise_skips_the_weekend(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-31 09:00:00')); // Friday

        // Saturday and Sunday have zero capacity, so the next slot is Monday.
        $this->assertSame('2026-08-03', (new LeadTimeScheduler)->promisedDate(50)->toDateString());

        CarbonImmutable::setTestNow();
    }

    public function test_converting_to_an_order_books_shop_floor_capacity(): void
    {
        $quote = (new QuoteBuilder)->createDraft('Aisle One Contracting', null, [
            ['material' => $this->opalAcrylic(), 'rows' => $this->rows(), 'mode' => EstimatorMode::Optimized],
        ]);

        $issued = (new QuoteIssuer)->issue($quote);
        $ordered = (new QuoteIssuer)->convertToOrder($issued);

        $this->assertSame('ordered', $ordered->status);
        $this->assertSame(1, CutJob::count());
        $this->assertEqualsWithDelta(
            $ordered->lines->first()->cut_metres,
            CutJob::first()->cut_metres,
            0.001,
        );
    }

    public function test_an_issued_quote_ignores_later_rate_and_parameter_changes(): void
    {
        $quote = (new QuoteBuilder)->createDraft('Aisle One Contracting', null, [
            ['material' => $this->opalAcrylic(), 'rows' => $this->rows(), 'mode' => EstimatorMode::Optimized],
        ]);

        $issued = (new QuoteIssuer)->issue($quote);
        $frozenTotal = $issued->total_aed;
        $frozenRate = $issued->lines->first()->frozen('cutting_rate.rate');

        CuttingRate::where('material_group', 'acrylic_cast')->where('thickness_mm', 3.0)
            ->update(['rate' => 99.00]);
        Material::where('sku', 'AS3.0-430-84-XX')->update(['selling_price_aed' => 999.00]);

        $reloaded = Quote::with('lines')->find($issued->id);

        $this->assertSame($frozenTotal, $reloaded->total_aed);
        $this->assertSame($frozenRate, $reloaded->lines->first()->frozen('cutting_rate.rate'));
        // JSON round-trips a whole-number float as an int, hence assertEquals.
        $this->assertEquals(180.0, $reloaded->lines->first()->frozen('material.selling_price_aed'));

        // A new quote picks up the new numbers.
        $fresh = (new QuoteBuilder)->previewLine(
            Material::where('sku', 'AS3.0-430-84-XX')->firstOrFail(),
            $this->rows(),
            EstimatorMode::Optimized,
        );

        $this->assertSame(99.0, $fresh['cutting_rate']['rate']);
        $this->assertSame(999.0, $fresh['material']['selling_price_aed']);
    }
}
