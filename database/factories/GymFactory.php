<?php

namespace Database\Factories;

use App\Enums\GymStatus;
use App\Models\Gym;
use App\Models\GymSettings;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Gym>
 */
class GymFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Gym '.fake()->unique()->lastName();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'saas_monthly_fee' => 1800,
            'saas_paid_until' => now()->addMonth(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Gym $gym) {
            $settings = new GymSettings([
                'brand_name' => $gym->name,
                'primary_color' => fake()->hexColor(),
                'secondary_color' => '#0f172a',
            ]);
            $settings->gym_id = $gym->id;
            $settings->save();
        });
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => GymStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }
}
