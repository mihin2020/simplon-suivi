<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\Formation;
use App\Models\User;

class AttendancePolicy
{
    public function before(User $user): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendances.view') || $user->trainer !== null;
    }

    public function view(User $user, Attendance $attendance): bool
    {
        if ($user->hasPermission('attendances.view')) {
            return true;
        }

        return $this->isTrainerOfFormation($user, $attendance->formation_id);
    }

    public function create(User $user, Formation $formation): bool
    {
        if ($user->hasPermission('attendances.create')) {
            return true;
        }

        return $this->isTrainerOfFormation($user, $formation->id);
    }

    public function update(User $user, Attendance $attendance): bool
    {
        if ($user->hasPermission('attendances.update')) {
            return true;
        }

        return $this->isTrainerOfFormation($user, $attendance->formation_id);
    }

    private function isTrainerOfFormation(User $user, string $formationId): bool
    {
        $trainer = $user->trainer;

        if ($trainer === null) {
            return false;
        }

        return $trainer->formations()->where('formations.id', $formationId)->exists();
    }
}
