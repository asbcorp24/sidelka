<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);
        $appName = config('app.name', 'Сиделка24');

        return (new MailMessage)
            ->subject('Подтверждение email — ' . $appName)
            ->greeting('Здравствуйте, ' . $notifiable->name . '!')
            ->line('Для завершения регистрации подтвердите адрес электронной почты.')
            ->action('Подтвердить email', $verificationUrl)
            ->line('Ссылка действительна ограниченное время. Если вы не регистрировались, просто проигнорируйте это письмо.')
            ->salutation('Команда ' . $appName);
    }
}
