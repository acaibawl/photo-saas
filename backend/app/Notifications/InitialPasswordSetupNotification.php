<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class InitialPasswordSetupNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $kindergartenName,
        private readonly string $rawSetupToken,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $setupUrl = rtrim((string) config('app.frontend_url'), '/').'/staff/invitations/'.$this->rawSetupToken;

        return (new MailMessage)
            ->subject("【{$this->kindergartenName}】初回パスワード設定のご案内")
            ->action('初回パスワード設定を開始する', $setupUrl)
            ->markdown('emails.staff.initial-password-setup', [
                'staffName' => $notifiable->name,
                'kindergartenName' => $this->kindergartenName,
                'setupUrl' => $setupUrl,
            ]);
    }
}
