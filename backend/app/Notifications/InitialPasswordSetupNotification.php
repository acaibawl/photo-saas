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
        // TODO: フロントエンドの設定画面URLが未確定のため、リンクではなく設定コードとして案内する。
        return (new MailMessage)
            ->subject("【{$this->kindergartenName}】初回パスワード設定のご案内")
            ->greeting("{$notifiable->name} 様")
            ->line("「{$this->kindergartenName}」の管理者アカウントが作成されました。")
            ->line('以下の設定コードを使用して、初回パスワードを設定してください。')
            ->line($this->rawSetupToken)
            ->line('このコードの有効期限が切れた場合は、運営者に再発行を依頼してください。');
    }
}
