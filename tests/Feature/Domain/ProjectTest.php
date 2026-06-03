<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

it('computes completion progress from its tasks', function () {
    $project = Project::factory()->create();
    Task::factory()->count(3)->todo()->inProject($project)->create();
    Task::factory()->count(1)->done()->inProject($project)->create();

    expect($project->progress())->toBe(25);
});

it('reports zero progress when it has no tasks', function () {
    expect(Project::factory()->create()->progress())->toBe(0);
});

it('computes progress from eager-loaded counts via withProgress', function () {
    $project = Project::factory()->create();
    Task::factory()->count(2)->done()->inProject($project)->create();
    Task::factory()->count(2)->todo()->inProject($project)->create();

    $loaded = Project::query()->withProgress()->findOrFail($project->id);

    expect($loaded->progress())->toBe(50);
});

it('only exposes projects the user can access via the accessibleBy scope', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();
    $stranger = User::factory()->create();

    $project = Project::factory()->create(['user_id' => $owner->id]);
    $project->members()->sync([$owner->id, $collaborator->id]);

    expect(Project::query()->accessibleBy($owner)->pluck('id')->all())->toContain($project->id);
    expect(Project::query()->accessibleBy($collaborator)->pluck('id')->all())->toContain($project->id);
    expect(Project::query()->accessibleBy($stranger)->pluck('id')->all())->not->toContain($project->id);
});
