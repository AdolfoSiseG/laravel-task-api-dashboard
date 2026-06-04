<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Livewire\Volt\Volt;

it('lets a member post a comment on a task', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $project->members()->attach($user->id);
    $task = Task::factory()->todo()->inProject($project)->create();
    $this->actingAs($user);

    Volt::test('projects.show', ['project' => $project])
        ->call('openTask', $task->id)
        ->set('commentBody', 'Looks good to me')
        ->call('addComment')
        ->assertHasNoErrors();

    expect($task->comments()->where('body', 'Looks good to me')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('requires a body to post a comment', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $project->members()->attach($user->id);
    $task = Task::factory()->todo()->inProject($project)->create();
    $this->actingAs($user);

    Volt::test('projects.show', ['project' => $project])
        ->call('openTask', $task->id)
        ->set('commentBody', '')
        ->call('addComment')
        ->assertHasErrors(['commentBody' => 'required']);
});

it('lets the author and the project owner delete a comment, but not other members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id]);
    $project->members()->attach([$owner->id, $member->id, $other->id]);
    $task = Task::factory()->todo()->inProject($project)->create();
    $comment = TaskComment::factory()->create(['task_id' => $task->id, 'user_id' => $member->id]);

    expect($member->can('delete', $comment))->toBeTrue()
        ->and($owner->can('delete', $comment))->toBeTrue()
        ->and($other->can('delete', $comment))->toBeFalse();
});
