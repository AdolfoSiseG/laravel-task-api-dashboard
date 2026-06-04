<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int $created_by
 * @property int|null $assigned_to
 * @property string $title
 * @property string|null $description
 * @property TaskStatus $status
 * @property TaskPriority $priority
 * @property int $position
 * @property Carbon|null $due_date
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    // project_id and created_by are intentionally NOT mass-assignable: they are set
    // via the project relationship and explicitly from the authenticated user, so no
    // request payload can move a task between projects or spoof its author.
    protected $fillable = [
        'title',
        'description',
        'assigned_to',
        'status',
        'priority',
        'due_date',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Invariant: completed_at follows the Done status automatically, so the
        // timestamp can never drift out of sync with the task's state.
        static::saving(function (Task $task): void {
            if ($task->status === TaskStatus::Done && $task->completed_at === null) {
                $task->completed_at = now();
            }

            if ($task->status !== TaskStatus::Done && $task->completed_at !== null) {
                $task->completed_at = null;
            }
        });
    }

    // ----- Relationships -----

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<TaskComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    // ----- Scopes -----

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeStatus(Builder $query, TaskStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopePriority(Builder $query, TaskPriority $priority): Builder
    {
        return $query->where('priority', $priority->value);
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->where('status', '!=', TaskStatus::Done->value);
    }

    /**
     * Tasks inside any project the user owns or collaborates on.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $query->whereHas('project', function (Builder $project) use ($user) {
            $project->where('user_id', $user->id)
                ->orWhereHas('members', fn (Builder $members) => $members->whereKey($user->id));
        });
    }

    // ----- Computed -----

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->status !== TaskStatus::Done
            && $this->due_date->lt(today());
    }
}
