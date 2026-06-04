<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\TaskComment;
use App\Models\User;

class TaskCommentPolicy
{
    /** The author may delete their own comment; the project owner may moderate any. */
    public function delete(User $user, TaskComment $comment): bool
    {
        if ($comment->user_id === $user->id) {
            return true;
        }

        return Project::query()
            ->whereHas('tasks', fn ($query) => $query->whereKey($comment->task_id))
            ->where('user_id', $user->id)
            ->exists();
    }
}
