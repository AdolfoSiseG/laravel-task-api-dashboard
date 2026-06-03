<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

it('renders the dashboard with live project and task data', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'name' => 'Acme Website Redesign',
    ]);
    $project->members()->sync([$user->id]);

    Task::factory()->count(2)->todo()->inProject($project)->create();
    Task::factory()->done()->inProject($project)->create();
    Task::factory()->todo()->inProject($project)->create(['due_date' => now()->addDays(3)]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Acme Website Redesign')
        ->assertSee('Task breakdown')
        ->assertSee('Upcoming deadlines');
});
