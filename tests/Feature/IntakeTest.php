<?php

namespace Tests\Feature;

use App\Models\IntakeSubmission;
use App\Models\Material;
use App\Models\User;
use App\Services\Intake\IntakeService;
use App\Services\Intake\MaterialMatcher;
use App\Services\Intake\OfflineCutListParser;
use Database\Seeders\CuttingRateSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\ShopFloorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class IntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([MaterialSeeder::class, CuttingRateSeeder::class, ShopFloorSeeder::class]);
        config()->set('services.anthropic.demo_offline', true);
    }

    public function test_fixture_a_parses_the_ps1_bom_offline(): void
    {
        $result = (new OfflineCutListParser)->parse(OfflineCutListParser::FIXTURE_A);

        $this->assertSame('offline_fixture', $result->source);
        $this->assertCount(4, $result->pieces);
        $this->assertSame(1200.0, $result->pieces[0]['width_mm']);
        $this->assertSame(1200.0, $result->pieces[0]['height_mm']);
        $this->assertSame(4, $result->pieces[0]['qty']);
        $this->assertSame(6.0, $result->pieces[0]['thickness_mm']);
        $this->assertSame(1000.0, $result->pieces[3]['width_mm']);
        $this->assertSame(5.0, $result->pieces[3]['thickness_mm']);
    }

    public function test_fixture_b_converts_centimetres_to_millimetres(): void
    {
        $result = (new OfflineCutListParser)->parse(OfflineCutListParser::FIXTURE_B);

        $this->assertSame([600.0, 400.0], [$result->pieces[0]['width_mm'], $result->pieces[0]['height_mm']]);
        $this->assertSame(6, $result->pieces[0]['qty']);
        $this->assertSame([1200.0, 800.0], [$result->pieces[1]['width_mm'], $result->pieces[1]['height_mm']]);
        $this->assertSame([500.0, 500.0], [$result->pieces[2]['width_mm'], $result->pieces[2]['height_mm']]);
        $this->assertNotEmpty($result->warnings);
    }

    public function test_an_unknown_paste_still_yields_rows_from_the_heuristic(): void
    {
        $result = (new OfflineCutListParser)->parse('need 3 pcs 800x450 5mm black hdpe please');

        $this->assertCount(1, $result->pieces);
        $this->assertSame(800.0, $result->pieces[0]['width_mm']);
        $this->assertSame(450.0, $result->pieces[0]['height_mm']);
        $this->assertSame(3, $result->pieces[0]['qty']);
        $this->assertSame(5.0, $result->pieces[0]['thickness_mm']);
        $this->assertLessThan(0.6, $result->confidence);
    }

    public function test_fixture_a_hints_map_to_the_polycarbonate_and_hdpe_skus(): void
    {
        $matcher = new MaterialMatcher;

        $this->assertSame('PC6.0-000-84-XX', $matcher->suggest('clear PC', 6.0)?->sku);

        $hdpe = $matcher->suggest('HDPE cable-duct sheet', 5.0);
        $this->assertSame('hdpe', $hdpe?->material_group);
        $this->assertSame(5.0, $hdpe?->thickness_mm);
    }

    public function test_fixture_b_hints_map_to_the_opal_and_mirror_skus(): void
    {
        $matcher = new MaterialMatcher;

        $this->assertSame('AS3.0-430-84-XX', $matcher->suggest('3mm opal white acrylic', 3.0)?->sku);

        $mirror = $matcher->suggest('mirror silver 3mm', 3.0);
        $this->assertSame('AM3.0-SLV-84-MR', $mirror?->sku);
        $this->assertFalse($mirror->rotation_allowed);
    }

    public function test_a_hint_with_no_recognisable_material_stays_unresolved(): void
    {
        $this->assertNull((new MaterialMatcher)->suggest('something we do not stock', null));
    }

    public function test_the_intake_screen_offers_both_fixtures(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/intake')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Intake/Create')
                ->has('examples', 2)
                ->where('apiAvailable', false));
    }

    public function test_a_paste_is_stored_and_parsed(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/intake', ['raw_input' => OfflineCutListParser::FIXTURE_B])
            ->assertRedirect();

        $submission = IntakeSubmission::latest('id')->firstOrFail();

        $this->assertSame('paste', $submission->source_type);
        $this->assertSame('parsed', $submission->status);
        $this->assertTrue($submission->offline_fallback);
        $this->assertCount(3, $submission->parse_result['pieces']);
    }

    public function test_an_uploaded_file_is_parsed_the_same_way(): void
    {
        $file = UploadedFile::fake()->createWithContent('bom.txt', OfflineCutListParser::FIXTURE_A);

        $this->actingAs(User::factory()->create())
            ->post('/intake', ['file' => $file])
            ->assertRedirect();

        $submission = IntakeSubmission::latest('id')->firstOrFail();

        $this->assertSame('file', $submission->source_type);
        $this->assertSame('bom.txt', $submission->file_name);
        $this->assertCount(4, $submission->parse_result['pieces']);
    }

    public function test_the_review_screen_pre_selects_a_material_for_each_row(): void
    {
        $submission = (new IntakeService)->submit(OfflineCutListParser::FIXTURE_B);

        $this->actingAs(User::factory()->create())
            ->get("/intake/{$submission->id}/review")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Intake/Review')
                ->has('rows', 3)
                ->where('rows.0.material_id', Material::where('sku', 'AS3.0-430-84-XX')->value('id'))
                ->where('rows.2.material_id', Material::where('sku', 'AM3.0-SLV-84-MR')->value('id'))
                ->has('materials'));
    }

    public function test_approving_carries_the_rows_into_the_estimate_screen(): void
    {
        $submission = (new IntakeService)->submit(OfflineCutListParser::FIXTURE_A);
        $pc = Material::where('sku', 'PC6.0-000-84-XX')->firstOrFail();
        $hdpe = Material::where('sku', 'HD5.0-900-84-XX')->firstOrFail();

        $response = $this->actingAs(User::factory()->create())
            ->post("/intake/{$submission->id}/approve", [
                'customer_name' => 'Aisle One Contracting',
                'customer_reference' => 'PO-4471',
                'mode' => 'optimized',
                'rows' => [
                    ['material_id' => $pc->id, 'width_mm' => 1200, 'height_mm' => 1200, 'qty' => 4, 'label' => 'PS1-PNL-CT-1212'],
                    ['material_id' => $pc->id, 'width_mm' => 1200, 'height_mm' => 200, 'qty' => 6, 'label' => 'PS1-PNL-IF-1220'],
                    ['material_id' => $hdpe->id, 'width_mm' => 1000, 'height_mm' => 600, 'qty' => 6, 'label' => 'HDPE duct'],
                ],
            ]);

        $response->assertRedirect(route('estimates.create'));
        $this->assertSame('reviewed', $submission->fresh()->status);

        // One quote line per SKU, rows grouped underneath.
        $prefill = session('estimate_prefill');
        $this->assertCount(2, $prefill['lines']);
        $this->assertSame('Aisle One Contracting', $prefill['customer_name']);
        $this->assertCount(2, $prefill['lines'][0]['rows']);
        $this->assertCount(1, $prefill['lines'][1]['rows']);
    }

    public function test_the_estimate_screen_receives_the_prefill(): void
    {
        $material = Material::where('sku', 'PC6.0-000-84-XX')->firstOrFail();

        $this->actingAs(User::factory()->create())
            ->withSession(['estimate_prefill' => [
                'customer_name' => 'Aisle One Contracting',
                'customer_reference' => null,
                'lines' => [[
                    'material_id' => $material->id,
                    'mode' => 'optimized',
                    'rows' => [['label' => 'CT', 'width' => '1200', 'height' => '1200', 'qty' => '4']],
                ]],
            ]])
            ->get('/estimates/new')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Estimates/New')
                ->where('prefill.customer_name', 'Aisle One Contracting')
                ->has('prefill.lines', 1));
    }
}
