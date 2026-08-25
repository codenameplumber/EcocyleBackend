<?php

namespace App\Notifications;

use App\Models\WasteBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BatchVerified extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WasteBatch $batch) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isHousehold = $notifiable->isHousehold();
        $share = $isHousehold ? $this->batch->household_share : $this->batch->agent_share;
        $pct   = $isHousehold ? '60%' : '40%';

        return (new MailMessage)
            ->subject("Batch {$this->batch->code} verified at {$this->batch->verified_kg} kg")
            ->greeting("Hello {$notifiable->name},")
            ->line("Batch **{$this->batch->code}** has been weighed at the facility gate.")
            ->line("Verified weight: **{$this->batch->verified_kg} kg**"
                . ($this->batch->grade ? " · Grade {$this->batch->grade}" : ''))
            ->line("Rate applied: NGN {$this->batch->rate_applied} per kg")
            ->line("Batch value: NGN " . number_format((float) $this->batch->value_naira, 2))
            ->line("Your share ({$pct}): **NGN " . number_format((float) $share, 2) . '**')
            ->line('This has been credited to your EcoCycle balance.')
            ->action('Open EcoCycle', url($notifiable->role->home()))
            ->line('Payment is always calculated from the facility scale reading, never from a declared weight.');
    }

    public function toDatabase(object $notifiable): array
    {
        $isHousehold = $notifiable->isHousehold();

        return [
            'kind'    => 'batch_verified',
            'icon'    => 'shield',
            'title'   => "Batch verified at {$this->batch->verified_kg} kg",
            'body'    => ($this->batch->grade ? "Grade {$this->batch->grade} · " : '')
                . 'NGN ' . number_format((float) ($isHousehold ? $this->batch->household_share : $this->batch->agent_share), 2) . ' credited',
            'batch'   => $this->batch->code,
            'batch_id'=> $this->batch->id,
        ];
    }
}
