<?php

namespace App\Services\Training;

use App\Models\Routine;
use App\Models\RoutineAssignment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Asigna una rutina a socios. Cada socio tiene a lo más UNA rutina
 * activa: al asignar otra se desactiva la anterior.
 */
class RoutineAssigner
{
    /**
     * @param  list<int>  $userIds  socios (ya validados del gimnasio activo)
     */
    public function assign(Routine $routine, array $userIds, CarbonInterface $startsOn, ?User $assignedBy = null): int
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        return DB::transaction(function () use ($routine, $userIds, $startsOn, $assignedBy) {
            RoutineAssignment::query()
                ->whereIn('user_id', $userIds)
                ->where('active', true)
                ->lockForUpdate()
                ->update(['active' => false, 'updated_at' => now()]);

            foreach ($userIds as $userId) {
                $assignment = new RoutineAssignment([
                    'routine_id' => $routine->id,
                    'user_id' => $userId,
                    'starts_on' => $startsOn->toDateString(),
                    'active' => true,
                ]);
                $assignment->assigned_by = $assignedBy?->id;
                $assignment->save();
            }

            return count($userIds);
        });
    }

    public function deactivate(RoutineAssignment $assignment): void
    {
        $assignment->update(['active' => false]);
    }
}
