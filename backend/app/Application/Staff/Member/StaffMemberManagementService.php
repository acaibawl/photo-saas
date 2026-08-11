<?php

namespace App\Application\Staff\Member;

use App\Application\Staff\Auth\StaffTokenService;
use App\Domain\Staff\Exceptions\OwnerMinimumRequiredException;
use App\Domain\Staff\Exceptions\StaffDeactivateSelfForbiddenException;
use App\Domain\Staff\Exceptions\StaffMemberNotFoundException;
use App\Domain\Staff\Exceptions\StaffRoleChangeSelfForbiddenException;
use App\Domain\Staff\StaffRole;
use App\Models\KindergartenStaff;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class StaffMemberManagementService
{
    public function __construct(private readonly StaffTokenService $tokenService) {}

    public function listMembers(
        KindergartenStaff $actor,
        ?string $status,
        ?string $role,
        ?string $keyword,
        int $perPage,
    ): LengthAwarePaginator {
        $query = KindergartenStaff::query()
            ->where('kindergarten_id', $actor->kindergarten_id)
            ->orderByDesc('created_at');

        if ($status === 'active') {
            $query->whereNull('deactivated_at');
        }

        if ($status === 'inactive') {
            $query->whereNotNull('deactivated_at');
        }

        if ($role !== null) {
            $query->where('role', $role);
        }

        if ($keyword !== null && trim($keyword) !== '') {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], trim($keyword));
            $query->where(function ($builder) use ($escaped): void {
                $builder->where('name', 'like', "%{$escaped}%")
                    ->orWhere('email', 'like', "%{$escaped}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function findMember(KindergartenStaff $actor, string $staffId): KindergartenStaff
    {
        $staff = KindergartenStaff::query()
            ->where('kindergarten_id', $actor->kindergarten_id)
            ->whereKey($staffId)
            ->first();

        if ($staff === null) {
            throw new StaffMemberNotFoundException;
        }

        return $staff;
    }

    public function changeRole(KindergartenStaff $actor, string $staffId, StaffRole $newRole): array
    {
        return DB::transaction(function () use ($actor, $staffId, $newRole): array {
            $activeOwners = $this->lockActiveOwners($actor->kindergarten_id);

            $target = KindergartenStaff::query()
                ->where('kindergarten_id', $actor->kindergarten_id)
                ->whereKey($staffId)
                ->lockForUpdate()
                ->first();

            if ($target === null) {
                throw new StaffMemberNotFoundException;
            }

            if ($target->id === $actor->id) {
                throw new StaffRoleChangeSelfForbiddenException;
            }

            if (
                $target->role === StaffRole::Owner
                && $newRole === StaffRole::Staff
                && $target->deactivated_at === null
                && $activeOwners->count() <= 1
            ) {
                throw new OwnerMinimumRequiredException;
            }

            $target->forceFill(['role' => $newRole])->save();

            return [
                'staff_id' => $target->id,
                'role' => $target->role->value,
                'updated_at' => $target->updated_at?->toIso8601String(),
            ];
        });
    }

    public function deactivate(KindergartenStaff $actor, string $staffId): array
    {
        return DB::transaction(function () use ($actor, $staffId): array {
            $activeOwners = $this->lockActiveOwners($actor->kindergarten_id);

            $target = KindergartenStaff::query()
                ->where('kindergarten_id', $actor->kindergarten_id)
                ->whereKey($staffId)
                ->lockForUpdate()
                ->first();

            if ($target === null) {
                throw new StaffMemberNotFoundException;
            }

            if ($target->id === $actor->id) {
                throw new StaffDeactivateSelfForbiddenException;
            }

            if (
                $target->role === StaffRole::Owner
                && $target->deactivated_at === null
                && $activeOwners->count() <= 1
            ) {
                throw new OwnerMinimumRequiredException;
            }

            if ($target->deactivated_at === null) {
                $target->forceFill(['deactivated_at' => now()])->save();
            }

            $this->tokenService->revokeAllActiveRefreshTokensForStaff($target->id);

            return [
                'staff_id' => $target->id,
                'deactivated_at' => $target->deactivated_at?->toIso8601String(),
            ];
        });
    }

    public function reactivate(KindergartenStaff $actor, string $staffId): array
    {
        $target = KindergartenStaff::query()
            ->where('kindergarten_id', $actor->kindergarten_id)
            ->whereKey($staffId)
            ->first();

        if ($target === null) {
            throw new StaffMemberNotFoundException;
        }

        $reactivatedAt = now();

        if ($target->deactivated_at !== null) {
            $target->forceFill(['deactivated_at' => null])->save();
            $reactivatedAt = $target->updated_at ?? $reactivatedAt;
        }

        return [
            'staff_id' => $target->id,
            'reactivated_at' => $reactivatedAt->toIso8601String(),
        ];
    }

    private function lockActiveOwners(string $kindergartenId)
    {
        return KindergartenStaff::query()
            ->where('kindergarten_id', $kindergartenId)
            ->where('role', StaffRole::Owner->value)
            ->whereNull('deactivated_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }
}
