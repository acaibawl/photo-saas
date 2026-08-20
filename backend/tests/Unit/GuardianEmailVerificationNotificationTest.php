<?php

namespace Tests\Unit;

use App\Models\Guardian;
use App\Notifications\GuardianEmailVerificationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuardianEmailVerificationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_url_points_to_frontend_domain(): void
    {
        $guardian = Guardian::create([
            'name' => 'テスト太郎',
            'email' => 'verify-target@example.com',
            'password_hash' => Hash::make('password-123'),
        ]);

        $mailMessage = (new GuardianEmailVerificationNotification)->toMail($guardian);

        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $expectedPath = "/guardian/email-verification/verify/{$guardian->id}/".sha1($guardian->getEmailForVerification());

        $this->assertStringStartsWith($frontendUrl.$expectedPath.'?', $mailMessage->actionUrl);
        $this->assertStringContainsString('signature=', $mailMessage->actionUrl);
        $this->assertStringContainsString('expires=', $mailMessage->actionUrl);
    }
}
