<?php

namespace Tests\Feature\Console;

use App\Domain\Staff\StaffRole;
use App\Models\Kindergarten;
use App\Models\KindergartenStaff;
use App\Models\StaffInvitation;
use App\Notifications\InitialPasswordSetupNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CreateKindergartenCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_kindergarten_with_owner_and_setup_invitation(): void
    {
        $this->artisan('kindergarten:create', [
            '--name' => 'サンプル保育園',
            '--owner-name' => '山田 太郎',
            '--owner-email' => 'owner@example.com',
        ])->assertExitCode(0);

        $kindergarten = Kindergarten::sole();
        self::assertSame('サンプル保育園', $kindergarten->name);
        self::assertNotEmpty($kindergarten->slug);

        $owner = KindergartenStaff::sole();
        self::assertSame($kindergarten->id, $owner->kindergarten_id);
        self::assertSame('山田 太郎', $owner->name);
        self::assertSame('owner@example.com', $owner->email);
        self::assertSame('owner@example.com', $owner->email_normalized);
        self::assertSame(StaffRole::Owner, $owner->role);
        self::assertNotNull($owner->invited_at);
        self::assertNull($owner->joined_at);

        $invitation = StaffInvitation::sole();
        self::assertSame($kindergarten->id, $invitation->kindergarten_id);
        self::assertSame($owner->id, $invitation->created_by_staff_id);
        self::assertSame(StaffRole::Owner, $invitation->role);
        self::assertNull($invitation->accepted_at);
        self::assertTrue($invitation->expires_at->isFuture());
    }

    public function test_sends_setup_notification_when_send_invite_flag_is_present(): void
    {
        Notification::fake();

        $this->artisan('kindergarten:create', [
            '--name' => 'サンプル保育園',
            '--owner-name' => '山田 太郎',
            '--owner-email' => 'owner@example.com',
            '--send-invite' => true,
        ])->assertExitCode(0);

        $owner = KindergartenStaff::sole();

        Notification::assertSentTo($owner, InitialPasswordSetupNotification::class);
    }

    public function test_does_not_send_notification_without_send_invite_flag(): void
    {
        Notification::fake();

        $this->artisan('kindergarten:create', [
            '--name' => 'サンプル保育園',
            '--owner-name' => '山田 太郎',
            '--owner-email' => 'owner@example.com',
        ])->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_rejects_duplicate_owner_email_across_kindergartens(): void
    {
        $this->artisan('kindergarten:create', [
            '--name' => '園A',
            '--owner-name' => '山田 太郎',
            '--owner-email' => 'owner@example.com',
        ])->assertExitCode(0);

        $this->artisan('kindergarten:create', [
            '--name' => '園B',
            '--owner-name' => '鈴木 次郎',
            '--owner-email' => 'owner@example.com',
        ])->assertExitCode(1);

        self::assertSame(1, Kindergarten::count());
        self::assertSame(1, KindergartenStaff::count());
    }

    public function test_rejects_invalid_input(): void
    {
        $this->artisan('kindergarten:create', [
            '--name' => '',
            '--owner-name' => '山田 太郎',
            '--owner-email' => 'not-an-email',
        ])->assertExitCode(1);

        self::assertSame(0, Kindergarten::count());
    }
}
