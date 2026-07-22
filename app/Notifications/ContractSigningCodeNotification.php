<?php

namespace App\Notifications;

use App\Models\LegalContractParty;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractSigningCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private LegalContractParty $party,
        private string $code,
        private int $ttlMinutes,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $contract = $this->party->contract;

        return (new MailMessage())
            ->subject('Код подписания договора ' . $contract->number)
            ->greeting('Здравствуйте, ' . $this->party->name . '!')
            ->line('Вы запросили подписание документа «' . $contract->title . '».')
            ->line('Код простой электронной подписи: ' . $this->code)
            ->line('Код действует ' . $this->ttlMinutes . ' минут и может быть использован один раз.')
            ->action('Открыть договор', route('legal.public.show', $this->party))
            ->line('Никому не сообщайте этот код. Сотрудник CRM не должен просить назвать код по телефону.');
    }
}
