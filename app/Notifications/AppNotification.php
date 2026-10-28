<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppNotification extends Notification
{
    public function __construct(
        private string $type,
        private string $title,
        private string $body,
        private ?string $url = null,
        private array $channels = ['database'],
    ) {}

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line($this->body)
            ->salutation("L'équipe " . config('app.name'));

        if ($this->url) {
            $mail->action('Voir sur le site', rtrim(config('shop.frontend_url'), '/') . $this->url);
        }

        return $mail;
    }
}