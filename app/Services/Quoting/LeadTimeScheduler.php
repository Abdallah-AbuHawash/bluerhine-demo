<?php

namespace App\Services\Quoting;

use App\Models\CutJob;
use App\Models\LeadTimeRule;
use Carbon\CarbonImmutable;

/**
 * Promised date = the first working day whose remaining cutting capacity can
 * absorb the job. Capacity per weekday comes from lead_time_rules
 * (400 cut-metres Mon-Fri, 0 at the weekend by default).
 */
class LeadTimeScheduler
{
    private const HORIZON_DAYS = 90;

    public function promisedDate(float $cutMetres, ?CarbonImmutable $from = null): CarbonImmutable
    {
        $capacities = LeadTimeRule::pluck('capacity_cut_metres', 'weekday')->all();
        $day = ($from ?? CarbonImmutable::today())->addDay();
        $fallback = null;

        for ($i = 0; $i < self::HORIZON_DAYS; $i++, $day = $day->addDay()) {
            $capacity = (float) ($capacities[$day->dayOfWeekIso] ?? 0);

            if ($capacity <= 0) {
                continue;
            }

            $remaining = $capacity - $this->bookedOn($day);

            if ($remaining >= $cutMetres) {
                return $day;
            }

            // A job bigger than one day's capacity still has to land somewhere:
            // remember the first completely free working day.
            if ($fallback === null && $remaining >= $capacity) {
                $fallback = $day;
            }
        }

        return $fallback ?? $day;
    }

    public function bookedOn(CarbonImmutable $date): float
    {
        return (float) CutJob::whereDate('scheduled_date', $date->toDateString())->sum('cut_metres');
    }

    public function capacityOn(CarbonImmutable $date): float
    {
        return (float) (LeadTimeRule::where('weekday', $date->dayOfWeekIso)->value('capacity_cut_metres') ?? 0);
    }

    /** @return array<int, array{date: string, capacity: float, booked: float, free: float}> */
    public function upcomingLoad(int $days = 10, ?CarbonImmutable $from = null): array
    {
        $day = $from ?? CarbonImmutable::today();
        $load = [];

        for ($i = 0; $i < $days; $i++, $day = $day->addDay()) {
            $capacity = $this->capacityOn($day);
            $booked = $this->bookedOn($day);

            $load[] = [
                'date' => $day->toDateString(),
                'capacity' => $capacity,
                'booked' => $booked,
                'free' => max(0, $capacity - $booked),
            ];
        }

        return $load;
    }
}
