<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Services\Cutting\EstimatorMode;
use App\Services\Cutting\Exceptions\PieceExceedsSheetException;
use App\Services\Quoting\QuoteBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EstimateController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Estimates/New', [
            'materials' => $this->materials(),
            // Set by the intake review screen when rows are carried over.
            'prefill' => $request->session()->get('estimate_prefill'),
        ]);
    }

    public function store(Request $request, QuoteBuilder $builder): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_reference' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.material_id' => ['required', 'exists:materials,id'],
            'lines.*.mode' => ['required', 'in:fixed,optimized'],
            'lines.*.rows' => ['required', 'array', 'min:1'],
            'lines.*.rows.*.width' => ['required', 'numeric', 'min:1'],
            'lines.*.rows.*.height' => ['required', 'numeric', 'min:1'],
            'lines.*.rows.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.rows.*.label' => ['nullable', 'string', 'max:120'],
        ]);

        $inputs = [];

        foreach ($data['lines'] as $index => $line) {
            $inputs[] = [
                'material' => Material::findOrFail($line['material_id']),
                'rows' => array_map(
                    fn (array $row, int $i) => [
                        'width' => (float) $row['width'],
                        'height' => (float) $row['height'],
                        'qty' => (int) $row['qty'],
                        'label' => $row['label'] ?? 'Row '.($i + 1),
                    ],
                    $line['rows'],
                    array_keys($line['rows']),
                ),
                'mode' => EstimatorMode::from($line['mode']),
                'index' => $index,
            ];
        }

        try {
            $quote = $builder->createDraft(
                $data['customer_name'],
                $data['customer_reference'] ?? null,
                $inputs,
            );
        } catch (PieceExceedsSheetException $e) {
            // Structured validation error, not a silent fix.
            throw ValidationException::withMessages([
                'lines' => $e->getMessage(),
            ]);
        }

        $request->session()->forget('estimate_prefill');

        return redirect()->route('quotes.show', $quote)->with('success', "Estimate {$quote->reference} created.");
    }

    /** @return array<int, array<string, mixed>> */
    public static function materials(): array
    {
        return Material::cutEligible()
            ->orderBy('material_group')
            ->orderBy('thickness_mm')
            ->get()
            ->map(fn (Material $material) => [
                'id' => $material->id,
                'sku' => $material->sku,
                'name' => $material->name,
                'brand' => $material->brand,
                'material_group' => $material->material_group,
                'thickness_mm' => $material->thickness_mm,
                'sheet_w_mm' => $material->sheet_w_mm,
                'sheet_h_mm' => $material->sheet_h_mm,
                'color_name' => $material->color_name,
                'selling_price_aed' => $material->selling_price_aed,
                'rotation_allowed' => $material->rotation_allowed,
                'stock_qty' => $material->stock_qty,
                'available_sheets' => $material->availableSheets(),
            ])
            ->all();
    }
}
