<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI;
use OpenAI\Client;

/**
 * Every AI feature in EcoCycle, backed by OpenAI.
 *
 * Two rules hold throughout:
 *
 *   1. Without an API key, every method returns a useful non-AI fallback.
 *      No screen ever breaks because the key is missing.
 *   2. The model never sees the database and is never asked to compute
 *      anything. We aggregate first and hand it real figures, so an answer
 *      here cannot invent a number.
 */
class OpenAiService
{
    private ?Client $client = null;

    public function enabled(): bool
    {
        return filled(config('ecocycle.openai.key'));
    }

    private function client(): Client
    {
        return $this->client ??= OpenAI::factory()
            ->withApiKey((string) config('ecocycle.openai.key'))
            ->withOrganization(config('ecocycle.openai.organization') ?: null)
            // The classifier sends a base64 photograph, which is slow enough on
            // a Lagos connection that the default ten seconds is not generous.
            ->withHttpClient(new \GuzzleHttp\Client(['timeout' => 60]))
            ->make();
    }

    private function model(): string
    {
        return (string) config('ecocycle.openai.model');
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
            $response = $this->client()->chat()->create([
                'model' => $this->model(),
                'max_completion_tokens' => 400,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    [
                        'role' => 'user',
                        'content' => "Figures:\n"
                            . json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                            . "\n\nQuestion: {$question}",
                    ],
                ],
            ]);

            $text = trim((string) ($response->choices[0]->message->content ?? ''));

            if ($text === '') {
                throw new \RuntimeException('empty response');
            }

            return ['answer' => $text, 'source' => $source, 'generated' => true];
        } catch (\Throwable $e) {
            Log::warning('OpenAI analytics failed', ['error' => $e->getMessage()]);

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
            $dataUri = 'data:' . $mime . ';base64,'
                . base64_encode((string) file_get_contents($absolutePath));

            $response = $this->client()->chat()->create([
                'model' => config('ecocycle.openai.vision_model'),
                'max_completion_tokens' => 500,
                // Ask for JSON at the API level so a stray sentence cannot break
                // the parse; the prompt asks for it too, belt and braces.
                'response_format' => ['type' => 'json_object'],
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $instruction],
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUri, 'detail' => 'low']],
                    ],
                ]],
            ]);

            $raw = trim((string) ($response->choices[0]->message->content ?? ''));
            $raw = trim(preg_replace('/^```(?:json)?|```$/m', '', $raw) ?? '');
            $parsed = json_decode($raw, true);

            if (! is_array($parsed) || ! isset($parsed['mix']) || ! is_array($parsed['mix'])) {
                Log::warning('OpenAI classification returned unparseable output', [
                    'raw' => mb_substr($raw, 0, 300),
                ]);

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
            Log::warning('OpenAI classification failed', ['error' => $e->getMessage()]);

            return $fallback;
        }
    }

    // ---------------------------------------------------------------- misc

    /** A plain sentence built from the same figures, when there is no key. */
    private function fallbackAnswer(array $facts): string
    {
        return sprintf(
            'Intake today is %s kg. This month %s kg has been verified across %d batches, '
            . 'settling NGN %s. Add an OPENAI_API_KEY to ask this in plain language.',
            number_format((float) ($facts['intake_today_kg'] ?? 0), 1),
            number_format((float) ($facts['verified_kg_month'] ?? 0), 1),
            (int) ($facts['batch_count'] ?? 0),
            number_format((float) ($facts['settled_month_naira'] ?? 0), 2),
        );
    }
}
