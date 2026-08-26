<?php

namespace Database\Seeders;

use App\Enums\BatchStatus;
use App\Models\ProcessingRun;
use App\Models\WasteBatch;
use App\Services\ProcessingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Back-fills the plant log for batches the network seeder already pushed to
 * PROCESSED.
 *
 * Every run here is opened and closed through ProcessingService, so a seeded run
 * is byte-for-byte what a run the console opened would look like — same code
 * sequence, same line assignment, same yield arithmetic.
 */
class ProcessingRunSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(ProcessingService::class);

        DB::table('waste_batches')->update(['processing_run_id' => null]);
        ProcessingRun::query()->delete();

        $batches = WasteBatch::with(['category', 'confirmedCategory'])
            ->whereIn('status', [BatchStatus::Processing->value, BatchStatus::Processed->value])
            ->whereNotNull('verified_kg')
            ->orderBy('processing_at')
            ->get();

        foreach ($batches as $b) {
            // Feed on the day it actually entered the line, so the throughput
            // chart shows the real shape rather than one enormous day.
            $service->feed($b, $b->processing_at ?? $b->verified_at ?? $b->created_at);
        }

        // Close every run whose batches all finished; leave today's open.
        $closed = 0;
        foreach (ProcessingRun::with('line.category')->where('status', 'running')->get() as $run) {
            $stillRunning = $run->batches()->where('status', BatchStatus::Processing->value)->exists();

            if (! $stillRunning) {
                $finished = $run->batches()->max('processed_at');
                $service->close($run, $finished ? \Illuminate\Support\Carbon::parse($finished) : null);
                $closed++;
            }
        }

        $this->command->info(sprintf(
            '  %d processing runs (%d complete) over %s kg of feedstock',
            ProcessingRun::count(),
            $closed,
            number_format((float) ProcessingRun::sum('input_kg'), 1),
        ));
    }
}
