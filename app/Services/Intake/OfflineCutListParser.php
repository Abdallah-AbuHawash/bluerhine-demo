<?php

namespace App\Services\Intake;

/**
 * The offline path. Two rehearsal fixtures are matched exactly; anything else
 * falls back to a regex sweep so the demo still does something sensible with
 * an improvised paste and no network.
 */
class OfflineCutListParser implements CutListParser
{
    public const FIXTURE_A = <<<'TXT'
        PS1-PNL-CT-1212  Ceiling tile 1200x1200x6 mm clear PC   qty 4
        PS1-PNL-IF-1220  Infill panel 1200x200x6 mm clear PC    qty 4
        PS1-PNL-EC-200   End-of-row infill 1200x200x6 clear PC  qty 2
        HDPE cable-duct sheet 1000x600x5 mm                      qty 6
        TXT;

    public const FIXTURE_B = <<<'TXT'
        hi need cutting: 6 pcs 60x40cm 3mm opal white acrylic, also 2 pcs 120 x 80 same material,
        and if u have mirror silver 3mm need 4 pieces 50by50 thanks
        TXT;

    public function parse(string $rawInput): ParsedCutList
    {
        $fixture = $this->matchFixture($rawInput);

        return ParsedCutList::fromArray(
            $fixture ?? $this->heuristic($rawInput),
            'offline_fixture',
        );
    }

    /** @return array<string, string> label => raw text, for the one-click demo buttons */
    public static function examples(): array
    {
        return [
            'Fixture A — PS-1 drawing pack BOM' => self::FIXTURE_A,
            'Fixture B — WhatsApp paste' => self::FIXTURE_B,
        ];
    }

    private function matchFixture(string $raw): ?array
    {
        $needle = $this->normalise($raw);

        if (str_contains($needle, 'ps1-pnl-ct-1212') || str_contains($needle, 'ceiling tile')) {
            return $this->fixtureA();
        }

        if (str_contains($needle, 'mirror silver') || str_contains($needle, '50by50')) {
            return $this->fixtureB();
        }

        return null;
    }

    private function normalise(string $raw): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($raw)) ?? '');
    }

    private function fixtureA(): array
    {
        return [
            'pieces' => [
                [
                    'material_hint' => 'clear PC', 'thickness_mm' => 6.0,
                    'width_mm' => 1200.0, 'height_mm' => 1200.0, 'qty' => 4,
                    'notes' => 'PS1-PNL-CT-1212 ceiling tile, FR grade, edge-bonded gasket',
                ],
                [
                    'material_hint' => 'clear PC', 'thickness_mm' => 6.0,
                    'width_mm' => 1200.0, 'height_mm' => 200.0, 'qty' => 4,
                    'notes' => 'PS1-PNL-IF-1220 infill panel',
                ],
                [
                    'material_hint' => 'clear PC', 'thickness_mm' => 6.0,
                    'width_mm' => 1200.0, 'height_mm' => 200.0, 'qty' => 2,
                    'notes' => 'PS1-PNL-EC-200 end-of-row infill',
                ],
                [
                    'material_hint' => 'HDPE cable-duct sheet', 'thickness_mm' => 5.0,
                    'width_mm' => 1000.0, 'height_mm' => 600.0, 'qty' => 6,
                    'notes' => 'Cable openings are drilled after cutting, not nested',
                ],
            ],
            'confidence' => 0.96,
            'warnings' => [
                'Cable openings on the HDPE sheets are a separate CNC operation and are not part of this cut list.',
            ],
        ];
    }

    private function fixtureB(): array
    {
        return [
            'pieces' => [
                [
                    'material_hint' => '3mm opal white acrylic', 'thickness_mm' => 3.0,
                    'width_mm' => 600.0, 'height_mm' => 400.0, 'qty' => 6,
                    'notes' => 'Given as 60x40 cm',
                ],
                [
                    'material_hint' => '3mm opal white acrylic', 'thickness_mm' => 3.0,
                    'width_mm' => 1200.0, 'height_mm' => 800.0, 'qty' => 2,
                    'notes' => 'Given as 120 x 80, "same material" as the previous line',
                ],
                [
                    'material_hint' => 'mirror silver 3mm', 'thickness_mm' => 3.0,
                    'width_mm' => 500.0, 'height_mm' => 500.0, 'qty' => 4,
                    'notes' => 'Given as 50by50 cm',
                ],
            ],
            'confidence' => 0.82,
            'warnings' => [
                'Line 2 gives no unit; read as centimetres to match line 1.',
                '"Same material" on line 2 was resolved to the 3 mm opal white acrylic above.',
                'Mirror stock is directional — those pieces must not be rotated when nesting.',
            ],
        ];
    }

    /**
     * Last resort with no network and no fixture match: pull quantities and
     * dimension pairs out of each line and convert everything to millimetres.
     */
    private function heuristic(string $raw): array
    {
        $pieces = [];
        $warnings = ['Offline heuristic parse — check every row before quoting.'];

        foreach (preg_split('/[\r\n,]+/', $raw) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (! preg_match('/(\d+(?:\.\d+)?)\s*(?:x|by|\*|×)\s*(\d+(?:\.\d+)?)/i', $line, $dims)) {
                continue;
            }

            $unitFactor = preg_match('/\bcm\b/i', $line) ? 10.0 : 1.0;
            $qty = preg_match('/(?:qty|quantity)\s*[:x]?\s*(\d+)/i', $line, $q)
                || preg_match('/(\d+)\s*(?:pcs?|pieces?|nos?)\b/i', $line, $q)
                ? (int) $q[1]
                : 1;

            $thickness = preg_match('/(\d+(?:\.\d+)?)\s*mm\b/i', $line, $t) ? (float) $t[1] : null;

            $pieces[] = [
                'material_hint' => trim(preg_replace('/[\d.,x×*]+\s*(mm|cm)?/i', ' ', $line) ?? '') ?: null,
                'thickness_mm' => $thickness,
                'width_mm' => (float) $dims[1] * $unitFactor,
                'height_mm' => (float) $dims[2] * $unitFactor,
                'qty' => $qty,
                'notes' => $line,
            ];
        }

        if ($pieces === []) {
            $warnings[] = 'No dimensions found — enter the rows by hand on the estimate screen.';
        }

        return ['pieces' => $pieces, 'confidence' => 0.4, 'warnings' => $warnings];
    }
}
