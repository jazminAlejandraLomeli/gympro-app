<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class AdminTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->super = User::factory()->superAdmin()->create();
    }

    protected function url(string $path = '/admin', ?string $slug = null): string
    {
        return 'http://'.($slug ? "{$slug}." : '').'localhost'.$path;
    }

    /** En pruebas la app no se reinicia entre requests: limpia el guard. */
    protected function fresh(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }
}
