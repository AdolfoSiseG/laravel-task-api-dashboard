<?php

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Livewire\Volt\Volt;

it('shows only projects the user owns or collaborates on', function () {
    $user = User::factory()->create();

    Project::factory()->create(['user_id' => $user->id, 'name' => 'Owned Project']);
    $shared = Project::factory()->create(['name' => 'Shared Project']);
    $shared->members()->attach($user->id);
    Project::factory()->create(['name' => 'Hidden Project']);

    $this->actingAs($user)
        ->get(route('projects'))
        ->assertOk()
        ->assertSee('Owned Project')
        ->assertSee('Shared Project')
        ->assertDontSee('Hidden Project');
});

it('creates a project and enrols the creator as a member', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('projects.index')
        ->set('name', 'Launch Plan')
        ->set('description', 'Ship v1')
        ->set('projectStatus', ProjectStatus::Active->value)
        ->call('save')
        ->assertHasNoErrors();

    $project = Project::firstWhere('name', 'Launch Plan');

    expect($project)->not->toBeNull()
        ->and($project->user_id)->toBe($user->id)
        ->and($project->members()->whereKey($user->id)->exists())->toBeTrue();
});

it('validates that a project name is required', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('projects.index')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

it('forbids a non-owner from editing a project', function () {
    $member = User::factory()->create();
    $project = Project::factory()->create(['name' => 'Not Mine']);
    $project->members()->attach($member->id);

    $this->actingAs($member);

    Volt::test('projects.index')
        ->call('editProject', $project->id)
        ->assertForbidden();
});
