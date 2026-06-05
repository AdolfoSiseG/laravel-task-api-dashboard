<?php

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;

it('exposes a non-empty label and color for every status', function () {
    foreach (TaskStatus::cases() as $status) {
        expect($status->label())->toBeString()->not->toBeEmpty()
            ->and($status->color())->toBeString()->not->toBeEmpty();
    }

    foreach (ProjectStatus::cases() as $status) {
        expect($status->label())->toBeString()->not->toBeEmpty()
            ->and($status->color())->toBeString()->not->toBeEmpty();
    }
});

it('orders task priorities by ascending weight', function () {
    expect(TaskPriority::Low->weight())->toBeLessThan(TaskPriority::Medium->weight())
        ->and(TaskPriority::Medium->weight())->toBeLessThan(TaskPriority::High->weight());
});

it('builds value lists and value => label option maps', function () {
    expect(ProjectStatus::values())->toBe(['active', 'completed', 'archived'])
        ->and(ProjectStatus::options())->toBe([
            'active' => 'Active',
            'completed' => 'Completed',
            'archived' => 'Archived',
        ]);
});
