<?php

namespace App\Application\Staff\ChildClass;

use App\Domain\ChildClass\Exceptions\ChildClassAlreadyExistsException;
use App\Domain\ChildClass\Exceptions\ChildClassInUseException;
use App\Domain\ChildClass\Exceptions\ChildClassNotFoundException;
use App\Domain\ChildClass\Exceptions\ChildClassTenantScopeViolationException;
use App\Models\Child;
use App\Models\ChildClass;
use App\Models\KindergartenStaff;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ChildClassManagementService
{
    public function createChildClass(KindergartenStaff $actor, string $name): array
    {
        $normalizedName = trim($name);

        if ($normalizedName === '') {
            throw new \InvalidArgumentException('Child class name cannot be empty');
        }

        if ($this->existsForKindergarten($actor->kindergarten_id, $normalizedName)) {
            throw new ChildClassAlreadyExistsException;
        }

        $childClass = ChildClass::create([
            'kindergarten_id' => $actor->kindergarten_id,
            'name' => $normalizedName,
        ]);

        return $this->formatChildClass($childClass);
    }

    public function listChildClasses(KindergartenStaff $actor, ?string $keyword, int $page, int $perPage): LengthAwarePaginator
    {
        $query = ChildClass::query()
            ->where('kindergarten_id', $actor->kindergarten_id)
            ->orderByDesc('created_at');

        if ($keyword !== null && trim($keyword) !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($keyword));
            $query->whereRaw('name LIKE ? ESCAPE ?', ["%{$escaped}%", '\\']);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function findChildClass(KindergartenStaff $actor, string $childClassId): ChildClass
    {
        $childClass = ChildClass::query()
            ->whereKey($childClassId)
            ->first();

        if ($childClass === null) {
            throw new ChildClassNotFoundException;
        }

        if ($childClass->kindergarten_id !== $actor->kindergarten_id) {
            throw new ChildClassTenantScopeViolationException;
        }

        return $childClass;
    }

    public function updateChildClass(KindergartenStaff $actor, string $childClassId, string $name): array
    {
        $childClass = $this->findChildClass($actor, $childClassId);
        $normalizedName = trim($name);

        if ($normalizedName === '') {
            throw new \InvalidArgumentException('Child class name cannot be empty');
        }

        if ($normalizedName !== $childClass->name && $this->existsForKindergarten($actor->kindergarten_id, $normalizedName)) {
            throw new ChildClassAlreadyExistsException;
        }

        $childClass->forceFill(['name' => $normalizedName])->save();

        return $this->formatChildClass($childClass);
    }

    public function deleteChildClass(KindergartenStaff $actor, string $childClassId): array
    {
        return DB::transaction(function () use ($actor, $childClassId): array {
            $childClass = $this->findChildClass($actor, $childClassId);

            $inUseCount = Child::query()
                ->where('kindergarten_id', $actor->kindergarten_id)
                ->where('child_class_id', $childClass->id)
                ->count();

            if ($inUseCount > 0) {
                throw new ChildClassInUseException;
            }

            $childClass->delete();

            return [
                'deleted' => true,
                'id' => $childClass->id,
            ];
        });
    }

    private function existsForKindergarten(string $kindergartenId, string $name): bool
    {
        return ChildClass::query()
            ->where('kindergarten_id', $kindergartenId)
            ->whereRaw('LOWER(name) = LOWER(?)', [$name])
            ->exists();
    }

    private function formatChildClass(ChildClass $childClass): array
    {
        return [
            'id' => $childClass->id,
            'kindergarten_id' => $childClass->kindergarten_id,
            'name' => $childClass->name,
            'created_at' => $childClass->created_at?->toIso8601String(),
            'updated_at' => $childClass->updated_at?->toIso8601String(),
        ];
    }
}
