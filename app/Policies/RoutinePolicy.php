<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Routine;
use App\Models\RoutineAssignment;
use App\Models\User;

/**
 * Permisos por rutina. El gimnasio ya lo filtra BelongsToGym (una rutina
 * de otro gimnasio responde 404 en el route model binding).
 *
 *  - Coach y dueño: todo dentro de su gimnasio.
 *  - Socio: ve sus rutinas personales o las que tiene asignadas; solo
 *    edita o borra las que él mismo creó. Nunca ve plantillas.
 */
class RoutinePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function view(User $user, Routine $routine): bool
    {
        if ($this->isStaff($user)) {
            return true;
        }

        if (! $user->hasRole(Role::Member) || $routine->is_template) {
            return false;
        }

        return $routine->member_id === $user->id
            || RoutineAssignment::where('routine_id', $routine->id)->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->isStaff($user) || $user->hasRole(Role::Member);
    }

    public function update(User $user, Routine $routine): bool
    {
        if ($this->isStaff($user)) {
            return true;
        }

        return $this->isOwnMemberRoutine($user, $routine);
    }

    public function delete(User $user, Routine $routine): bool
    {
        return $this->update($user, $routine);
    }

    /** Solo las plantillas se asignan desde el panel (se duplican por socio). */
    public function assign(User $user, Routine $routine): bool
    {
        return $this->isStaff($user) && $routine->is_template;
    }

    /** "Volver a usar" desde el historial del socio. */
    public function reuse(User $user, Routine $routine): bool
    {
        return $user->hasRole(Role::Member) && $this->view($user, $routine);
    }

    private function isOwnMemberRoutine(User $user, Routine $routine): bool
    {
        return $user->hasRole(Role::Member)
            && ! $routine->is_template
            && $routine->member_id === $user->id
            && $routine->created_by === $user->id;
    }

    private function isStaff(User $user): bool
    {
        return $user->hasRole(Role::GymAdmin, Role::Trainer);
    }
}
