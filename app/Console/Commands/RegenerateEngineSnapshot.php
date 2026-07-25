<?php

namespace App\Console\Commands;

use App\Services\Cutting\CutConfig;
use App\Services\Cutting\Estimator;
use App\Services\Cutting\EstimatorMode;
use Illuminate\Console\Command;
use Tests\Fixtures\CutLists;

/**
 * Regenerates the golden snapshot guarded by DeterminismTest. Run it only when
 * an engine change is intended — the diff is the review artefact.
 */
class RegenerateEngineSnapshot extends Command
{
    protected $signature = 'cutting:snapshot';

    protected $description = 'Regenerate the golden engine output snapshot used by the determinism test';

    public function handle(): int
    {
        if (! class_exists(CutLists::class)) {
            $this->error('Test fixtures are not autoloaded. Run composer install with dev dependencies.');

            return self::FAILURE;
        }

        $result = (new Estimator(new CutConfig(kerfMm: 4.4, trimMm: 10.0, rotationAllowed: true)))
            ->estimate(CutLists::standardSheet(), CutLists::ps1Panels(), EstimatorMode::Optimized);

        $path = base_path('tests/Fixtures/engine-snapshot-ps1.json');
        file_put_contents($path, $result->toJson().PHP_EOL);

        $this->info("Snapshot written: {$path}");
        $this->line("Sheets: {$result->sheetsConsumed}, cut length: {$result->totalCutLengthMm} mm");

        return self::SUCCESS;
    }
}
