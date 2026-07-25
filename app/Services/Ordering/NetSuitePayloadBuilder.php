<?php

namespace App\Services\Ordering;

use App\Models\Quote;
use App\Models\QuoteLine;

/**
 * Renders the ERP order payload. It is *rendered only* — the demo never sends
 * anything anywhere.
 *
 * Every number comes from the frozen quote-line snapshots, so the payload for
 * an issued quote is stable no matter what admin does to live rates.
 */
class NetSuitePayloadBuilder
{
    public const CUT_CHARGE_ITEM = 'CUT-CHARGE';

    public function build(Quote $quote): array
    {
        $quote->loadMissing('lines');

        $items = [];

        foreach ($quote->lines as $line) {
            $items[] = [
                'item_code' => $line->frozen('material.sku'),
                'description' => $this->sheetDescription($line),
                'qty' => $line->sheets_consumed,
                'rate' => round((float) $line->frozen('material.selling_price_aed'), 2),
                'tax_rate' => round((float) $line->frozen('pricing.vat_pct', $quote->vat_pct), 2),
            ];
        }

        // One service line for the whole order, per the client's sample shape.
        $items[] = [
            'item_code' => self::CUT_CHARGE_ITEM,
            'description' => 'Cut-to-size service',
            'qty' => 1,
            'rate' => round((float) $quote->cutting_total_aed, 2),
        ];

        return [
            'param' => [[
                'entity' => [
                    'orderId' => $this->orderId($quote),
                    'customer' => $quote->customer_name,
                    'order_date' => ($quote->issued_at ?? $quote->created_at)->toDateString(),
                    'currency' => $quote->currency,
                    'items' => $items,
                    'remarks' => $this->remarks($quote),
                ],
            ]],
        ];
    }

    public function json(Quote $quote): string
    {
        return json_encode($this->build($quote), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * The client's own sample payload, kept as a static file so the demo can
     * show it side by side. Swap the file for their real sample when it lands.
     */
    public function clientSample(): array
    {
        $path = resource_path('fixtures/netsuite-sample-payload.json');

        return is_file($path)
            ? (json_decode((string) file_get_contents($path), true) ?? [])
            : [];
    }

    private function orderId(Quote $quote): string
    {
        return 'SO-'.str_replace('Q-', '', $quote->reference);
    }

    private function remarks(Quote $quote): string
    {
        $remarks = "Quote {$quote->reference}";

        if (filled($quote->customer_reference)) {
            $remarks .= " · customer ref {$quote->customer_reference}";
        }

        if ($quote->promised_date !== null) {
            $remarks .= ' · promised '.$quote->promised_date->toDateString();
        }

        return $remarks;
    }

    private function sheetDescription(QuoteLine $line): string
    {
        return sprintf(
            '%s %s x %s mm — %d pieces cut to size',
            (string) $line->frozen('material.name'),
            (string) $line->frozen('material.sheet_w_mm'),
            (string) $line->frozen('material.sheet_h_mm'),
            (int) $line->frozen('engine.pieces_placed', 0),
        );
    }
}
