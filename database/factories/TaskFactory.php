<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(TaskStatus::cases());

        return [
            'project_id' => Project::factory(),
            'created_by' => User::factory(),
            'assigned_to' => null,
            'title' => rtrim(fake()->sentence(fake()->numberBetween(3, 6)), '.'),
            'description' => fake()->optional()->paragraph(),
            'status' => $status,
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'due_date' => fake()->optional(0.7)->dateTimeBetween('-1 week', '+3 weeks'),
            'completed_at' => $status === TaskStatus::Done
                ? fake()->dateTimeBetween('-1 week', 'now')
                : null,
        ];
    }

    public function todo(): static
    {
        return $this->state(['status' => TaskStatus::Todo, 'completed_at' => null]);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => TaskStatus::InProgress, 'completed_at' => null]);
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status' => TaskStatus::Done,
            'completed_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /** A task past its due date and not yet finished. */
    public function overdue(): static
    {
        return $this->state(fn () => [
            'due_date' => fake()->dateTimeBetween('-3 weeks', '-2 days'),
            'status' => fake()->randomElement([TaskStatus::Todo, TaskStatus::InProgress]),
            'completed_at' => null,
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(['priority' => TaskPriority::High]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(['assigned_to' => $user->id]);
    }

    /** Place the task inside an existing project, authored by that project's owner. */
    public function inProject(Project $project): static
    {
        return $this->state([
            'project_id' => $project->id,
            'created_by' => $project->user_id,
        ]);
    }
}
