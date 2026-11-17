<?php

namespace Tests\Feature\Training;

use App\Enums\Role;
use App\Models\Exercise;
use App\Models\Gym;
use App\Models\Routine;
use App\Models\User;
use App\Tenancy\TenantContext;
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

    protected function routine(Gym $gym, array $attributes = []): Routine
    {
        return $this->inGym($gym, fn () => Routine::create($attributes + ['name' => 'Fuerza']));
    }
}
