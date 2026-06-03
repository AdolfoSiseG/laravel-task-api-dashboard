<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
        ];
    }

    // ----- Relationships -----

    /** The user who owns the project. */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Everyone with access to the project (the owner is also stored as a member). */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    // ----- Scopes -----

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /** Projects a user can access: owned OR collaborated on. */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhereHas('members', fn (Builder $members) => $members->whereKey($user->id));
        });
    }

    public function scopeStatus(Builder $query, ProjectStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /** Eager-load task counts so progress() never triggers an N+1 query. */
    public function scopeWithProgress(Builder $query): Builder
    {
        return $query->withCount([
            'tasks',
            'tasks as completed_tasks_count' => fn (Builder $query) => $query->where('status', TaskStatus::Done->value),
        ]);
    }

    // ----- Computed -----

    /** Completion percentage (0-100); uses withProgress() counts when they are loaded. */
    public function progress(): int
    {
        $total = $this->attributes['tasks_count'] ?? $this->tasks()->count();

        if ($total === 0) {
            return 0;
        }

        $completed = $this->attributes['completed_tasks_count']
            ?? $this->tasks()->where('status', TaskStatus::Done->value)->count();

        return (int) round($completed / $total * 100);
    }
}
