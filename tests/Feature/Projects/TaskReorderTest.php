<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Livewire\Volt\Volt;

it('moves a task to another column and positions it via drag-and-drop', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $project->members()->attach($user->id);

    Task::factory()->inProgress()->inProject($project)->create(['position' => 0]);
    Task::factory()->inProgress()->inProject($project)->create(['position' => 1]);
    $dragged = Task::factory()->todo()->inProject($project)->create();

    $this->actingAs($user);

    // Drop the To Do task at the top (index 0) of In Progress.
    Volt::test('projects.show', ['project' => $project])
        ->call('reorderTask', TaskStatus::InProgress->value, $dragged->id, 0);

    expect($dragged->refresh()->status)->toBe(TaskStatus::InProgress)
        ->and($dragged->position)->toBe(0);

    $positions = Task::query()
        ->where('project_id', $project->id)
        ->where('status', TaskStatus::InProgress->value)
        ->orderBy('position')
        ->pluck('position')
        ->all();

    expect($positions)->toBe([0, 1, 2]);
});

it('reorders tasks within the same column', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $project->members()->attach($user->id);

    $a = Task::factory()->todo()->inProject($project)->create(['position' => 0]);
    $b = Task::factory()->todo()->inProject($project)->create(['position' => 1]);
    $c = Task::factory()->todo()->inProject($project)->create(['position' => 2]);

    $this->actingAs($user);

    // Drag $c to the top of the To Do column.
    Volt::test('projects.show', ['project' => $project])
        ->call('reorderTask', TaskStatus::Todo->value, $c->id, 0);

    expect($c->refresh()->position)->toBe(0)
        ->and($a->refresh()->position)->toBe(1)
        ->and($b->refresh()->position)->toBe(2);
});
