<?php

namespace App\Services\Intake;

use Anthropic\Client;
use Anthropic\Messages\JSONOutputFormat;
use Anthropic\Messages\OutputConfig;
use Anthropic\Messages\OutputConfig\Effort;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\ThinkingConfigDisabled;
use RuntimeException;

/**
 * The only outbound call in the demo: one Anthropic request that turns a
 * customer's pasted cut list into strict JSON.
 *
 * The brief asked for temperature 0. Current Sonnet rejects sampling
 * parameters outright (400), so determinism comes from the structured-output
 * schema plus thinking disabled at low effort instead — the model can only
 * emit the shape below, and it does not free-associate on the way there.
 */
class AnthropicCutListParser implements CutListParser
{
    public function __construct(
        private readonly Client $client,
        private readonly string $model,
        private readonly ?string $effort = null,
        private readonly int $maxTokens = 4096,
    ) {}

    public function parse(string $rawInput): ParsedCutList
    {
        $message = $this->client->messages->create(
            maxTokens: $this->maxTokens,
            messages: [[
                'role' => 'user',
                'content' => "Extract the cut list from this customer message:\n\n".$rawInput,
            ]],
            model: $this->model,
            // effort is only accepted by the newer Opus/Sonnet models — Haiku
            // 4.5 rejects it with a 400 — so it stays opt-in per model.
            outputConfig: OutputConfig::with(
                effort: $this->effort !== null ? Effort::from($this->effort) : null,
                format: JSONOutputFormat::with(schema: CutListSchema::schema()),
            ),
            system: CutListSchema::SYSTEM_PROMPT,
            thinking: new ThinkingConfigDisabled,
        );

        return ParsedCutList::fromArray($this->decode($message->content), 'api');
    }

    /** @param array<int, mixed> $content */
    private function decode(array $content): array
    {
        foreach ($content as $block) {
            if (! $block instanceof TextBlock) {
                continue;
            }

            // The SDK pre-parses structured output; fall back to the raw text.
            $parsed = is_array($block->parsed) ? $block->parsed : json_decode($block->text, true);

            if (is_array($parsed) && array_key_exists('pieces', $parsed)) {
                return $parsed;
            }
        }

        throw new RuntimeException('The parser returned no cut list JSON.');
    }
}
