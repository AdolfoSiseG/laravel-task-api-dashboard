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
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property ProjectStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read int|null $tasks_count
 * @property-read int|null $completed_tasks_count
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
        ];
    }

    // ----- Relationships -----

    /**
     * The user who owns the project.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Everyone with access to the project (the owner is also stored as a member).
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    // ----- Scopes -----

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Projects a user can access: owned OR collaborated on.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhereHas('members', fn (Builder $members) => $members->whereKey($user->id));
        });
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeStatus(Builder $query, ProjectStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * Eager-load task counts so progress() never triggers an N+1 query.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeWithProgress(Builder $query): Builder
    {
        return $query->withCount([
            'tasks',
            'tasks as completed_tasks_count' => fn (Builder $query) => $query->where('status', TaskStatus::Done->value),
        ]);
    }

    // ----- Computed -----

    /**
     * Completion percentage (0-100); uses withProgress() counts when they are loaded.
     */
    public function progress(): int
    {
        $total = (int) ($this->tasks_count ?? $this->tasks()->count());

        if ($total === 0) {
            return 0;
        }

        $completed = (int) ($this->completed_tasks_count
            ?? $this->tasks()->where('status', TaskStatus::Done->value)->count());

        return (int) round($completed / $total * 100);
    }
}
