<?php

namespace App\Services\Intake;

use Anthropic\Client;
use App\Models\IntakeSubmission;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Decides how a submission gets parsed and records the result.
 *
 * The API is used only when a key is present and DEMO_OFFLINE is off; any
 * failure falls back to the canned fixtures, so the demo never depends on
 * the network being there.
 */
class IntakeService
{
    public function __construct(
        private readonly OfflineCutListParser $offline = new OfflineCutListParser,
    ) {}

    public function submit(string $rawInput, string $sourceType = 'paste', ?string $fileName = null): IntakeSubmission
    {
        $result = $this->parse($rawInput);

        return IntakeSubmission::create([
            'raw_input' => $rawInput,
            'source_type' => $sourceType,
            'file_name' => $fileName,
            'parse_result' => $result->toArray(),
            'status' => 'parsed',
            'confidence' => $result->confidence,
            'offline_fallback' => $result->isOffline(),
        ]);
    }

    public function parse(string $rawInput): ParsedCutList
    {
        if (! $this->apiAvailable()) {
            return $this->offline->parse($rawInput);
        }

        try {
            return $this->apiParser()->parse($rawInput);
        } catch (Throwable $e) {
            Log::warning('Cut-list parse fell back to the offline fixtures.', [
                'exception' => $e->getMessage(),
            ]);

            return $this->offline->parse($rawInput);
        }
    }

    public function apiAvailable(): bool
    {
        return ! config('services.anthropic.demo_offline')
            && filled(config('services.anthropic.key'));
    }

    private function apiParser(): AnthropicCutListParser
    {
        return new AnthropicCutListParser(
            client: new Client(
                apiKey: (string) config('services.anthropic.key'),
                baseUrl: (string) config('services.anthropic.base_url'),
            ),
            model: (string) config('services.anthropic.model'),
            effort: config('services.anthropic.effort'),
        );
    }
}
