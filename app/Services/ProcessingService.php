<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Models\ProcessingLine;
use App\Models\ProcessingRun;
use App\Models\WasteBatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Runs: what physically happened to verified material on a line.
 *
 * A batch is verified, then it is fed to a line, then the line produces
 * something. Keeping runs separate from batches matters for the same reason
 * deliveries are separate: one run consumes several batches, so recording the
 * output on the batch would either duplicate it or lose it.
 *
 * Yield factors are the plant's rated engineering, not fitted from data — but
 * the input weight they multiply is always the verified weight, never a
 * declared one.
 */
class ProcessingService
{
    /**
     * Rated output per kilogram of input, by feedstock stream.
     *
     * @var array<string, array{yield: float, output: string}>
     */
    private const YIELDS = [
        'organic' => ['yield' => 0.38, 'output' => 'Digestate fertiliser · biogas'],
        'plastic' => ['yield' => 0.86, 'output' => 'Baled PET and HDPE'],
        'paper'   => ['yield' => 0.81, 'output' => 'Baled fibre'],
        'metal'   => ['yield' => 0.93, 'output' => 'Sorted ferrous and non-ferrous'],
        'glass'   => ['yield' => 0.88, 'output' => 'Colour-sorted cullet'],
        'ewaste'  => ['yield' => 0.64, 'output' => 'Recovered boards and metals'],
        'mixed'   => ['yield' => 0.42, 'output' => 'Recovered recyclables'],
    ];

    /**
     * Put a verified batch onto a line, opening a run or joining the one already
     * open on that line for the same feedstock.
     *
     * Joining rather than always opening is what makes the runs table read like
     * a plant log instead of a batch log.
     */
    public function feed(WasteBatch $batch, ?Carbon $at = null): ?ProcessingRun
    {
        $category = $batch->effectiveCategory();
        $line = $this->lineFor($category?->key);

        if (! $line) {
            return null;
        }

        $at ??= now();

        return DB::transaction(function () use ($batch, $line, $category, $at) {
            $run = ProcessingRun::where('processing_line_id', $line->id)
                ->where('status', 'running')
                ->whereDate('started_at', $at->toDateString())
                ->lockForUpdate()
                ->first();

            if (! $run) {
                $run = ProcessingRun::create([
                    'code' => $this->nextCode(),
                    'processing_line_id' => $line->id,
                    'input_kg' => 0,
                    'output_desc' => self::YIELDS[$category?->key ?? 'mixed']['output'] ?? 'Recovered material',
                    'status' => 'running',
                    'started_at' => $at,
                ]);
            }

            $batch->update(['processing_run_id' => $run->id]);

            $run->increment('input_kg', (float) $batch->verified_kg);

            return $run->refresh();
        });
    }

    /**
     * Close a run and record what came off the line.
     *
     * Yield is applied once, at the end, against the run's whole input — which
     * is how a plant actually reports, and avoids rounding the same factor
     * across a dozen partial batches.
     */
    public function close(ProcessingRun $run, ?Carbon $at = null): ProcessingRun
    {
        if ($run->status === 'complete') {
            return $run;
        }

        $key = $run->line?->category?->key ?? 'mixed';
        $yield = self::YIELDS[$key]['yield'] ?? 0.5;

        $run->update([
            'output_kg' => round((float) $run->input_kg * $yield, 2),
            'yield_pct' => round($yield * 100, 2),
            'status' => 'complete',
            'finished_at' => $at ?? now(),
        ]);

        return $run;
    }

    /**
     * Plant output over a window, in the four categories the console reports.
     *
     * @return array<string, float>
     */
    public function output(int $days = 1): array
    {
        $runs = ProcessingRun::with('line.category')
            ->where('started_at', '>=', now()->subDays($days)->startOfDay())
            ->get();

        $organic = (float) $runs->filter(fn ($r) => $r->line?->category?->key === 'organic')->sum('input_kg');
        $dry = (float) $runs->filter(fn ($r) => in_array($r->line?->category?->key, ['plastic', 'paper', 'metal', 'glass', 'ewaste'], true))->sum('output_kg');
        $mixedIn = (float) $runs->filter(fn ($r) => $r->line?->category?->key === 'mixed')->sum('input_kg');
        $mixedOut = (float) $runs->filter(fn ($r) => $r->line?->category?->key === 'mixed')->sum('output_kg');

        $total = max(0.001, (float) $runs->sum('input_kg'));
        $residual = ($mixedIn - $mixedOut) + $organic * 0.04;

        return [
            // 120 m3 of biogas and 0.38 t of digestate per tonne of organics.
            'biogas_m3'     => $organic / 1000 * 120,
            'digestate_kg'  => $organic * 0.38,
            'recovered_kg'  => $dry + $mixedOut,
            'residual_kg'   => max(0, $residual),
            'diversion_pct' => (1 - max(0, $residual) / $total) * 100,
            'co2e_kg'       => ($total - max(0, $residual)) * 0.86,
            'total_kg'      => $total,
        ];
    }

    /**
     * Daily input against output over a window, for the throughput chart.
     *
     * @return array{labels: list<string>, input: list<float>, output: list<float>, yield: float}
     */
    public function throughput(int $days = 14): array
    {
        $rows = ProcessingRun::selectRaw('DATE(started_at) as d, SUM(input_kg) as inp, SUM(output_kg) as outp')
            ->where('started_at', '>=', now()->subDays($days)->startOfDay())
            ->groupByRaw('DATE(started_at)')
            ->orderByRaw('DATE(started_at)')
            ->get()
            ->keyBy('d');

        $labels = $input = $output = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $row = $rows[$d->toDateString()] ?? null;
            $labels[] = $d->format('j M');
            $input[] = round((float) ($row->inp ?? 0), 1);
            $output[] = round((float) ($row->outp ?? 0), 1);
        }

        $inTotal = array_sum($input);

        return [
            'labels' => $labels,
            'input' => $input,
            'output' => $output,
            'yield' => $inTotal > 0 ? round(array_sum($output) / $inTotal * 100, 1) : 0.0,
        ];
    }

    // ----------------------------------------------------------------- shared

    /** The line that handles a stream, falling back to the mixed sorting line. */
    private function lineFor(?string $categoryKey): ?ProcessingLine
    {
        $lines = ProcessingLine::with('category')->where('status', '!=', 'offline')->get();

        return $lines->first(fn ($l) => $l->category?->key === $categoryKey)
            ?? $lines->first(fn ($l) => $l->category?->key === 'mixed')
            ?? $lines->first();
    }

    private function nextCode(): string
    {
        $n = (int) ProcessingRun::max('id') + 1;

        return sprintf('RUN-%04d', 2000 + $n);
    }
}
