<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmailNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

final class GuardianEmailVerificationNotification extends BaseVerifyEmailNotification implements ShouldQueue
{
    use Queueable;

    public function toMail(mixed $notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('メールアドレスの確認')
            ->action('メールアドレスを確認する', $verificationUrl)
            ->markdown('emails.guardian.email-verification', [
                'verificationUrl' => $verificationUrl,
            ]);
    }

    /**
     * バックエンドの署名付き検証URLをそのままメールに載せると、フロントエンドを経由しない
     * 直リンクになってしまうため、署名・有効期限のクエリだけを引き継いでフロントエンドの
     * 確認画面URLへ差し替える。
     */
    protected function verificationUrl(mixed $notifiable): string
    {
        $backendUrl = parent::verificationUrl($notifiable);

        parse_str((string) parse_url($backendUrl, PHP_URL_QUERY), $query);

        return sprintf(
            '%s/guardian/email-verification/verify/%s/%s?%s',
            rtrim((string) config('app.frontend_url'), '/'),
            $notifiable->getKey(),
            sha1($notifiable->getEmailForVerification()),
            http_build_query($query),
        );
    }
}
