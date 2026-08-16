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
            ->greeting("{$notifiable->name} 様")
            ->line("「{$this->kindergartenName}」の管理者アカウントが作成されました。")
            ->line('以下のリンクから初回パスワードを設定してください。')
            ->action('初回パスワード設定を開始する', $setupUrl)
            ->line('リンクの有効期限が切れた場合は、運営者に再発行を依頼してください。');
    }
}
