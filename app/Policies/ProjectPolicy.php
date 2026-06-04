<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Owners and collaborators may view a project. */
    public function view(User $user, Project $project): bool
    {
        return $project->user_id === $user->id
            || $project->members()->whereKey($user->id)->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** Only the owner may edit a project. */
    public function update(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }

    /** Only the owner may delete a project. */
    public function delete(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }
}
