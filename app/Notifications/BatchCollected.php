<?php

namespace App\Notifications;

use App\Models\WasteBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BatchCollected extends Notification implements ShouldQueue
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
            ->subject("Batch {$this->batch->code} collected and tagged")
            ->greeting("Hello {$notifiable->name},")
            ->line('Your waste has been collected and tagged with a QR batch code.')
            ->line("Declared weight: **{$this->batch->collected_kg} kg**")
            ->line("Batch reference: {$this->batch->code}")
            ->line('This is the weight the agent recorded. Your payment will be calculated from '
                . 'the facility scale, which may differ.')
            ->action('Track the collection', url('/app/track'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind'     => 'batch_collected',
            'icon'     => 'package',
            'title'    => 'Batch tagged at pickup',
            'body'     => $this->batch->code . ' · declared ' . $this->batch->collected_kg . ' kg',
            'batch'    => $this->batch->code,
            'batch_id' => $this->batch->id,
        ];
    }
}
