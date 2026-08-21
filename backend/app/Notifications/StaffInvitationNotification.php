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
        $invitationUrl = rtrim((string) config('app.frontend_url'), '/').'/staff/invitations/'.$this->rawToken;

        return (new MailMessage)
            ->subject("【{$this->kindergartenName}】スタッフ招待のお知らせ")
            ->action('招待を受諾して初回設定を行う', $invitationUrl)
            ->markdown('emails.staff.invitation', [
                'invitedName' => $this->invitation->name,
                'kindergartenName' => $this->kindergartenName,
                'invitationUrl' => $invitationUrl,
                'expiresAt' => $this->invitation->expires_at,
            ]);
    }
}
