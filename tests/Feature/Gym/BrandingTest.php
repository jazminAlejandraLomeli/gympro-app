<?php

namespace Tests\Feature\Gym;

use App\Enums\Role;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->gym = Gym::factory()->create(['slug' => 'irondemo']);
    }

    private function url(string $path = '/panel/marca'): string
    {
        return "http://irondemo.localhost{$path}";
    }

    public function test_gym_admin_updates_brand_and_logo(): void
    {
        $admin = User::factory()->forGym($this->gym)->role(Role::GymAdmin)->create();

        $this->actingAs($admin)->post($this->url(), [
            'brand_name' => 'Iron Nuevo',
            'primary_color' => '#22C55E',
            'secondary_color' => '#111827',
            'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
        ])->assertRedirect()->assertSessionHas('success');

        $settings = $this->gym->settings()->first();
        $this->assertSame('Iron Nuevo', $settings->brand_name);
        $this->assertStringContainsString("gyms/{$this->gym->id}/branding/", $settings->logo_url);

        // La marca nueva se ve de inmediato (caché invalidada)
        $this->actingAs($admin)->get($this->url('/panel'))->assertSee('--brand-primary: #22c55e', false);
    }

    public function test_rejects_invalid_colors(): void
    {
        $admin = User::factory()->forGym($this->gym)->role(Role::GymAdmin)->create();

        $this->actingAs($admin)->post($this->url(), [
            'brand_name' => 'X',
            'primary_color' => 'red; } body {',
            'secondary_color' => '#111827',
        ])->assertSessionHasErrors('primary_color');
    }

    public function test_trainer_cannot_change_brand(): void
    {
        $trainer = User::factory()->forGym($this->gym)->role(Role::Trainer)->create();

        $this->actingAs($trainer)->post($this->url(), [
            'brand_name' => 'Hack', 'primary_color' => '#000000', 'secondary_color' => '#000000',
        ])->assertForbidden();
    }
}
