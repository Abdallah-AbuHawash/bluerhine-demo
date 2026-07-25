<?php

namespace App\Services\Intake;

use App\Models\Material;
use Illuminate\Support\Collection;

/**
 * Maps a customer's wording ("clear PC", "3mm opal white acrylic") onto a real
 * SKU. Scoring is deterministic and deliberately conservative: it pre-selects a
 * best guess, and the review screen makes the operator confirm it.
 */
class MaterialMatcher
{
    private const GROUP_KEYWORDS = [
        'acrylic_mirror' => ['mirror', 'mirrored'],
        'polycarbonate' => ['polycarbonate', 'poly carbonate', ' pc', 'pc ', 'lexan', 'makrolon'],
        'hdpe' => ['hdpe', 'polyethylene', 'poly ethylene'],
        'acrylic_cast' => ['acrylic', 'perspex', 'plexi', 'pmma'],
    ];

    private const COLOUR_KEYWORDS = [
        'opal white' => ['opal', 'milky', 'white'],
        'clear' => ['clear', 'transparent', 'crystal'],
        'black' => ['black'],
        'natural' => ['natural'],
        'bronze' => ['bronze'],
        'mirror silver' => ['silver', 'mirror'],
        'dark yellow' => ['yellow'],
    ];

    /** @var Collection<int, Material>|null */
    private ?Collection $materials = null;

    public function suggest(?string $hint, ?float $thicknessMm): ?Material
    {
        $candidates = $this->materials();

        if ($candidates->isEmpty()) {
            return null;
        }

        $hint = strtolower(trim((string) $hint));
        $group = $this->groupFor($hint);

        $scored = $candidates->map(fn (Material $material) => [
            'material' => $material,
            'score' => $this->score($material, $hint, $group, $thicknessMm),
        ])->sortBy([
            fn (array $a, array $b) => $b['score'] <=> $a['score'],
            fn (array $a, array $b) => $a['material']->sku <=> $b['material']->sku,
        ]);

        $best = $scored->first();

        // Below this, the guess is noise — leave the row unresolved instead.
        return $best !== null && $best['score'] >= 2 ? $best['material'] : null;
    }

    private function score(Material $material, string $hint, ?string $group, ?float $thicknessMm): int
    {
        $score = 0;

        if ($group !== null && $material->material_group === $group) {
            $score += 4;
        } elseif ($group !== null) {
            return 0; // wrong family — never suggest it
        }

        if ($thicknessMm !== null) {
            $delta = abs($material->thickness_mm - $thicknessMm);
            $score += match (true) {
                $delta < 0.01 => 4,
                $delta <= 0.5 => 2,
                $delta <= 1.5 => 1,
                default => 0,
            };
        }

        foreach (self::COLOUR_KEYWORDS as $colour => $keywords) {
            if (strtolower((string) $material->color_name) !== $colour) {
                continue;
            }

            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($hint, $keyword)) {
                    $score += 2;
                    break;
                }
            }
        }

        return $score;
    }

    private function groupFor(string $hint): ?string
    {
        foreach (self::GROUP_KEYWORDS as $group => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($hint, $keyword)) {
                    return $group;
                }
            }
        }

        return null;
    }

    /** @return Collection<int, Material> */
    private function materials(): Collection
    {
        return $this->materials ??= Material::cutEligible()->orderBy('sku')->get();
    }
}
