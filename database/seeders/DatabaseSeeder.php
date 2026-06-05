<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: only seed a fresh database, so this is safe to run on every boot/deploy.
        if (User::query()->where('email', 'demo@example.com')->exists()) {
            return;
        }

        // Primary demo account, surfaced on the login screen ("Log in as demo").
        $demo = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => Hash::make('password'),
        ]);

        // A small team so assignments and the "tasks by user" analytics are meaningful.
        $team = User::factory()
            ->count(4)
            ->sequence(
                ['name' => 'Alice Johnson', 'email' => 'alice@example.com'],
                ['name' => 'Bob Martinez', 'email' => 'bob@example.com'],
                ['name' => 'Carla Nguyen', 'email' => 'carla@example.com'],
                ['name' => 'David Smith', 'email' => 'david@example.com'],
            )
            ->create();

        $blueprints = [
            ['name' => 'Website Redesign', 'description' => 'Revamp the marketing site with a fresh design system and a headless CMS.', 'status' => ProjectStatus::Active, 'members' => 3],
            ['name' => 'Mobile App Launch', 'description' => 'Ship the v1 iOS and Android apps and reach the public stores.', 'status' => ProjectStatus::Active, 'members' => 4],
            ['name' => 'Q3 Marketing Campaign', 'description' => 'Plan and run the third-quarter growth campaign across channels.', 'status' => ProjectStatus::Active, 'members' => 2],
            ['name' => 'API Platform v2', 'description' => 'Design and deliver the next-generation public REST API.', 'status' => ProjectStatus::Completed, 'members' => 3],
            ['name' => 'Internal Tooling', 'description' => 'Automate repetitive operations with small internal dashboards.', 'status' => ProjectStatus::Archived, 'members' => 1],
        ];

        foreach ($blueprints as $data) {
            $project = Project::factory()->create([
                'user_id' => $demo->id,
                'name' => $data['name'],
                'description' => $data['description'],
                'status' => $data['status'],
            ]);

            // Members = owner + a random slice of the team.
            $members = collect([$demo])
                ->concat($team->shuffle()->take($data['members']))
                ->unique('id')
                ->values();

            $project->members()->sync($members->pluck('id'));

            $this->seedTasks($project, $members);
        }
    }

    /**
     * Seed a realistic spread of tasks (and a few comments) for a project.
     *
     * @param  Collection<int, User>  $members
     */
    private function seedTasks(Project $project, Collection $members): void
    {
        $assignee = fn (): int => $members->random()->id;

        Task::factory()->count(fake()->numberBetween(3, 6))->done()
            ->inProject($project)
            ->state(fn () => ['assigned_to' => $assignee()])
            ->create();

        Task::factory()->count(fake()->numberBetween(2, 4))->inProgress()
            ->inProject($project)
            ->state(fn () => ['assigned_to' => $assignee()])
            ->create();

        Task::factory()->count(fake()->numberBetween(3, 6))->todo()
            ->inProject($project)
            ->state(fn () => ['assigned_to' => fake()->boolean(70) ? $assignee() : null])
            ->create();

        Task::factory()->count(2)->overdue()->highPriority()
            ->inProject($project)
            ->state(fn () => ['assigned_to' => $assignee()])
            ->create();

        $project->tasks()->inRandomOrder()->take(5)->get()->each(function (Task $task) use ($members): void {
            TaskComment::factory()
                ->count(fake()->numberBetween(1, 3))
                ->state(fn () => [
                    'task_id' => $task->id,
                    'user_id' => $members->random()->id,
                ])
                ->create();
        });
    }
}
