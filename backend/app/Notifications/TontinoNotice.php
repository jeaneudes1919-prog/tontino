<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class TontinoNotice extends Notification
{
    public function __construct(public string $title, public string $body, public string $type = 'info', public ?int $cooperativeId = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'type' => $this->type, 'cooperative_id' => $this->cooperativeId];
    }
}
