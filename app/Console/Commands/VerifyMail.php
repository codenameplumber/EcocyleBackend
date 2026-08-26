<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WasteBatch;
use App\Notifications\BatchVerified;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Proves the mail path end to end, and says plainly where the message went.
 *
 * The failure this exists to catch is the quiet one: `.env` names Mailtrap, the
 * credentials are blank, the app falls back to the log driver, and everybody
 * assumes email works because nothing errored.
 */
class VerifyMail extends Command
{
    protected $signature = 'ecocycle:mail-check
                            {--to= : send to this address instead of a seeded household}
                            {--now : send synchronously instead of queueing}';

    protected $description = 'Send a real notification through the configured mailer and report where it landed';

    public function handle(): int
    {
        $mailer = config('mail.default');
        $host = config('mail.mailers.smtp.host');
        $username = config('mail.mailers.smtp.username');

        $this->newLine();
        $this->line('  <options=bold>Mail configuration</>');
        $this->line('  driver        ' . $mailer);

        if ($mailer === 'smtp') {
            $this->line('  host          ' . $host . ':' . config('mail.mailers.smtp.port'));
            $this->line('  username      ' . ($username ? substr($username, 0, 4) . '…' : 'not set'));
            $this->line('  inbox         ' . (str_contains((string) $host, 'mailtrap')
                ? 'Mailtrap sandbox — nothing reaches a real address'
                : 'real SMTP — mail will be delivered'));
        }

        if ($mailer === 'resend') {
            $key = (string) config('services.resend.key');
            $this->line('  key           ' . ($key ? substr($key, 0, 6) . '…' : 'not set'));
            $this->line('  inbox         real delivery — the from-domain must be verified with Resend');
        }

        $this->line('  from          ' . config('mail.from.address'));
        $this->line('  queue         ' . config('queue.default'));

        if ($mailer === 'log') {
            $this->newLine();
            $this->warn('  Falling back to the log driver — the configured transport has no credentials.');
            $this->line('  Mail is written to storage/logs/laravel.log, not delivered.');
            $this->line('  Mailtrap: put MAIL_USERNAME and MAIL_PASSWORD from your inbox into .env.');
            $this->line('  Resend:   set MAIL_MAILER=resend and RESEND_KEY in .env.');
        }

        $batch = WasteBatch::whereNotNull('value_naira')->with('household.user')->first();
        $recipient = $batch?->household?->user;

        if (! $recipient) {
            $this->error('  No settled batch to notify about. Seed the database first.');

            return self::FAILURE;
        }

        if ($to = $this->option('to')) {
            // An unsaved User so the notification renders exactly as it would for
            // a real one, but goes wherever the operator asked and touches no row.
            $recipient = new User(['name' => 'Mail check', 'email' => $to]);
            $recipient->exists = false;
        }

        $this->newLine();
        $this->line('  <options=bold>Sending</>');
        $this->line('  batch         ' . $batch->code . ' · ' . $batch->verified_kg . ' kg');
        $this->line('  to            ' . $recipient->email);

        $notification = new BatchVerified($batch);

        try {
            if ($this->option('now') || ! $recipient->exists) {
                // Bypass the queue so a transport failure surfaces here rather
                // than silently in a worker; an unsaved user cannot be queued at all.
                $recipient->notifyNow($notification, ['mail']);
                $this->line('  mode          sent synchronously');
            } else {
                $recipient->notify($notification);
                $this->line('  mode          queued');
                $this->line('  pending jobs  ' . DB::table('jobs')->count());
            }
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('  Send failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();

        $this->line(match ($mailer) {
            'log'    => '  Written to storage/logs/laravel.log.',
            'smtp'   => '  <fg=green>Handed to ' . $host . '. Open your inbox to read it.</>',
            'resend' => '  <fg=green>Handed to Resend. Check the dashboard for delivery status.</>',
            default  => '  Handed to the ' . $mailer . ' transport.',
        });

        if (! $this->option('now') && $recipient->exists && config('queue.default') !== 'sync') {
            $this->line('  Run <options=bold>php artisan queue:work</> in another terminal to deliver it.');
        }

        return self::SUCCESS;
    }
}
