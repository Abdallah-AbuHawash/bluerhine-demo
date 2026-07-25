<?php

namespace App\Services\Quoting;

use App\Models\CutParameter;
use App\Models\CuttingRate;
use App\Models\Material;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Services\Cutting\Estimator;
use App\Services\Cutting\EstimatorMode;
use App\Services\Cutting\Piece;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns a cut list into a priced quote: engine -> rates -> VAT -> promised
 * date. Everything it used is written into the line snapshot, so an issued
 * quote can be re-rendered years later without touching the live tables.
 */
class QuoteBuilder
{
    public function __construct(private readonly LeadTimeScheduler $scheduler = new LeadTimeScheduler) {}

    /**
     * Price one material's cut list without persisting anything.
     *
     * @param  array<int, array{width: float, height: float, qty: int, label?: string}>  $rows
     */
    public function previewLine(Material $material, array $rows, EstimatorMode $mode): array
    {
        $parameters = CutParameter::current();
        $rate = CuttingRate::forMaterial($material);

        if ($rate === null) {
            throw new RuntimeException(
                "No cutting rate configured for material group \"{$material->material_group}\"."
            );
        }

        $config = $parameters->toCutConfig($material->rotation_allowed);
        $pieces = $this->toPieces($rows);

        $result = (new Estimator($config))->estimate($material->sheet(), $pieces, $mode);

        $pricing = PriceBreakdown::make(
            sheets: $result->sheetsConsumed,
            sheetPriceAed: $material->selling_price_aed,
            cutMetres: $result->totalCutMetres(),
            piecesPlaced: $result->piecesPlaced,
            rate: $rate->rate,
            rateUnit: $rate->rate_unit,
            vatPct: $parameters->vat_pct,
        );

        return [
            'mode' => $mode->value,
            'engine' => $result->toArray(),
            'material' => [
                'id' => $material->id,
                'sku' => $material->sku,
                'name' => $material->name,
                'brand' => $material->brand,
                'material_group' => $material->material_group,
                'thickness_mm' => $material->thickness_mm,
                'sheet_w_mm' => $material->sheet_w_mm,
                'sheet_h_mm' => $material->sheet_h_mm,
                'color_code' => $material->color_code,
                'color_name' => $material->color_name,
                'selling_price_aed' => $material->selling_price_aed,
                'rotation_allowed' => $material->rotation_allowed,
            ],
            'cutting_rate' => [
                'material_group' => $rate->material_group,
                'thickness_mm' => $rate->thickness_mm,
                'rate' => $rate->rate,
                'rate_unit' => $rate->rate_unit,
                'label' => $rate->label(),
            ],
            'parameters' => [
                'kerf_mm' => $parameters->kerf_mm,
                'trim_mm' => $parameters->trim_mm,
                'vat_pct' => $parameters->vat_pct,
                'quote_validity_days' => $parameters->quote_validity_days,
                'include_trim_in_cut_length' => $parameters->include_trim_in_cut_length,
            ],
            'pricing' => $pricing->toArray(),
            'cut_metres' => $result->totalCutMetres(),
            'sheets_consumed' => $result->sheetsConsumed,
            'rows' => array_map(fn (Piece $piece) => $piece->toArray(), $pieces),
        ];
    }

    /**
     * @param  array<int, array{material: Material, rows: array, mode: EstimatorMode}>  $lineInputs
     */
    public function createDraft(string $customerName, ?string $customerReference, array $lineInputs): Quote
    {
        $snapshots = [];

        foreach ($lineInputs as $input) {
            $snapshots[] = [
                'material' => $input['material'],
                'snapshot' => $this->previewLine($input['material'], $input['rows'], $input['mode']),
            ];
        }

        $parameters = CutParameter::current();
        $totalCutMetres = array_sum(array_map(fn ($l) => $l['snapshot']['cut_metres'], $snapshots));
        $promisedDate = $this->scheduler->promisedDate($totalCutMetres);
        $validUntil = CarbonImmutable::today()->addDays($parameters->quote_validity_days);

        return DB::transaction(function () use ($customerName, $customerReference, $snapshots, $parameters, $promisedDate, $validUntil) {
            $quote = Quote::create([
                'reference' => Quote::nextReference(),
                'customer_name' => $customerName,
                'customer_reference' => $customerReference,
                'status' => 'draft',
                'currency' => 'AED',
                'vat_pct' => $parameters->vat_pct,
                'promised_date' => $promisedDate->toDateString(),
                'valid_until' => $validUntil->toDateString(),
            ]);

            foreach ($snapshots as $line) {
                $snapshot = $line['snapshot'] + [
                    'promised_date' => $promisedDate->toDateString(),
                    'valid_until' => $validUntil->toDateString(),
                    'frozen_at' => null,
                ];

                QuoteLine::create([
                    'quote_id' => $quote->id,
                    'material_id' => $line['material']->id,
                    'mode' => $snapshot['mode'],
                    'sheets_consumed' => $snapshot['sheets_consumed'],
                    'cut_metres' => $snapshot['cut_metres'],
                    'material_total_aed' => $snapshot['pricing']['material_total_aed'],
                    'cutting_total_aed' => $snapshot['pricing']['cutting_total_aed'],
                    'line_total_aed' => $snapshot['pricing']['total_aed'],
                    'snapshot' => $snapshot,
                ]);
            }

            return $this->recalculateTotals($quote->fresh('lines'));
        });
    }

    /** Quote totals are the sum of the frozen line snapshots, nothing live. */
    public function recalculateTotals(Quote $quote): Quote
    {
        $material = 0.0;
        $cutting = 0.0;
        $vat = 0.0;

        foreach ($quote->lines as $line) {
            $material += (float) $line->frozen('pricing.material_total_aed', 0);
            $cutting += (float) $line->frozen('pricing.cutting_total_aed', 0);
            $vat += (float) $line->frozen('pricing.vat_aed', 0);
        }

        $subtotal = round($material + $cutting, 2);

        $quote->update([
            'material_total_aed' => round($material, 2),
            'cutting_total_aed' => round($cutting, 2),
            'subtotal_aed' => $subtotal,
            'vat_aed' => round($vat, 2),
            'total_aed' => round($subtotal + $vat, 2),
        ]);

        return $quote->fresh('lines');
    }

    /**
     * @param  array<int, array>  $rows
     * @return array<int, Piece>
     */
    private function toPieces(array $rows): array
    {
        return array_values(array_map(
            fn (array $row, int $index) => Piece::fromArray($row, $index),
            $rows,
            array_keys($rows),
        ));
    }
}
