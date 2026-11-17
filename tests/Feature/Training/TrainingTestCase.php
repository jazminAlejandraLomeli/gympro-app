<?php

namespace Tests\Feature\Training;

use App\Enums\Role;
use App\Models\Exercise;
use App\Models\Gym;
use App\Models\Routine;
use App\Models\RoutineItem;
use App\Models\RoutineRequest;
use App\Models\User;
use App\Services\Training\RoutineAssigner;
use App\Services\Training\TodayRoutine;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Base de las pruebas del Módulo B: dos gimnasios y helpers por gimnasio. */
abstract class TrainingTestCase extends TestCase
{
    use RefreshDatabase;

    protected Gym $iron;

    protected Gym $titan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->iron = Gym::factory()->create(['slug' => 'irondemo']);
        $this->titan = Gym::factory()->create(['slug' => 'titan']);
    }

    protected function url(string $path, string $slug = 'irondemo'): string
    {
        return "http://{$slug}.localhost{$path}";
    }

    /** Congela "hoy" en la zona del gimnasio. */
    protected function freeze(string $dateTime): CarbonImmutable
    {
        $now = CarbonImmutable::parse($dateTime, TodayRoutine::TIMEZONE);
        $this->travelTo($now);

        return $now;
    }

    /** Ejecuta dentro del gimnasio (como lo haría un request o un seeder). */
    protected function inGym(Gym $gym, callable $callback): mixed
    {
        return app(TenantContext::class)->runAs($gym, $callback);
    }

    protected function user(Gym $gym, Role $role = Role::Member, array $attributes = []): User
    {
        return User::factory()->forGym($gym)->role($role)->create($attributes);
    }

    protected function exercise(Gym $gym, array $attributes = []): Exercise
    {
        return $this->inGym($gym, fn () => Exercise::create($attributes + [
            'name' => 'Sentadilla',
            'muscle_group' => 'piernas',
        ]));
    }

    /** Plantilla del gimnasio por defecto; $forced para is_template, member_id, created_by. */
    protected function routine(Gym $gym, array $attributes = [], array $forced = []): Routine
    {
        return $this->inGym($gym, function () use ($attributes, $forced) {
            $routine = new Routine($attributes + ['name' => 'Fuerza']);
            $routine->forceFill($forced + ['is_template' => true, 'member_id' => null])->save();

            return $routine;
        });
    }

    protected function template(Gym $gym, string $type = Routine::TYPE_WEEKLY, array $attributes = [], ?User $creator = null): Routine
    {
        return $this->routine($gym, $attributes + ['type' => $type], ['created_by' => $creator?->id]);
    }

    protected function personal(Gym $gym, User $member, ?User $creator, string $type = Routine::TYPE_WEEKLY, array $attributes = []): Routine
    {
        return $this->routine($gym, $attributes + ['type' => $type], [
            'is_template' => false, 'member_id' => $member->id, 'created_by' => $creator?->id,
        ]);
    }

    protected function addItem(Gym $gym, Routine $routine, Exercise $exercise, ?int $day = 1, array $attributes = []): RoutineItem
    {
        return $this->inGym($gym, fn () => $routine->items()->create($attributes + [
            'day_of_week' => $day, 'exercise_id' => $exercise->id, 'position' => 0, 'sets' => 4, 'reps' => '10-12', 'weight_kg' => 20, 'rest_seconds' => 60,
        ]));
    }

    protected function assign(Gym $gym, Routine $routine, User $member, string $date, ?User $by = null): void
    {
        $this->inGym($gym, fn () => app(RoutineAssigner::class)
            ->assign($routine, [$member->id], CarbonImmutable::parse($date, TodayRoutine::TIMEZONE), $by));
    }

    protected function pendingRequest(Gym $gym, User $member, string $kind = Routine::TYPE_WEEKLY, array $attributes = []): RoutineRequest
    {
        return $this->inGym($gym, function () use ($member, $kind, $attributes) {
            $request = new RoutineRequest($attributes + ['kind' => $kind, 'goal' => 'Bajar de peso', 'days_per_week' => 4]);
            $request->user_id = $member->id;
            $request->save();

            return $request;
        });
    }
}
