<?php

namespace App\Application\Staff\Invitation;

use App\Application\Staff\Auth\StaffTokenService;
use App\Domain\Shared\EmailAddress;
use App\Domain\Shared\SecureToken;
use App\Domain\Staff\Exceptions\StaffEmailAlreadyExistsException;
use App\Domain\Staff\Exceptions\StaffInvitationAlreadyAcceptedException;
use App\Domain\Staff\Exceptions\StaffInvitationAlreadyExistsException;
use App\Domain\Staff\Exceptions\StaffInvitationInvalidOrExpiredException;
use App\Domain\Staff\Exceptions\StaffMemberNotFoundException;
use App\Domain\Staff\StaffRole;
use App\Models\KindergartenStaff;
use App\Models\StaffInvitation;
use App\Notifications\StaffInvitationNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

final class StaffInvitationService
{
    private const DEFAULT_EXPIRES_IN_DAYS = 7;

    public function __construct(private readonly StaffTokenService $tokenService) {}

    public function createInvitation(
        KindergartenStaff $actor,
        string $name,
        string $email,
        StaffRole $role,
        ?int $expiresInDays = null,
    ): array {
        $normalizedEmail = EmailAddress::fromString($email)->normalized();

        if ($role !== StaffRole::Staff) {
            throw new \InvalidArgumentException('Only staff role can be invited via this endpoint');
        }

        if ($this->staffEmailExists($actor->kindergarten_id, $normalizedEmail)) {
            throw new StaffEmailAlreadyExistsException;
        }

        if ($this->hasPendingInvitation($actor->kindergarten_id, $normalizedEmail)) {
            throw new StaffInvitationAlreadyExistsException;
        }

        $token = SecureToken::generate();

        $invitation = StaffInvitation::create([
            'kindergarten_id' => $actor->kindergarten_id,
            'name' => $name,
            'email' => trim($email),
            'email_normalized' => $normalizedEmail,
            'role' => $role,
            'token_hash' => $token->hash(),
            'expires_at' => now()->addDays($expiresInDays ?? self::DEFAULT_EXPIRES_IN_DAYS),
            'created_by_staff_id' => $actor->id,
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new StaffInvitationNotification($actor->kindergarten->name, $invitation, $token->plainText()));

        return [
            'invitation_id' => $invitation->id,
            'invited_name' => $invitation->name,
            'invited_email' => $invitation->email,
            'role' => $invitation->role->value,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ];
    }

    public function listInvitations(KindergartenStaff $actor, ?string $status, int $perPage): LengthAwarePaginator
    {
        $query = StaffInvitation::query()
            ->where('kindergarten_id', $actor->kindergarten_id)
            ->orderByDesc('created_at');

        $now = now();

        if ($status === 'pending') {
            $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>=', $now);
        }

        if ($status === 'accepted') {
            $query->whereNotNull('accepted_at');
        }

        if ($status === 'revoked') {
            $query->whereNotNull('revoked_at');
        }

        if ($status === 'expired') {
            $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '<', $now);
        }

        return $query->paginate($perPage);
    }

    public function revokeInvitation(KindergartenStaff $actor, string $invitationId): array
    {
        $invitation = StaffInvitation::query()
            ->where('kindergarten_id', $actor->kindergarten_id)
            ->whereKey($invitationId)
            ->first();

        if ($invitation === null) {
            throw new StaffMemberNotFoundException('Staff invitation not found');
        }

        if ($invitation->accepted_at !== null) {
            throw new StaffInvitationAlreadyAcceptedException;
        }

        if ($invitation->revoked_at === null) {
            $invitation->forceFill(['revoked_at' => now()])->save();
        }

        return [
            'invitation_id' => $invitation->id,
            'revoked_at' => $invitation->revoked_at?->toIso8601String(),
        ];
    }

