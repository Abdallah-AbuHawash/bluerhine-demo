<?php

namespace Tests\Feature;

use App\Models\CutJob;
use App\Models\CuttingRate;
use App\Models\Material;
use App\Models\Quote;
use App\Models\User;
use App\Services\Cutting\EstimatorMode;
use App\Services\Ordering\NetSuitePayloadBuilder;
use App\Services\Quoting\QuoteBuilder;
use App\Services\Quoting\QuoteIssuer;
use Database\Seeders\CuttingRateSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\ShopFloorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OrderPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([MaterialSeeder::class, CuttingRateSeeder::class, ShopFloorSeeder::class]);
    }

    private function issuedQuote(): Quote
    {
        $quote = (new QuoteBuilder)->createDraft('Aisle One Contracting', 'PO-4471', [
            [
                'material' => Material::where('sku', 'PC6.0-000-84-XX')->firstOrFail(),
                'rows' => [['width' => 1200, 'height' => 1200, 'qty' => 4, 'label' => 'PS1-PNL-CT-1212']],
                'mode' => EstimatorMode::Optimized,
            ],
            [
                'material' => Material::where('sku', 'HD5.0-900-84-XX')->firstOrFail(),
                'rows' => [['width' => 1000, 'height' => 600, 'qty' => 6, 'label' => 'HDPE duct']],
                'mode' => EstimatorMode::Optimized,
            ],
        ]);

        return (new QuoteIssuer)->issue($quote);
    }

    public function test_the_payload_matches_the_client_envelope_shape(): void
    {
        $quote = $this->issuedQuote();
        $payload = (new NetSuitePayloadBuilder)->build($quote);

        $entity = $payload['param'][0]['entity'];

        $this->assertSame('AED', $entity['currency']);
        $this->assertSame('Aisle One Contracting', $entity['customer']);
        $this->assertStringStartsWith('SO-', $entity['orderId']);
        $this->assertStringContainsString($quote->reference, $entity['remarks']);
        $this->assertStringContainsString('PO-4471', $entity['remarks']);
        $this->assertSame($quote->issued_at->toDateString(), $entity['order_date']);
    }

    public function test_every_sheet_line_plus_exactly_one_cut_charge_service_line(): void
    {
        $quote = $this->issuedQuote();
        $items = (new NetSuitePayloadBuilder)->build($quote)['param'][0]['entity']['items'];

        $this->assertCount(3, $items); // two SKUs + one service line

        $skus = array_column($items, 'item_code');
        $this->assertContains('PC6.0-000-84-XX', $skus);
        $this->assertContains('HD5.0-900-84-XX', $skus);
        $this->assertSame(1, count(array_filter($skus, fn ($code) => $code === 'CUT-CHARGE')));

        $service = $items[count($items) - 1];
        $this->assertSame('CUT-CHARGE', $service['item_code']);
        $this->assertSame('Cut-to-size service', $service['description']);
        $this->assertSame(1, $service['qty']);
        $this->assertSame(round($quote->cutting_total_aed, 2), $service['rate']);
        $this->assertArrayNotHasKey('tax_rate', $service);

        foreach (array_slice($items, 0, 2) as $item) {
            $this->assertSame(5.0, $item['tax_rate']);
            $this->assertGreaterThan(0, $item['qty']);
        }
    }

    public function test_the_payload_is_built_from_the_snapshot_not_live_prices(): void
    {
        $quote = $this->issuedQuote();
        $before = (new NetSuitePayloadBuilder)->build($quote);

        Material::where('sku', 'PC6.0-000-84-XX')->update(['selling_price_aed' => 999]);
        CuttingRate::where('material_group', 'polycarbonate')->update(['rate' => 500]);

        $after = (new NetSuitePayloadBuilder)->build($quote->fresh('lines'));

        $this->assertSame($before, $after);
    }

    public function test_the_order_preview_screen_shows_both_payloads(): void
    {
        $quote = $this->issuedQuote();

        $this->actingAs(User::factory()->create())
            ->get("/quotes/{$quote->id}/order")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Quotes/Order')
                ->has('payload.param.0.entity.items', 3)
                ->has('samplePayload.param.0.entity')
                ->where('quote.reference', $quote->reference));
    }

    public function test_a_draft_quote_has_no_order_preview(): void
    {
        $quote = (new QuoteBuilder)->createDraft('Draft Co', null, [[
            'material' => Material::where('sku', 'PC6.0-000-84-XX')->firstOrFail(),
            'rows' => [['width' => 600, 'height' => 400, 'qty' => 2, 'label' => 'A']],
            'mode' => EstimatorMode::Optimized,
        ]]);

        $this->actingAs(User::factory()->create())
            ->get("/quotes/{$quote->id}/order")
            ->assertNotFound();
    }

    public function test_converting_schedules_cut_jobs_and_marks_the_quote_ordered(): void
    {
        $quote = $this->issuedQuote();

        $this->actingAs(User::factory()->create())
            ->post("/quotes/{$quote->id}/convert")
            ->assertRedirect(route('quotes.order', $quote));

        $this->assertSame('ordered', $quote->fresh()->status);
        $this->assertSame(2, CutJob::count());
    }
}
