<?php

namespace App\Application\Staff\Invitation;

use App\Application\Staff\Child\ChildManagementService;
use App\Domain\Invitation\Exceptions\InvitationAlreadyRevokedException;
use App\Domain\Invitation\Exceptions\InvitationAlreadyUsedException;
use App\Domain\Invitation\Exceptions\InvitationNotFoundException;
use App\Domain\Invitation\Exceptions\InvitationReissueLimitExceededException;
use App\Domain\Invitation\Exceptions\InvitationTenantScopeViolationException;
use App\Domain\Invitation\Exceptions\InvitationTokenMismatchException;
use App\Domain\Shared\SecureToken;
use App\Models\ChildInvitation;
use App\Models\KindergartenStaff;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ChildInvitationService
{
    private const DEFAULT_EXPIRES_IN_DAYS = 90;

    private const MAX_REISSUE_COUNT = 3;

    public function __construct(private readonly ChildManagementService $childService) {}

    public function createInvitation(
        KindergartenStaff $actor,
        string $childId,
        string $label,
        ?int $expiresInDays = null,
    ): array {
        $child = $this->childService->findChild($actor, $childId);

        $token = SecureToken::generate();

        $invitation = ChildInvitation::create([
            'kindergarten_id' => $actor->kindergarten_id,
            'child_id' => $child->id,
            'token_hash' => $token->hash(),
            'label' => $label,
            'expires_at' => now()->addDays($expiresInDays ?? self::DEFAULT_EXPIRES_IN_DAYS),
            'created_by_staff_id' => $actor->id,
        ]);

        return [
            'invitation_id' => $invitation->id,
            'invite_url' => $this->buildInviteUrl($token->plainText()),
            'token_expires_at' => $invitation->expires_at->toIso8601String(),
            'qr_payload' => $this->buildInviteUrl($token->plainText()),
        ];
    }

    public function listInvitations(
        KindergartenStaff $actor,
        string $childId,
        ?string $status,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        $child = $this->childService->findChild($actor, $childId);

        $query = ChildInvitation::query()
            ->where('child_id', $child->id)
            ->orderByDesc('created_at');

        $now = now();

        if ($status === 'active') {
            $query->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>=', $now);
        }

        if ($status === 'used') {
            $query->whereNotNull('used_at');
        }

        if ($status === 'revoked') {
            $query->whereNotNull('revoked_at');
        }

        if ($status === 'expired') {
            $query->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '<', $now);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function renderPrintPdf(KindergartenStaff $actor, string $invitationId, string $rawToken): string
    {
        $invitation = $this->findInvitationForActor($actor, $invitationId);

        if (! hash_equals($invitation->token_hash, SecureToken::hashOf($rawToken))) {
            throw new InvitationTokenMismatchException;
        }

        $invitation->loadMissing('child.childClass', 'kindergarten');

        $inviteUrl = $this->buildInviteUrl($rawToken);

        $qrCode = new QrCode($inviteUrl);
        $qrDataUri = (new PngWriter)->write($qrCode)->getDataUri();

        return Pdf::loadView('pdf.child_invitation', [
            'kindergartenName' => $invitation->kindergarten->name,
            'childName' => $invitation->child->name,
            'label' => $invitation->label,
            'inviteUrl' => $inviteUrl,
            'qrDataUri' => $qrDataUri,
            'expiresAt' => $invitation->expires_at,
        ])->output();
    }

    public function revokeInvitation(KindergartenStaff $actor, string $invitationId, ?string $reason): array
    {
        return DB::transaction(function () use ($actor, $invitationId): array {
            $invitation = $this->findInvitationForActor($actor, $invitationId, true);

            if ($invitation->used_at !== null) {
                throw new InvitationAlreadyUsedException;
            }

            if ($invitation->revoked_at === null) {
                $invitation->forceFill(['revoked_at' => now()])->save();
            }

            return [
                'invitation_id' => $invitation->id,
                'revoked_at' => $invitation->revoked_at?->toIso8601String(),
            ];
        });
    }

    public function reissueInvitation(
        KindergartenStaff $actor,
        string $invitationId,
        ?string $label,
        ?int $expiresInDays = null,
    ): array {
        return DB::transaction(function () use ($actor, $invitationId, $label, $expiresInDays): array {
            $invitation = $this->findInvitationForActor($actor, $invitationId, true);

            if ($invitation->used_at !== null) {
                throw new InvitationAlreadyUsedException;
            }

            if ($invitation->revoked_at !== null) {
                throw new InvitationAlreadyRevokedException;
            }

            if ($this->reissueDepthOf($invitation) >= self::MAX_REISSUE_COUNT) {
                throw new InvitationReissueLimitExceededException;
            }

            $token = SecureToken::generate();

            $newInvitation = ChildInvitation::create([
                'kindergarten_id' => $invitation->kindergarten_id,
                'child_id' => $invitation->child_id,
                'token_hash' => $token->hash(),
                'label' => $label ?? $invitation->label,
                'expires_at' => now()->addDays($expiresInDays ?? self::DEFAULT_EXPIRES_IN_DAYS),
                'created_by_staff_id' => $actor->id,
                'reissued_from_invitation_id' => $invitation->id,
            ]);

            $invitation->forceFill(['revoked_at' => now()])->save();

            return [
                'invitation_id' => $newInvitation->id,
                'invite_url' => $this->buildInviteUrl($token->plainText()),
                'token_expires_at' => $newInvitation->expires_at->toIso8601String(),
            ];
        });
    }

    private function reissueDepthOf(ChildInvitation $invitation): int
    {
        $depth = 0;
        $current = $invitation;

        while ($current->reissued_from_invitation_id !== null) {
            $depth++;
            $current = ChildInvitation::query()->findOrFail($current->reissued_from_invitation_id);
        }

        return $depth;
    }

    private function findInvitationForActor(KindergartenStaff $actor, string $invitationId, bool $lockForUpdate = false): ChildInvitation
    {
        $query = ChildInvitation::query()->whereKey($invitationId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $invitation = $query->first();

        if ($invitation === null) {
            throw new InvitationNotFoundException;
        }

        if ($invitation->kindergarten_id !== $actor->kindergarten_id) {
            throw new InvitationTenantScopeViolationException;
        }

        return $invitation;
    }

    private function buildInviteUrl(string $rawToken): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/invitations/'.$rawToken;
    }
}
