<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TestNotification extends Notification
{
    use Queueable;

    public function __construct()
    {
        //
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject(__('email.testNotification.subject'))
            ->greeting(__('email.testNotification.greeting'))
            ->line(__('email.testNotification.line1'))
            ->line(__('email.testNotification.line2'))
            ->line(__('email.testNotification.line3'));
    }
}
