<?php

namespace App\Application\Staff\GuardianLink;

use App\Application\Staff\Child\ChildManagementService;
use App\Domain\GuardianLink\Exceptions\GuardianLinkAlreadyUnlinkedException;
use App\Domain\GuardianLink\Exceptions\GuardianLinkNotFoundException;
use App\Domain\GuardianLink\Exceptions\GuardianLinkNotUnlinkedException;
use App\Domain\GuardianLink\Exceptions\GuardianLinkTenantScopeViolationException;
use App\Models\GuardianChild;
use App\Models\KindergartenStaff;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class GuardianLinkManagementService
{
    public function __construct(private readonly ChildManagementService $childManagementService) {}

    public function listGuardianLinks(
        KindergartenStaff $actor,
        string $childId,
        bool $includeUnlinked = false,
    ): LengthAwarePaginator {
        $child = $this->childManagementService->findChild($actor, $childId);

        $query = GuardianChild::query()
            ->with('guardian')
            ->where('child_id', $child->id)
            ->orderByDesc('linked_at');

        if (! $includeUnlinked) {
            $query->whereNull('unlinked_at');
        }

        return $query->paginate(20, ['*'], 'page', 1);
    }

    public function unlinkGuardianLink(
        KindergartenStaff $actor,
        string $linkId,
        ?string $reason,
        string $confirmText,
    ): array {
        return DB::transaction(function () use ($actor, $linkId, $reason, $confirmText): array {
            $link = GuardianChild::query()->whereKey($linkId)->lockForUpdate()->first();

            if ($link === null) {
                throw new GuardianLinkNotFoundException;
            }

            if ($link->kindergarten_id !== $actor->kindergarten_id) {
                throw new GuardianLinkTenantScopeViolationException;
            }

            if ($link->unlinked_at !== null) {
                throw new GuardianLinkAlreadyUnlinkedException;
            }

            if ($confirmText !== 'UNLINK') {
                throw new \InvalidArgumentException('confirm_text must be UNLINK');
            }

            $link->forceFill([
                'unlinked_at' => now(),
                'unlinked_by_staff_id' => $actor->id,
            ])->save();

            return [
                'link_id' => $link->id,
                'unlinked_at' => $link->unlinked_at?->toIso8601String(),
                'unlinked_by_staff_id' => $link->unlinked_by_staff_id,
            ];
        });
    }

    public function restoreGuardianLink(
        KindergartenStaff $actor,
        string $linkId,
    ): array {
        return DB::transaction(function () use ($actor, $linkId): array {
            $link = GuardianChild::query()->whereKey($linkId)->lockForUpdate()->first();

            if ($link === null) {
                throw new GuardianLinkNotFoundException;
            }

            if ($link->kindergarten_id !== $actor->kindergarten_id) {
                throw new GuardianLinkTenantScopeViolationException;
            }

            if ($link->unlinked_at === null) {
                throw new GuardianLinkNotUnlinkedException;
            }

            $link->forceFill([
                'unlinked_at' => null,
                'unlinked_by_staff_id' => null,
            ])->save();

            return [
                'link_id' => $link->id,
                'unlinked_at' => null,
                'restored_at' => $link->updated_at?->toIso8601String(),
            ];
        });
    }
}
