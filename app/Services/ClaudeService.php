<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Messages\Base64ImageSource;
use Anthropic\Messages\ImageBlockParam;
use Illuminate\Support\Facades\Log;

/**
 * Every AI feature in EcoCycle, backed by Claude.
 *
 * Two rules hold throughout:
 *
 *   1. Without an API key, every method returns a useful non-AI fallback.
 *      No screen ever breaks because the key is missing.
 *   2. The model never sees the database and is never asked to compute
 *      anything. We aggregate first and hand it real figures, so an answer
 *      here cannot invent a number.
 */
class ClaudeService
{
    private ?Client $client = null;

    public function enabled(): bool
    {
        return filled(config('ecocycle.claude.key'));
    }

    private function client(): Client
    {
        return $this->client ??= new Client(apiKey: config('ecocycle.claude.key'));
    }

    private function model(): string
    {
        return (string) config('ecocycle.claude.model');
    }

    /** Pull the plain text out of a response, skipping thinking blocks. */
    private function textOf(object $message): string
    {
        $out = '';
        foreach ($message->content as $block) {
            if (($block->type ?? null) === 'text') {
                $out .= $block->text;
            }
        }

        return trim($out);
    }

    // ------------------------------------------------------------ analytics

    /**
     * Answer an operator's question from figures we computed ourselves.
     *
     * @param  array<string, mixed>  $facts
     * @return array{answer: string, source: string, generated: bool}
     */
    public function answer(string $question, array $facts): array
    {
        $source = 'from ' . ($facts['batch_count'] ?? 0) . ' batches · '
            . ($facts['area_count'] ?? 0) . ' areas';

        if (! $this->enabled()) {
            return [
                'answer' => $this->fallbackAnswer($facts),
                'source' => $source . ' · computed locally',
                'generated' => false,
            ];
        }

        $system = <<<'TXT'
        You are the analytics assistant for EcoCycle, a waste-to-value platform in Lagos, Nigeria.

        You will be given a JSON object of REAL figures computed from the database.
        Answer the operator's question in at most three sentences.

        Rules:
        - Use only the numbers provided. Never invent, extrapolate or estimate a figure that is not present.
        - If the figures cannot answer the question, say plainly what is missing.
        - Money is Nigerian naira; write it as NGN 1,234.56.
        - Weights are kilograms; use tonnes above 1,000 kg.
        - Be direct. No preamble, no bullet points, no restating the question.
        TXT;

        try {
            $message = $this->client()->messages->create(
                model: $this->model(),
                maxTokens: 1024,
                system: $system,
                // Phrasing pre-computed figures is not hard work; keep it cheap and fast.
                thinking: ['type' => 'adaptive'],
                outputConfig: ['effort' => 'low'],
                messages: [[
                    'role' => 'user',
                    'content' => "Figures:\n" . json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                        . "\n\nQuestion: {$question}",
                ]],
            );

            $text = $this->textOf($message);

            if ($text === '') {
                throw new \RuntimeException('empty response');
            }

            return ['answer' => $text, 'source' => $source, 'generated' => true];
        } catch (\Throwable $e) {
            Log::warning('Claude analytics failed', ['error' => $e->getMessage()]);

            return [
                'answer' => $this->fallbackAnswer($facts),
                'source' => $source . ' · computed locally',
                'generated' => false,
            ];
        }
    }

    // ------------------------------------------------------- classification

    /**
     * Estimate waste composition from a pickup photograph.
     *
     * Advisory only. The inspector's decision always overrides this, and a
     * disagreement is recorded on the batch rather than discarded.
     *
     * @return array{mix: array<string,int>, suggested: string, confidence: int, generated: bool, note: ?string}
     */
    public function classifyPhoto(string $absolutePath): array
    {
        $fallback = [
            'mix' => ['organic' => 62, 'plastic' => 21, 'paper' => 11, 'metal' => 6],
            'suggested' => 'mixed',
            'confidence' => 0,
            'generated' => false,
            'note' => null,
        ];

        if (! $this->enabled() || ! is_readable($absolutePath)) {
            return $fallback;
        }

        $mime = mime_content_type($absolutePath) ?: 'image/jpeg';
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return $fallback;
        }

        $instruction = <<<'TXT'
        You are assisting a waste inspector at a materials recovery facility in Lagos.
        Estimate the composition of the waste in this photograph.

        Reply with ONLY a JSON object, no markdown fence, in exactly this shape:
        {"mix":{"organic":0,"plastic":0,"paper":0,"metal":0,"glass":0,"ewaste":0,"mixed":0},
         "suggested":"organic|plastic|paper|metal|glass|ewaste|mixed",
         "confidence":0,
         "note":"one short sentence on what you can and cannot see"}

        The integers in "mix" are percentages and must sum to 100.
        Omit streams you cannot see rather than guessing a small number for them.
        "confidence" is 0-100 and should be low when the photo is unclear, dark or partial.
        TXT;

        try {
            $message = $this->client()->messages->create(
                model: $this->model(),
                maxTokens: 1024,
                thinking: ['type' => 'adaptive'],
                outputConfig: ['effort' => 'medium'],
                messages: [[
                    'role' => 'user',
                    'content' => [
                        ImageBlockParam::with(
                            source: Base64ImageSource::with(
                                data: base64_encode((string) file_get_contents($absolutePath)),
                                mediaType: $mime,
                            ),
                        ),
                        ['type' => 'text', 'text' => $instruction],
                    ],
                ]],
            );

            $raw = $this->textOf($message);
            $raw = trim(preg_replace('/^```(?:json)?|```$/m', '', $raw) ?? '');
            $parsed = json_decode($raw, true);

            if (! is_array($parsed) || ! isset($parsed['mix']) || ! is_array($parsed['mix'])) {
                Log::warning('Claude classification returned unparseable output', ['raw' => mb_substr($raw, 0, 300)]);

                return $fallback;
            }

            $mix = array_filter(
                array_map('intval', $parsed['mix']),
                fn ($v) => $v > 0
            );

            return [
                'mix' => $mix ?: $fallback['mix'],
                'suggested' => (string) ($parsed['suggested'] ?? 'mixed'),
                'confidence' => max(0, min(100, (int) ($parsed['confidence'] ?? 0))),
                'generated' => true,
                'note' => isset($parsed['note']) ? (string) $parsed['note'] : null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Claude classification failed', ['error' => $e->getMessage()]);

            return $fallback;
        }
    }

    // ---------------------------------------------------------------- misc

    /** A plain sentence built from the same figures, when there is no key. */
    private function fallbackAnswer(array $facts): string
    {
        return sprintf(
            'Intake today is %s kg. This month %s kg has been verified across %d batches, '
            . 'settling NGN %s. Add an ANTHROPIC_API_KEY to ask this in plain language.',
            number_format((float) ($facts['intake_today_kg'] ?? 0), 1),
            number_format((float) ($facts['verified_kg_month'] ?? 0), 1),
            (int) ($facts['batch_count'] ?? 0),
            number_format((float) ($facts['settled_month_naira'] ?? 0), 2),
        );
    }
}
