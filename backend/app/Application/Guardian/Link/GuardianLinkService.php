<?php

namespace App\Application\Guardian\Link;

use App\Domain\Guardian\Exceptions\GuardianInvitationAlreadyUsedException;
use App\Domain\Guardian\Exceptions\GuardianInvitationInvalidOrExpiredException;
use App\Domain\GuardianLink\Exceptions\GuardianChildLinkAlreadyExistsException;
use App\Domain\Shared\SecureToken;
use App\Models\ChildInvitation;
use App\Models\Guardian;
use App\Models\GuardianChild;
use Illuminate\Support\Facades\DB;

final class GuardianLinkService
{
    public function acceptInvitation(Guardian $guardian, string $rawToken): array
    {
        return DB::transaction(function () use ($guardian, $rawToken): array {
            $invitation = ChildInvitation::query()
                ->with(['kindergarten', 'child.childClass'])
                ->where('token_hash', SecureToken::hashOf($rawToken))
                ->lockForUpdate()
                ->first();

            if (! $invitation instanceof ChildInvitation) {
                throw new GuardianInvitationInvalidOrExpiredException;
            }

            if ($invitation->used_at !== null) {
                throw new GuardianInvitationAlreadyUsedException;
            }

            if ($invitation->revoked_at !== null || $invitation->expires_at->isPast()) {
                throw new GuardianInvitationInvalidOrExpiredException;
            }

            $exists = GuardianChild::query()
                ->where('guardian_id', $guardian->id)
                ->where('child_id', $invitation->child_id)
                ->whereNull('unlinked_at')
                ->exists();

            if ($exists) {
                throw new GuardianChildLinkAlreadyExistsException;
            }

            $link = GuardianChild::create([
                'guardian_id' => $guardian->id,
                'child_id' => $invitation->child_id,
                'kindergarten_id' => $invitation->kindergarten_id,
                'label' => $invitation->label,
                'linked_at' => now(),
            ]);

            $invitation->forceFill([
                'used_at' => now(),
                'used_by_guardian_id' => $guardian->id,
            ])->save();

            return [
                'guardian_id' => $guardian->id,
                'linked_child' => [
                    'id' => $invitation->child_id,
                    'kindergarten_id' => $invitation->kindergarten_id,
                ],
                'linked_at' => $link->linked_at->toIso8601String(),
            ];
        });
    }

    public function listActiveChildren(Guardian $guardian): array
    {
        $links = GuardianChild::query()
            ->with(['child.childClass', 'kindergarten'])
            ->where('guardian_id', $guardian->id)
            ->whereNull('unlinked_at')
            ->orderByDesc('linked_at')
            ->get();

        return [
            'data' => $links->map(function (GuardianChild $link): array {
                return [
                    'child_id' => $link->child_id,
                    'child_name' => $link->child->name,
                    'class_name' => $link->child->childClass->name,
                    'kindergarten_id' => $link->kindergarten_id,
                    'kindergarten_name' => $link->kindergarten->name,
                    'label' => $link->label,
                    'linked_at' => $link->linked_at->toIso8601String(),
                ];
            })->values()->all(),
        ];
    }
}
