<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Progress\StoreOwnMeasurementRequest;
use App\Http\Requests\Progress\UpdateWeeklyGoalRequest;
use App\Models\BodyMeasurement;
use App\Models\MemberGoal;
use App\Services\Progress\MeasurementService;
use App\Services\Progress\StreakService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Portal del socio: racha, meta semanal y evolución física. */
class ProgressController extends Controller
{
    public function __construct(
        private StreakService $streaks,
        private MeasurementService $measurements,
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Member/Progress', [
            'streak' => $this->streaks->forMember($user),
            'progress' => $this->measurements->report($user),
            'defaultGoal' => (int) config('gympro.streaks.weekly_goal', 3),
        ]);
    }

    public function storeMeasurement(StoreOwnMeasurementRequest $request): RedirectResponse
    {
        $user = $request->user();

        $measurement = new BodyMeasurement($request->measurementData());
        $measurement->user_id = $user->id;
        $measurement->recorded_by = $user->id;
        $measurement->save();

        return back()->with('success', '¡Medidas guardadas! Sigue así.');
    }

    public function updateGoal(UpdateWeeklyGoalRequest $request): RedirectResponse
    {
        $user = $request->user();

        $goal = MemberGoal::query()->where('user_id', $user->id)->first() ?? new MemberGoal;
        $goal->user_id = $user->id;
        $goal->weekly_attendance_goal = $request->integer('weekly_attendance_goal');
        $goal->save();

        $this->streaks->forget($user);

        return back()->with('success', 'Meta semanal actualizada.');
    }
}
