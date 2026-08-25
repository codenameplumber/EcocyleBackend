<?php

namespace App\Notifications;

use App\Models\WasteBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JobAccepted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WasteBatch $batch) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $agent = $this->batch->agent;

        return (new MailMessage)
            ->subject("{$agent?->name()} accepted your pickup")
            ->greeting("Hello {$notifiable->name},")
            ->line("**{$agent?->name()}** has accepted your pickup request.")
            ->line("Vehicle: {$agent?->vehicle_type}"
                . ($agent?->user?->phone ? " · Phone: {$agent->user->phone}" : ''))
            ->line("Batch reference: {$this->batch->code}")
            ->action('Track the collection', url('/app/track'))
            ->line('You will be emailed again once the facility has weighed it.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind'     => 'job_accepted',
            'icon'     => 'truck',
            'title'    => ($this->batch->agent?->name() ?? 'An agent') . ' accepted your request',
            'body'     => 'Batch ' . $this->batch->code,
            'batch'    => $this->batch->code,
            'batch_id' => $this->batch->id,
        ];
    }
}
