<?php

namespace App\Notifications;

use App\Models\StaffInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class StaffInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $kindergartenName,
        private readonly StaffInvitation $invitation,
        private readonly string $rawToken,
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
        // TODO: あとでbladeテンプレートに置き換える
        return (new MailMessage)
            ->subject("【{$this->kindergartenName}】スタッフ招待のお知らせ")
            ->line("{$this->invitation->name} 様")
            ->line("「{$this->kindergartenName}」にスタッフとして招待されました。")
            ->line('以下の招待トークンを初回設定画面で入力してください。')
            ->line($this->rawToken)
            ->line('有効期限: '.$this->invitation->expires_at->toRfc3339String());
    }
}
