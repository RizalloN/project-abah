<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\SlackMessage;
use Throwable;

class ShadowBackfillFailedNotification extends Notification
{
    public function __construct(
        private array $periods,
        private Throwable $exception
    ) {
    }

    public function via($notifiable): array
    {
        return ['slack'];
    }

    public function toSlack($notifiable): SlackMessage
    {
        $periodString = implode(', ', $this->periods);

        return (new SlackMessage())
            ->error()
            ->content('🚨 Shadow Columns Backfill FAILED')
            ->attachment(function ($attachment) use ($periodString) {
                $attachment
                    ->title('Backfill Job Failed')
                    ->fields([
                        'Periods' => $periodString,
                        'Error' => $this->exception->getMessage(),
                        'Time' => now()->format('Y-m-d H:i:s'),
                    ])
                    ->markdown(['fields']);
            });
    }
}
