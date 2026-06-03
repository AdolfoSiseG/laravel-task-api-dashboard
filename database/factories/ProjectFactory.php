<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->company(),
            'description' => fake()->optional()->sentence(12),
            'status' => fake()->randomElement(ProjectStatus::cases()),
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => ProjectStatus::Active]);
    }

    public function completed(): static
    {
        return $this->state(['status' => ProjectStatus::Completed]);
    }

    public function archived(): static
    {
        return $this->state(['status' => ProjectStatus::Archived]);
    }

    /** Assign a specific owner. */
    public function ownedBy(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }
}