    public function previewInvitation(string $rawToken): array
    {
        $invitation = $this->locklessFindByRawToken($rawToken);

        if ($invitation === null || ! $this->isInvitationAvailable($invitation)) {
            throw new StaffInvitationInvalidOrExpiredException;
        }

        return [
            'kindergarten_name' => $invitation->kindergarten->name,
            'invited_name' => $invitation->name,
            'invited_email' => $invitation->email,
            'role' => $invitation->role->value,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ];
    }

    public function acceptInvitation(string $rawToken, string $password, string $ipAddress, ?string $userAgent): array
    {
        return DB::transaction(function () use ($rawToken, $password, $ipAddress, $userAgent): array {
            /** @var StaffInvitation|null $invitation */
            $invitation = StaffInvitation::query()
                ->where('token_hash', SecureToken::hashOf($rawToken))
                ->lockForUpdate()
                ->first();

            if (! $invitation instanceof StaffInvitation) {
                throw new StaffInvitationInvalidOrExpiredException;
            }

            if ($invitation->accepted_at !== null) {
                throw new StaffInvitationAlreadyAcceptedException;
            }

            if (! $this->isInvitationAvailable($invitation)) {
                throw new StaffInvitationInvalidOrExpiredException;
            }

            $emailNormalized = EmailAddress::fromString($invitation->email)->normalized();

            $existingStaff = KindergartenStaff::query()
                ->where('kindergarten_id', $invitation->kindergarten_id)
                ->where('email_normalized', $emailNormalized)
                ->lockForUpdate()
                ->first();

            $staff = $this->resolveOrCreateAcceptedStaff($invitation, $existingStaff, $password, $emailNormalized);

            $invitation->forceFill([
                'accepted_at' => now(),
                'accepted_staff_id' => $staff->id,
            ])->save();

            $tokens = $this->tokenService->issueTokensForStaff($staff, $ipAddress, $userAgent);

            return [
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'token_type' => $tokens['token_type'],
                'expires_in' => $tokens['expires_in'],
                'staff' => [
                    'id' => $staff->id,
                    'kindergarten_id' => $staff->kindergarten_id,
                    'role' => $staff->role->value,
                ],
            ];
        });
    }

    private function resolveOrCreateAcceptedStaff(
        StaffInvitation $invitation,
        ?KindergartenStaff $existingStaff,
        string $password,
        string $emailNormalized,
    ): KindergartenStaff {
        if ($existingStaff === null) {
            return KindergartenStaff::create([
                'kindergarten_id' => $invitation->kindergarten_id,
                'name' => $invitation->name,
                'email' => $invitation->email,
                'email_normalized' => $emailNormalized,
                'password_hash' => Hash::make($password),
                'role' => $invitation->role,
                'invited_at' => $invitation->created_at ?? now(),
                'joined_at' => now(),
            ]);
        }

        // Allow the initial owner account created by command to complete setup with the same token flow.
        if ($invitation->role === StaffRole::Owner && $existingStaff->id === $invitation->created_by_staff_id) {
            $existingStaff->forceFill([
                'password_hash' => Hash::make($password),
                'joined_at' => $existingStaff->joined_at ?? now(),
            ])->save();

            return $existingStaff;
        }

        throw new StaffEmailAlreadyExistsException;
    }

    private function staffEmailExists(string $kindergartenId, string $emailNormalized): bool
    {
        return KindergartenStaff::query()
            ->where('kindergarten_id', $kindergartenId)
            ->where('email_normalized', $emailNormalized)
            ->exists();
    }

    private function hasPendingInvitation(string $kindergartenId, string $emailNormalized): bool
    {
        return StaffInvitation::query()
            ->where('kindergarten_id', $kindergartenId)
            ->where('email_normalized', $emailNormalized)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>=', now())
            ->exists();
    }

    private function locklessFindByRawToken(string $rawToken): ?StaffInvitation
    {
        return StaffInvitation::query()
            ->with('kindergarten')
            ->where('token_hash', SecureToken::hashOf($rawToken))
            ->first();
    }

    private function isInvitationAvailable(StaffInvitation $invitation): bool
    {
        return $invitation->accepted_at === null
            && $invitation->revoked_at === null
            && $invitation->expires_at->isFuture();
    }
}
