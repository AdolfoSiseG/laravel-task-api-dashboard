<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $this->hasProjectAccess($user, $task->project_id);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->hasProjectAccess($user, $task->project_id);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->hasProjectAccess($user, $task->project_id);
    }

    /** A user may act on a task when they own or collaborate on its project. */
    private function hasProjectAccess(User $user, int $projectId): bool
    {
        return Project::query()->whereKey($projectId)->accessibleBy($user)->exists();
    }
}
