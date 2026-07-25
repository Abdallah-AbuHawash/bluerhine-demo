<?php

namespace App\Http\Controllers;

use App\Models\IntakeSubmission;
use App\Models\Material;
use App\Services\Intake\IntakeService;
use App\Services\Intake\MaterialMatcher;
use App\Services\Intake\OfflineCutListParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntakeController extends Controller
{
    public function create(IntakeService $intake): Response
    {
        return Inertia::render('Intake/Create', [
            'examples' => collect(OfflineCutListParser::examples())
                ->map(fn (string $text, string $label) => ['label' => $label, 'text' => $text])
                ->values(),
            'apiAvailable' => $intake->apiAvailable(),
            'recent' => IntakeSubmission::latest()->take(5)->get()->map(fn (IntakeSubmission $s) => [
                'id' => $s->id,
                'status' => $s->status,
                'confidence' => $s->confidence,
                'offline_fallback' => $s->offline_fallback,
                'created_at' => $s->created_at->diffForHumans(),
            ]),
        ]);
    }

    public function store(Request $request, IntakeService $intake): RedirectResponse
    {
        $data = $request->validate([
            'raw_input' => ['nullable', 'string', 'max:20000'],
            'file' => ['nullable', 'file', 'max:2048', 'mimetypes:text/plain,text/csv,text/html,application/csv,application/octet-stream'],
        ]);

        $file = $request->file('file');
        $raw = $file !== null ? (string) file_get_contents($file->getRealPath()) : (string) ($data['raw_input'] ?? '');

        if (trim($raw) === '') {
            return back()->withErrors(['raw_input' => 'Paste a cut list or upload a file.']);
        }

        $submission = $intake->submit(
            rawInput: $raw,
            sourceType: $file !== null ? 'file' : 'paste',
            fileName: $file?->getClientOriginalName(),
        );

        return redirect()->route('intake.review', $submission);
    }

    public function review(IntakeSubmission $submission, MaterialMatcher $matcher): Response
    {
        $parse = $submission->parse_result ?? ['pieces' => [], 'warnings' => [], 'confidence' => 0];

        $rows = collect($parse['pieces'] ?? [])->map(function (array $piece) use ($matcher) {
            $suggested = $matcher->suggest($piece['material_hint'] ?? null, $piece['thickness_mm'] ?? null);

            return [
                ...$piece,
                'suggested_material_id' => $suggested?->id,
                'material_id' => $suggested?->id,
            ];
        })->values();

        return Inertia::render('Intake/Review', [
            'submission' => [
                'id' => $submission->id,
                'raw_input' => $submission->raw_input,
                'status' => $submission->status,
                'confidence' => $submission->confidence,
                'offline_fallback' => $submission->offline_fallback,
                'source' => $parse['source'] ?? 'offline_fixture',
                'warnings' => $parse['warnings'] ?? [],
            ],
            'rows' => $rows,
            'materials' => EstimateController::materials(),
        ]);
    }

    /**
     * Carry the reviewed rows into the estimate screen, grouped per material —
     * one quote line per SKU.
     */
    public function approve(Request $request, IntakeSubmission $submission): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_reference' => ['nullable', 'string', 'max:255'],
            'mode' => ['required', 'in:fixed,optimized'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.material_id' => ['required', 'exists:materials,id'],
            'rows.*.width_mm' => ['required', 'numeric', 'min:1'],
            'rows.*.height_mm' => ['required', 'numeric', 'min:1'],
            'rows.*.qty' => ['required', 'integer', 'min:1'],
            'rows.*.label' => ['nullable', 'string', 'max:120'],
        ]);

        $lines = collect($data['rows'])
            ->groupBy('material_id')
            ->map(fn ($rows, $materialId) => [
                'material_id' => (int) $materialId,
                'mode' => $data['mode'],
                'rows' => collect($rows)->values()->map(fn (array $row, int $i) => [
                    'label' => $row['label'] ?: Material::find($row['material_id'])?->sku.' row '.($i + 1),
                    'width' => (string) $row['width_mm'],
                    'height' => (string) $row['height_mm'],
                    'qty' => (string) $row['qty'],
                ])->all(),
            ])
            ->values()
            ->all();

        $submission->update(['status' => 'reviewed']);

        return redirect()
            ->route('estimates.create')
            ->with('estimate_prefill', [
                'customer_name' => $data['customer_name'],
                'customer_reference' => $data['customer_reference'] ?? null,
                'lines' => $lines,
            ])
            ->with('success', 'Rows carried over — check the cut list and calculate the estimate.');
    }
}
