<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Уведомление в колокольчик Orchid с доп. метаданными (message_id и т.п.).
 */
class AppDashboardMessage extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly string $action,
        private readonly string $type = 'info',
        private readonly array $meta = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return array_merge([
            'title' => $this->title,
            'message' => $this->message,
            'action' => $this->action,
            'type' => $this->type,
        ], $this->meta);
    }
}
