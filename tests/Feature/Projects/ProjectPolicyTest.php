<?php

use App\Models\Project;
use App\Models\User;

it('lets owners and members view, but only owners manage, a project', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $stranger = User::factory()->create();

    $project = Project::factory()->create(['user_id' => $owner->id]);
    $project->members()->attach([$owner->id, $member->id]);

    expect($owner->can('view', $project))->toBeTrue()
        ->and($member->can('view', $project))->toBeTrue()
        ->and($stranger->can('view', $project))->toBeFalse();

    expect($owner->can('update', $project))->toBeTrue()
        ->and($member->can('update', $project))->toBeFalse()
        ->and($stranger->can('update', $project))->toBeFalse();

    expect($owner->can('delete', $project))->toBeTrue()
        ->and($member->can('delete', $project))->toBeFalse();
});
