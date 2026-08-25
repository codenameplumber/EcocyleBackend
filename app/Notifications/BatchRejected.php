<?php

namespace App\Notifications;

use App\Models\WasteBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BatchRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WasteBatch $batch) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Batch {$this->batch->code} was rejected at inspection")
            ->greeting("Hello {$notifiable->name},")
            ->line("Batch **{$this->batch->code}** was rejected at the inspection bay.")
            ->line("Reason: **{$this->batch->rejection_reason}**")
            ->line('A rejected batch pays neither the household nor the agent.')
            ->line('If you believe this is wrong, you can appeal it — every rejection carries its evidence.')
            ->action('View the batch', url($notifiable->role->home()));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind'     => 'batch_rejected',
            'icon'     => 'ban',
            'title'    => 'Batch rejected at inspection',
            'body'     => $this->batch->rejection_reason,
            'batch'    => $this->batch->code,
            'batch_id' => $this->batch->id,
        ];
    }
}
