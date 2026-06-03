<?php

use App\Enums\TaskStatus;
use App\Models\Task;

it('stamps completed_at when a task becomes done', function () {
    $task = Task::factory()->todo()->create();
    expect($task->completed_at)->toBeNull();

    $task->update(['status' => TaskStatus::Done]);

    expect($task->fresh()->completed_at)->not->toBeNull();
});

it('clears completed_at when a task leaves the done status', function () {
    $task = Task::factory()->done()->create();
    expect($task->completed_at)->not->toBeNull();

    $task->update(['status' => TaskStatus::InProgress]);

    expect($task->fresh()->completed_at)->toBeNull();
});

it('scopes only past-due, unfinished tasks as overdue', function () {
    $overdue = Task::factory()->overdue()->create();
    $upcoming = Task::factory()->todo()->create(['due_date' => now()->addWeek()]);
    $finished = Task::factory()->done()->create(['due_date' => now()->subWeek()]);

    $overdueIds = Task::overdue()->pluck('id')->all();

    expect($overdueIds)
        ->toContain($overdue->id)
        ->not->toContain($upcoming->id)
        ->not->toContain($finished->id);
});
