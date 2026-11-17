<?php

namespace App\Services\Progress;

use App\Models\User;

/**
 * Props para las tarjetas del inicio del socio (Member/Home):
 *
 *   'progress' => ProgressSummary::forMemberHome($request->user()),
 *
 *   <StreakCard streak={progress.streak} />
 *   {progress.measurementReminder && <MeasurementReminderCard reminder={progress.measurementReminder} />}
 */
class ProgressSummary
{
    /** @return array{streak: array, measurementReminder: ?array} */
    public static function forMemberHome(User $user): array
    {
        $reminder = app(MeasurementService::class)->reminder($user);

        return [
            'streak' => app(StreakService::class)->forMember($user),
            'measurementReminder' => $reminder ? [...$reminder, 'href' => '/app/progreso#registrar'] : null,
        ];
    }
}
