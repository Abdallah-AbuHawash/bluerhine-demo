<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Services\Ordering\NetSuitePayloadBuilder;
use App\Services\Quoting\QuoteIssuer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Quotes/Index', [
            'quotes' => Quote::latest()->take(50)->get()->map(fn (Quote $quote) => [
                'id' => $quote->id,
                'reference' => $quote->reference,
                'customer_name' => $quote->customer_name,
                'status' => $quote->status,
                'total_aed' => $quote->total_aed,
                'created_at' => $quote->created_at->toDateString(),
            ]),
        ]);
    }

    public function show(Quote $quote): Response
    {
        return Inertia::render('Quotes/Show', $this->payload($quote));
    }

    public function issue(Quote $quote, QuoteIssuer $issuer): RedirectResponse
    {
        $issued = $issuer->issue($quote);

        return redirect()
            ->route('quotes.show', $issued)
            ->with('success', "Quote {$issued->reference} issued — stock soft-allocated for 48 hours.");
    }

    /** M6: renders the NetSuite-style payload, never sends it. */
    public function orderPreview(Quote $quote, NetSuitePayloadBuilder $builder): Response
    {
        abort_unless($quote->isIssued(), 404);

        return Inertia::render('Quotes/Order', [
            ...$this->payload($quote),
            'payload' => $builder->build($quote),
            'samplePayload' => $builder->clientSample(),
        ]);
    }

    public function convert(Quote $quote, QuoteIssuer $issuer): RedirectResponse
    {
        abort_unless($quote->isIssued(), 404);

        $ordered = $issuer->convertToOrder($quote);

        return redirect()
            ->route('quotes.order', $ordered)
            ->with('success', "Order created for {$ordered->reference} — cut jobs scheduled.");
    }

    /**
     * Everything shown comes from the frozen line snapshots. The only live
     * numbers are the availability counters, which are explicitly current.
     */
    private function payload(Quote $quote): array
    {
        $quote->load('lines.material', 'lines.cutJobs');

        return [
            'quote' => [
                'id' => $quote->id,
                'reference' => $quote->reference,
                'customer_name' => $quote->customer_name,
                'customer_reference' => $quote->customer_reference,
                'status' => $quote->status,
                'currency' => $quote->currency,
                'material_total_aed' => $quote->material_total_aed,
                'cutting_total_aed' => $quote->cutting_total_aed,
                'subtotal_aed' => $quote->subtotal_aed,
                'vat_pct' => $quote->vat_pct,
                'vat_aed' => $quote->vat_aed,
                'total_aed' => $quote->total_aed,
                'promised_date' => $quote->promised_date?->toDateString(),
                'valid_until' => $quote->valid_until?->toDateString(),
                'issued_at' => $quote->issued_at?->toIso8601String(),
            ],
            'lines' => $quote->lines->map(fn (QuoteLine $line) => [
                'id' => $line->id,
                'material_id' => $line->material_id,
                'mode' => $line->mode,
                'sheets_consumed' => $line->sheets_consumed,
                'cut_metres' => $line->cut_metres,
                'snapshot' => $line->snapshot,
                'cut_jobs' => $line->cutJobs->map(fn ($job) => [
                    'cut_metres' => $job->cut_metres,
                    'scheduled_date' => $job->scheduled_date->toDateString(),
                ]),
            ]),
            'availability' => $quote->lines
                ->map(fn (QuoteLine $line) => $line->material)
                ->unique('id')
                ->mapWithKeys(fn (Material $material) => [
                    $material->id => [
                        'sku' => $material->sku,
                        'stock_qty' => $material->stock_qty,
                        'available_sheets' => $material->availableSheets(),
                    ],
                ]),
        ];
    }
}
