<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_central_landing_renders_with_default_brand(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertSee('--brand-primary', false)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('brand.name')
                ->has('brand.primary')
            );
    }
}
