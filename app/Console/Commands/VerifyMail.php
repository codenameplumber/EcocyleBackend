<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WasteBatch;
use App\Notifications\BatchVerified;
use Illuminate\Console\Command;

class VerifyMail extends Command
{
    protected $signature = 'ecocycle:mail-check';
    protected $description = 'Queue a real notification and report what lands';

    public function handle(): int
    {
        $batch = WasteBatch::whereNotNull('value_naira')->with('household.user')->first();
        $user = $batch?->household?->user;

        if (! $user) {
            $this->error('No settled batch to notify about.');

            return self::FAILURE;
        }

        $before = $user->notifications()->count();
        $user->notify(new BatchVerified($batch));

        $this->line("queued for   {$user->email}");
        $this->line('db before    ' . $before);
        $this->line('queued jobs  ' . \DB::table('jobs')->count());

        return self::SUCCESS;
    }
}
