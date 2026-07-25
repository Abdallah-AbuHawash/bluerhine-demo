<?php

namespace App\Http\Controllers;

use App\Models\CutParameter;
use App\Models\CuttingRate;
use App\Models\LeadTimeRule;
use App\Models\Material;
use App\Models\Quote;
use App\Services\Quoting\LeadTimeScheduler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shop-floor settings. Every change here affects NEW quotes only — issued
 * quotes render from their frozen snapshot, which is the point of the demo.
 */
class AdminController extends Controller
{
    public function index(LeadTimeScheduler $scheduler): Response
    {
        return Inertia::render('Admin/Index', [
            'parameters' => CutParameter::current()->only([
                'kerf_mm', 'trim_mm', 'vat_pct', 'quote_validity_days', 'include_trim_in_cut_length',
            ]),
            'rates' => CuttingRate::orderBy('material_group')->orderBy('thickness_mm')->get()
                ->map(fn (CuttingRate $rate) => [
                    'id' => $rate->id,
                    'material_group' => $rate->material_group,
                    'thickness_mm' => $rate->thickness_mm,
                    'rate' => $rate->rate,
                    'rate_unit' => $rate->rate_unit,
                ]),
            'leadTimes' => LeadTimeRule::orderBy('weekday')->get()
                ->map(fn (LeadTimeRule $rule) => [
                    'id' => $rule->id,
                    'weekday' => $rule->weekday,
                    'weekday_name' => $rule->weekdayName(),
                    'capacity_cut_metres' => $rule->capacity_cut_metres,
                ]),
            'materialGroups' => Material::query()->distinct()->orderBy('material_group')->pluck('material_group'),
            'load' => $scheduler->upcomingLoad(10),
            'issuedQuotes' => Quote::whereIn('status', ['issued', 'ordered'])
                ->latest()->take(5)->get()
                ->map(fn (Quote $quote) => [
                    'id' => $quote->id,
                    'reference' => $quote->reference,
                    'total_aed' => $quote->total_aed,
                    'issued_at' => $quote->issued_at?->toDateString(),
                ]),
        ]);
    }

    public function updateParameters(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kerf_mm' => ['required', 'numeric', 'min:0', 'max:50'],
            'trim_mm' => ['required', 'numeric', 'min:0', 'max:200'],
            'vat_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'quote_validity_days' => ['required', 'integer', 'min:1', 'max:365'],
            'include_trim_in_cut_length' => ['required', 'boolean'],
        ]);

        CutParameter::current()->update($data);

        return back()->with('success', 'Cut parameters updated — new quotes only.');
    }

    public function storeRate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'material_group' => ['required', 'string', 'max:60'],
            'thickness_mm' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'rate' => ['required', 'numeric', 'min:0'],
            'rate_unit' => ['required', 'in:per_cut_metre,per_piece,per_sheet'],
        ]);

        CuttingRate::updateOrCreate(
            ['material_group' => $data['material_group'], 'thickness_mm' => $data['thickness_mm']],
            ['rate' => $data['rate'], 'rate_unit' => $data['rate_unit']],
        );

        return back()->with('success', 'Cutting rate saved.');
    }

    public function updateRate(Request $request, CuttingRate $rate): RedirectResponse
    {
        $data = $request->validate([
            'rate' => ['required', 'numeric', 'min:0'],
            'rate_unit' => ['required', 'in:per_cut_metre,per_piece,per_sheet'],
        ]);

        $rate->update($data);

        return back()->with('success', "Rate for {$rate->material_group} {$rate->thickness_mm} mm updated.");
    }

    public function destroyRate(CuttingRate $rate): RedirectResponse
    {
        $rate->delete();

        return back()->with('success', 'Cutting rate removed.');
    }

    public function updateLeadTime(Request $request, LeadTimeRule $rule): RedirectResponse
    {
        $data = $request->validate([
            'capacity_cut_metres' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);

        $rule->update($data);

        return back()->with('success', "{$rule->weekdayName()} capacity updated.");
    }
}
