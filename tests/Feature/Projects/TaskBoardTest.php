<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Livewire\Volt\Volt;

it('forbids a stranger from opening a project board', function () {
    $project = Project::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('projects.show', $project))
        ->assertForbidden();
});

it('shows the board to a collaborator', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'name' => 'Roadmap']);
    $project->members()->attach($user->id);
    Task::factory()->todo()->inProject($project)->create(['title' => 'Draft the spec']);

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Roadmap')
        ->assertSee('Draft the spec');
});

it('lets a member add a task to the board', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $project->members()->attach($user->id);
    $this->actingAs($user);

    Volt::test('projects.show', ['project' => $project])
        ->call('addTask', TaskStatus::Todo->value)
        ->set('taskTitle', 'Write the README')
        ->call('saveTask')
        ->assertHasNoErrors();

    expect($project->tasks()->where('title', 'Write the README')->exists())->toBeTrue();
});

it('only allows assigning tasks to project members', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $project->members()->attach($user->id);
    $outsider = User::factory()->create();
    $this->actingAs($user);

    Volt::test('projects.show', ['project' => $project])
        ->call('addTask', TaskStatus::Todo->value)
        ->set('taskTitle', 'Secret task')
        ->set('taskAssignee', $outsider->id)
        ->call('saveTask')
        ->assertHasErrors(['taskAssignee']);
});

it('moves a task across columns and stamps completion', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $project->members()->attach($user->id);
    $task = Task::factory()->todo()->inProject($project)->create();
    $this->actingAs($user);

    Volt::test('projects.show', ['project' => $project])
        ->call('moveTask', $task->id, TaskStatus::Done->value);

    $task->refresh();

    expect($task->status)->toBe(TaskStatus::Done)
        ->and($task->completed_at)->not->toBeNull();
});
