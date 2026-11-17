<?php

namespace Tests\Feature\Member;

use App\Jobs\RecordAttendance;
use App\Models\Gym;
use App\Models\User;
use App\Services\Progress\StreakService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_home_includes_every_module_card(): void
    {
        $this->withoutVite();
        $gym = Gym::factory()->create(['slug' => 'irondemo']);
        $member = User::factory()->forGym($gym)->create();

        $this->actingAs($member)->get('http://irondemo.localhost/app')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Home')
                ->has('membership.hasMembership')
                ->has('todayRoutine.hasRoutine')
                ->has('progress.streak.days', 7)
                ->has('announcementsCard.announcements')
            );
    }

    public function test_recording_attendance_refreshes_the_streak_immediately(): void
    {
        $this->travelTo(now()->startOfWeek()->addDays(2)->setTime(10, 0));
        $gym = Gym::factory()->create();
        $member = User::factory()->forGym($gym)->create();
        $tenant = app(TenantContext::class);
        $streaks = app(StreakService::class);

        $tenant->runAs($gym, function () use ($member, $streaks) {
            $this->assertSame(0, $streaks->forMember($member)['attendedThisWeek']); // queda en caché

            RecordAttendance::dispatch($member->id, now()->toDateTimeString());

            $this->assertSame(1, $streaks->forMember($member)['attendedThisWeek']);
        });
    }
}
