<?php

namespace App\Application\Staff\Child;

use App\Domain\Child\ChildStatus;
use App\Domain\Child\Exceptions\ChildNotFoundException;
use App\Domain\Child\Exceptions\ChildStatusTransitionNotAllowedException;
use App\Domain\Child\Exceptions\ChildTenantScopeViolationException;
use App\Domain\ChildClass\Exceptions\ChildClassNotFoundException;
use App\Domain\ChildClass\Exceptions\ChildClassTenantScopeViolationException;
use App\Models\Child;
use App\Models\ChildClass;
use App\Models\KindergartenStaff;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ChildManagementService
{
    public function createChild(
        KindergartenStaff $actor,
        string $name,
        string $childClassId,
        ChildStatus $status,
    ): array {
        $childClass = $this->findChildClassForActor($actor, $childClassId);

        $child = Child::create([
            'child_class_id' => $childClass->id,
            'name' => $name,
            'status' => $status,
        ]);

        return $this->formatChild($child, false);
    }

    public function listChildren(
        KindergartenStaff $actor,
        ?string $status,
        ?string $childClassId,
        ?string $keyword,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        $query = Child::query()
            ->with('childClass')
            ->whereHas('childClass', function ($builder) use ($actor): void {
                $builder->where('kindergarten_id', $actor->kindergarten_id);
            })
            ->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($childClassId !== null && trim($childClassId) !== '') {
            $query->where('child_class_id', trim($childClassId));
        }

        if ($keyword !== null && trim($keyword) !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($keyword));

            $query->where(function ($builder) use ($escaped): void {
                $builder->whereRaw('children.name LIKE ? ESCAPE ?', ["%{$escaped}%", '\\'])
                    ->orWhereHas('childClass', function ($builder) use ($escaped): void {
                        $builder->whereRaw('name LIKE ? ESCAPE ?', ["%{$escaped}%", '\\']);
                    });
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function findChild(KindergartenStaff $actor, string $childId): Child
    {
        $child = Child::query()
            ->with('childClass')
            ->whereKey($childId)
            ->first();

        if ($child === null) {
            throw new ChildNotFoundException;
        }

        if (! $this->isChildInKindergarten($child, $actor->kindergarten_id)) {
            throw new ChildTenantScopeViolationException;
        }

        return $child;
    }

    public function updateChild(
        KindergartenStaff $actor,
        string $childId,
        ?string $name,
        ?string $childClassId,
    ): array {
        $child = $this->findChild($actor, $childId);

        if ($name !== null) {
            $child->forceFill(['name' => $name]);
        }

        if ($childClassId !== null) {
            $childClass = $this->findChildClassForActor($actor, $childClassId);
            $child->forceFill([
                'child_class_id' => $childClass->id,
            ]);
        }

        $child->save();
        $child->load('childClass');

        return $this->formatChild($child, true);
    }

    public function updateChildStatus(
        KindergartenStaff $actor,
        string $childId,
        ChildStatus $status,
    ): array {
        return DB::transaction(function () use ($actor, $childId, $status): array {
            $child = Child::query()
                ->with('childClass')
                ->whereKey($childId)
                ->lockForUpdate()
                ->first();

            if ($child === null) {
                throw new ChildNotFoundException;
            }

            if (! $this->isChildInKindergarten($child, $actor->kindergarten_id)) {
                throw new ChildTenantScopeViolationException;
            }

            $currentStatus = $child->status;

            if ($currentStatus === $status) {
                return [
                    'id' => $child->id,
                    'status' => $child->status->value,
                    'updated_at' => $child->updated_at?->toIso8601String(),
                ];
            }

            if (! $this->isTransitionAllowed($currentStatus, $status)) {
                throw new ChildStatusTransitionNotAllowedException;
            }

            $child->forceFill(['status' => $status])->save();

            return [
                'id' => $child->id,
                'status' => $child->status->value,
                'updated_at' => $child->updated_at?->toIso8601String(),
            ];
        });
    }

    private function isTransitionAllowed(ChildStatus $currentStatus, ChildStatus $newStatus): bool
    {
        return true;
    }

    private function isChildInKindergarten(Child $child, string $kindergartenId): bool
    {
        return $child->childClass->kindergarten_id === $kindergartenId;
    }

    private function findChildClassForActor(KindergartenStaff $actor, string $childClassId): ChildClass
    {
        $childClass = ChildClass::query()->whereKey($childClassId)->first();

        if ($childClass === null) {
            throw new ChildClassNotFoundException;
        }

        if ($childClass->kindergarten_id !== $actor->kindergarten_id) {
            throw new ChildClassTenantScopeViolationException;
        }

        return $childClass;
    }

    private function formatChild(Child $child, bool $includeUpdatedAt): array
    {
        $class = $child->childClass;

        return array_filter([
            'id' => $child->id,
            'kindergarten_id' => $class->kindergarten_id,
            'class_id' => $child->child_class_id,
            'name' => $child->name,
            'class_name' => $class->name,
            'status' => $child->status->value,
            'created_at' => $child->created_at?->toIso8601String(),
            'updated_at' => $includeUpdatedAt ? $child->updated_at?->toIso8601String() : null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
