<?php

namespace App\Services\Training;

use App\Models\Routine;
use App\Models\RoutineAssignment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Asigna rutinas a socios.
 *
 *  - Semanal: desactiva SOLO la semanal activa anterior del socio.
 *  - De un día: no toca la semanal; si ya había otra de un día para esa
 *    misma fecha, la reemplaza.
 */
class RoutineAssigner
{
    /**
     * @param  list<int>  $userIds  socios (ya validados del gimnasio activo)
     * @param  CarbonInterface  $date  inicio (semanal) o fecha de la sesión (un día)
     */
    public function assign(Routine $routine, array $userIds, CarbonInterface $date, ?User $assignedBy = null): int
    {
        return $routine->isSingle()
            ? $this->assignForDay($routine, $userIds, $date, $assignedBy)
            : $this->assignWeekly($routine, $userIds, $date, $assignedBy);
    }

    public function assignWeekly(Routine $routine, array $userIds, CarbonInterface $startsOn, ?User $assignedBy = null): int
    {
        $userIds = $this->ids($userIds);

        return DB::transaction(function () use ($routine, $userIds, $startsOn, $assignedBy) {
            RoutineAssignment::query()
                ->whereIn('user_id', $userIds)
                ->weekly()
                ->active()
                ->lockForUpdate()
                ->update(['active' => false, 'updated_at' => now()]);

            foreach ($userIds as $userId) {
                $this->create($routine, $userId, $startsOn->toDateString(), null, $assignedBy);
            }

            return count($userIds);
        });
    }

    public function assignForDay(Routine $routine, array $userIds, CarbonInterface $day, ?User $assignedBy = null): int
    {
        $userIds = $this->ids($userIds);
        $date = $day->toDateString();

        return DB::transaction(function () use ($routine, $userIds, $date, $assignedBy) {
            RoutineAssignment::query()
                ->whereIn('user_id', $userIds)
                ->where('scheduled_for', $date)
                ->active()
                ->lockForUpdate()
                ->update(['active' => false, 'updated_at' => now()]);

            foreach ($userIds as $userId) {
                $this->create($routine, $userId, $date, $date, $assignedBy);
            }

            return count($userIds);
        });
    }

    public function deactivate(RoutineAssignment $assignment): void
    {
        $assignment->update(['active' => false]);
    }

    private function create(Routine $routine, int $userId, string $startsOn, ?string $scheduledFor, ?User $assignedBy): void
    {
        $assignment = new RoutineAssignment([
            'routine_id' => $routine->id,
            'starts_on' => $startsOn,
            'scheduled_for' => $scheduledFor,
            'active' => true,
        ]);
        $assignment->user_id = $userId;
        $assignment->assigned_by = $assignedBy?->id;
        $assignment->save();
    }

    /** @return list<int> */
    private function ids(array $userIds): array
    {
        return array_values(array_unique(array_map('intval', $userIds)));
    }
}
