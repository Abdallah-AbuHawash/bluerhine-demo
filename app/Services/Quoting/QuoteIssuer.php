<?php

namespace App\Services\Quoting;

use App\Models\CutJob;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\SoftAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Issuing freezes the quote: the line snapshots stop being provisional, stock
 * is soft-allocated for 48 hours, and later admin edits to rates or parameters
 * can no longer change what the customer was quoted.
 */
class QuoteIssuer
{
    public const ALLOCATION_HOURS = 48;

    public function __construct(private readonly LeadTimeScheduler $scheduler = new LeadTimeScheduler) {}

    public function issue(Quote $quote): Quote
    {
        if ($quote->isIssued()) {
            return $quote;
        }

        return DB::transaction(function () use ($quote) {
            $frozenAt = CarbonImmutable::now();

            foreach ($quote->lines as $line) {
                $snapshot = $line->snapshot;
                $snapshot['frozen_at'] = $frozenAt->toIso8601String();
                $line->update(['snapshot' => $snapshot]);

                SoftAllocation::create([
                    'material_id' => $line->material_id,
                    'quote_id' => $quote->id,
                    'qty_sheets' => $line->sheets_consumed,
                    'expires_at' => $frozenAt->addHours(self::ALLOCATION_HOURS),
                ]);
            }

            $quote->update([
                'status' => 'issued',
                'issued_at' => $frozenAt,
            ]);

            return $quote->fresh(['lines', 'softAllocations']);
        });
    }

    /**
     * Converting to an order books shop-floor capacity: one cut job per line,
     * on the first day whose remaining capacity can absorb it.
     */
    public function convertToOrder(Quote $quote): Quote
    {
        if ($quote->status === 'ordered') {
            return $quote;
        }

        return DB::transaction(function () use ($quote) {
            foreach ($quote->lines as $line) {
                $this->scheduleLine($line);
            }

            $quote->update(['status' => 'ordered']);

            return $quote->fresh(['lines.cutJobs']);
        });
    }

    private function scheduleLine(QuoteLine $line): CutJob
    {
        $date = $this->scheduler->promisedDate($line->cut_metres);

        return CutJob::create([
            'quote_line_id' => $line->id,
            'cut_metres' => $line->cut_metres,
            'scheduled_date' => $date->toDateString(),
        ]);
    }
}
