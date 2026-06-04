<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    public Project $project;

    public bool $showTaskForm = false;

    public ?int $editingTaskId = null;

    public string $taskTitle = '';

    public string $taskDescription = '';

    public string $taskPriority = '';

    public ?int $taskAssignee = null;

    public string $taskDueDate = '';

    public string $taskColumnStatus = '';

    public bool $showTaskDetail = false;

    public ?int $viewingTaskId = null;

    public string $commentBody = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
        $this->taskPriority = TaskPriority::Medium->value;
        $this->taskColumnStatus = TaskStatus::Todo->value;
    }

    public function addTask(string $status): void
    {
        $this->authorize('view', $this->project);
        $this->reset('editingTaskId', 'taskTitle', 'taskDescription', 'taskAssignee', 'taskDueDate');
        $this->taskPriority = TaskPriority::Medium->value;
        $this->taskColumnStatus = TaskStatus::from($status)->value;
        $this->resetValidation();
        $this->showTaskForm = true;
    }

    public function editTask(int $taskId): void
    {
        $task = $this->findProjectTask($taskId);
        $this->authorize('update', $task);

        $this->editingTaskId = $task->id;
        $this->taskTitle = $task->title;
        $this->taskDescription = (string) $task->description;
        $this->taskPriority = $task->priority->value;
        $this->taskAssignee = $task->assigned_to;
        $this->taskDueDate = $task->due_date?->format('Y-m-d') ?? '';
        $this->taskColumnStatus = $task->status->value;
        $this->resetValidation();
        $this->showTaskForm = true;
    }

    public function saveTask(): void
    {
        $memberIds = $this->project->members()->pluck('users.id')->all();

        $validated = $this->validate([
            'taskTitle' => ['required', 'string', 'max:160'],
            'taskDescription' => ['nullable', 'string', 'max:2000'],
            'taskPriority' => ['required', Rule::enum(TaskPriority::class)],
            'taskAssignee' => ['nullable', Rule::in($memberIds)],
            'taskDueDate' => ['nullable', 'date'],
            'taskColumnStatus' => ['required', Rule::enum(TaskStatus::class)],
        ]);

        $attributes = [
            'title' => $validated['taskTitle'],
            'description' => $validated['taskDescription'] ?: null,
            'priority' => $validated['taskPriority'],
            'assigned_to' => $validated['taskAssignee'] ?: null,
            'due_date' => $validated['taskDueDate'] ?: null,
            'status' => $validated['taskColumnStatus'],
        ];

        if ($this->editingTaskId !== null) {
            $task = $this->findProjectTask($this->editingTaskId);
            $this->authorize('update', $task);
            $task->update($attributes);
        } else {
            $this->authorize('view', $this->project);

            // created_by and project_id are set server-side, never mass-assigned.
            $task = new Task($attributes);
            $task->created_by = Auth::id();
            $this->project->tasks()->save($task);
        }

        $this->showTaskForm = false;
        $this->reset('editingTaskId', 'taskTitle', 'taskDescription', 'taskAssignee', 'taskDueDate');
    }

    public function moveTask(int $taskId, string $status): void
    {
        $task = $this->findProjectTask($taskId);
        $this->authorize('update', $task);
        $task->update(['status' => TaskStatus::from($status)]);
    }

    /** Persist a drag-and-drop move: drop $taskId into $status at index $position. */
    public function reorderTask(string $status, int $taskId, int $position): void
    {
        $statusEnum = TaskStatus::from($status);
        $task = $this->findProjectTask($taskId);
        $this->authorize('update', $task);

        // Move into the target column (the saving hook keeps completed_at in sync)...
        $task->update(['status' => $statusEnum]);

        // ...then renumber that column so positions stay contiguous.
        $ids = $this->project->tasks()
            ->where('status', $statusEnum->value)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $ids = array_values(array_filter($ids, fn (int $id): bool => $id !== $taskId));
        $position = max(0, min($position, count($ids)));
        array_splice($ids, $position, 0, [$taskId]);

        foreach ($ids as $index => $id) {
            Task::whereKey($id)->update(['position' => $index]);
        }
    }

    public function deleteTask(int $taskId): void
    {
        $task = $this->findProjectTask($taskId);
        $this->authorize('delete', $task);
        $task->delete();
    }

    public function openTask(int $taskId): void
    {
        $task = $this->findProjectTask($taskId);
        $this->authorize('view', $task);

        $this->viewingTaskId = $task->id;
        $this->commentBody = '';
        $this->resetValidation();
        $this->showTaskDetail = true;
    }

    public function updatedShowTaskDetail(bool $value): void
    {
        if (! $value) {
            $this->viewingTaskId = null;
            $this->commentBody = '';
            $this->resetValidation();
        }
    }

    public function addComment(): void
    {
        abort_unless($this->viewingTaskId !== null, 404);

        $task = $this->findProjectTask($this->viewingTaskId);
        $this->authorize('view', $task);

        $validated = $this->validate([
            'commentBody' => ['required', 'string', 'max:2000'],
        ]);

        $comment = new TaskComment(['body' => $validated['commentBody']]);
        $comment->user_id = Auth::id();
        $task->comments()->save($comment);

        $this->commentBody = '';
    }

    public function deleteComment(int $commentId): void
    {
        $comment = TaskComment::query()
            ->whereHas('task', fn ($query) => $query->where('project_id', $this->project->id))
            ->findOrFail($commentId);

        $this->authorize('delete', $comment);
        $comment->delete();
    }

    private function findProjectTask(int $taskId): Task
    {
        return $this->project->tasks()->findOrFail($taskId);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $this->project->loadMissing('owner:id,name', 'members:id,name')->loadCount([
            'tasks',
            'tasks as completed_tasks_count' => fn ($query) => $query->where('status', TaskStatus::Done->value),
        ]);

        $grouped = $this->project->tasks()
            ->with('assignee:id,name')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Task $task) => $task->status->value);

        return [
            'columns' => collect(TaskStatus::cases())->map(fn (TaskStatus $status) => [
                'status' => $status,
                'tasks' => $grouped->get($status->value, collect()),
            ]),
            'members' => $this->project->members,
            'statuses' => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
            'viewingTask' => $this->viewingTaskId === null ? null : $this->project->tasks()
                ->with([
                    'assignee:id,name',
                    'creator:id,name',
                    'comments' => fn ($query) => $query->with('author:id,name')->orderBy('created_at'),
                ])
                ->find($this->viewingTaskId),
        ];
    }
}; ?>

<div class="space-y-6">
    {{-- Header --}}
    <div>
        <a href="{{ route('projects') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
            <flux:icon.chevron-left variant="micro" /> Projects
        </a>

        <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-3">
                    <flux:heading size="xl">{{ $project->name }}</flux:heading>
                    <flux:badge :color="$project->status->color()">{{ $project->status->label() }}</flux:badge>
                </div>
                @if ($project->description)
                    <p class="mt-1 max-w-2xl text-sm text-zinc-500 dark:text-zinc-400">{{ $project->description }}</p>
                @endif
            </div>

            <div class="flex items-center gap-5">
                <div class="text-right">
                    <div class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $project->progress() }}%</div>
                    <div class="text-xs text-zinc-400">complete</div>
                </div>
                <div class="flex -space-x-2">
                    @foreach ($members->take(5) as $member)
                        <x-user-avatar :user="$member" size="lg" class="border-2 border-white dark:border-zinc-800" />
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Board --}}
    <div class="grid gap-4 md:grid-cols-3">
        @foreach ($columns as $column)
            @php($status = $column['status'])
            <div wire:key="column-{{ $status->value }}" class="flex flex-col rounded-xl bg-zinc-50 p-3 dark:bg-zinc-900/40">
                <div class="mb-3 flex items-center justify-between px-1">
                    <div class="flex items-center gap-2">
                        <flux:badge :color="$status->color()" size="sm">{{ $status->label() }}</flux:badge>
                        <span class="text-sm text-zinc-400">{{ $column['tasks']->count() }}</span>
                    </div>
                    <flux:button size="xs" variant="ghost" icon="plus" aria-label="Add task" wire:click="addTask('{{ $status->value }}')" />
                </div>

                <div
                    x-sort:group="board"
                    x-sort="$wire.reorderTask('{{ $status->value }}', $item, $position)"
                    x-sort:config="{ animation: 150, ghostClass: 'opacity-40' }"
                    class="flex min-h-12 flex-1 flex-col gap-2"
                >
                    @forelse ($column['tasks'] as $task)
                        <div x-sort:item="{{ $task->id }}" wire:key="task-{{ $task->id }}" class="cursor-grab rounded-lg border border-zinc-200 bg-white p-3 shadow-sm active:cursor-grabbing dark:border-zinc-700 dark:bg-zinc-800">
                            <div class="flex items-start justify-between gap-2">
                                <button type="button" wire:click="openTask({{ $task->id }})" class="text-left text-sm font-medium text-zinc-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400">{{ $task->title }}</button>
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" aria-label="Task actions" />
                                    <flux:menu>
                                        @foreach ($statuses as $s)
                                            @if ($s->value !== $task->status->value)
                                                <flux:menu.item wire:key="move-{{ $task->id }}-{{ $s->value }}" wire:click="moveTask({{ $task->id }}, '{{ $s->value }}')">Move to {{ $s->label() }}</flux:menu.item>
                                            @endif
                                        @endforeach
                                        <flux:menu.separator />
                                        <flux:menu.item icon="pencil-square" wire:click="editTask({{ $task->id }})">Edit</flux:menu.item>
                                        <flux:menu.item icon="trash" variant="danger" wire:click="deleteTask({{ $task->id }})" wire:confirm="Delete this task?">Delete</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>

                            @if ($task->description)
                                <p class="mt-1 line-clamp-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $task->description }}</p>
                            @endif

                            <div class="mt-3 flex items-center justify-between">
                                <flux:badge :color="$task->priority->color()" size="sm">{{ $task->priority->label() }}</flux:badge>
                                <div class="flex items-center gap-2">
                                    @if ($task->due_date)
                                        <span @class([
                                            'text-xs',
                                            'font-medium text-rose-600 dark:text-rose-400' => $task->isOverdue(),
                                            'text-zinc-400' => ! $task->isOverdue(),
                                        ])>{{ $task->due_date->isoFormat('MMM D') }}</span>
                                    @endif
                                    @if ($task->assignee)
                                        <x-user-avatar :user="$task->assignee" size="sm" />
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <button type="button" wire:click="addTask('{{ $status->value }}')" class="rounded-lg border border-dashed border-zinc-300 px-3 py-6 text-center text-xs text-zinc-400 transition hover:border-indigo-400 hover:text-indigo-500 dark:border-zinc-700">
                            + Add a task
                        </button>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    {{-- Task modal --}}
    <x-modal wire:model="showTaskForm" :title="$editingTaskId ? 'Edit task' : 'New task'">
        <form wire:submit="saveTask" class="space-y-4">
            <flux:input wire:model="taskTitle" label="Title" placeholder="What needs to be done?" />
            <flux:textarea wire:model="taskDescription" label="Description" rows="2" />

            <div class="grid grid-cols-2 gap-3">
                <flux:select wire:model="taskPriority" label="Priority">
                    @foreach ($priorities as $p)
                        <flux:select.option value="{{ $p->value }}">{{ $p->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="taskColumnStatus" label="Status">
                    @foreach ($statuses as $s)
                        <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <flux:select wire:model="taskAssignee" label="Assignee" placeholder="Unassigned">
                    <flux:select.option value="">Unassigned</flux:select.option>
                    @foreach ($members as $member)
                        <flux:select.option value="{{ $member->id }}">{{ $member->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input
                    type="date"
                    wire:model="taskDueDate"
                    label="Due date"
                    :min="$editingTaskId ? null : now()->toDateString()"
                />
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button type="button" variant="ghost" x-on:click="open = false">Cancel</flux:button>
                <flux:button type="submit" variant="primary" wire:target="saveTask" wire:loading.attr="disabled">{{ $editingTaskId ? 'Save changes' : 'Add task' }}</flux:button>
            </div>
        </form>
    </x-modal>

    {{-- Task detail + comments --}}
    <x-modal wire:model="showTaskDetail" size="max-w-2xl">
        @if ($viewingTask)
            <div class="space-y-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <flux:heading size="lg">{{ $viewingTask->title }}</flux:heading>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <flux:badge :color="$viewingTask->status->color()" size="sm">{{ $viewingTask->status->label() }}</flux:badge>
                            <flux:badge :color="$viewingTask->priority->color()" size="sm">{{ $viewingTask->priority->label() }}</flux:badge>
                            @if ($viewingTask->due_date)
                                <span @class([
                                    'text-xs',
                                    'font-medium text-rose-600 dark:text-rose-400' => $viewingTask->isOverdue(),
                                    'text-zinc-500 dark:text-zinc-400' => ! $viewingTask->isOverdue(),
                                ])>Due {{ $viewingTask->due_date->isoFormat('MMM D, YYYY') }}</span>
                            @endif
                        </div>
                    </div>
                    <flux:button variant="ghost" size="sm" icon="x-mark" aria-label="Close" x-on:click="open = false" />
                </div>

                @if ($viewingTask->description)
                    <p class="text-sm whitespace-pre-line text-zinc-600 dark:text-zinc-300">{{ $viewingTask->description }}</p>
                @endif

                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-zinc-500 dark:text-zinc-400">
                    <div class="flex items-center gap-2">
                        <span>Assignee:</span>
                        @if ($viewingTask->assignee)
                            <span class="inline-flex items-center gap-1.5"><x-user-avatar :user="$viewingTask->assignee" size="sm" /> {{ $viewingTask->assignee->name }}</span>
                        @else
                            <span>Unassigned</span>
                        @endif
                    </div>
                    <div>Created by {{ $viewingTask->creator->name }}</div>
                </div>

                <flux:separator />

                {{-- Comments thread --}}
                <div>
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-white">Comments ({{ $viewingTask->comments->count() }})</h4>

                    <div class="mt-3 max-h-64 space-y-4 overflow-y-auto pr-1">
                        @forelse ($viewingTask->comments as $comment)
                            <div wire:key="comment-{{ $comment->id }}" class="flex gap-3">
                                <x-user-avatar :user="$comment->author" size="md" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="text-sm">
                                            <span class="font-medium text-zinc-900 dark:text-white">{{ $comment->author->name }}</span>
                                            <span class="ml-1 text-xs text-zinc-400">{{ $comment->created_at->diffForHumans() }}</span>
                                        </div>
                                        @can('delete', $comment)
                                            <button type="button" wire:click="deleteComment({{ $comment->id }})" wire:confirm="Delete this comment?" class="text-xs text-zinc-400 hover:text-rose-500">Delete</button>
                                        @endcan
                                    </div>
                                    <p class="mt-0.5 text-sm whitespace-pre-line text-zinc-600 dark:text-zinc-300">{{ $comment->body }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">No comments yet. Start the conversation.</p>
                        @endforelse
                    </div>

                    <form wire:submit="addComment" class="mt-4 flex items-start gap-2">
                        <flux:textarea wire:model="commentBody" rows="2" placeholder="Write a comment..." class="flex-1" />
                        <flux:button type="submit" variant="primary" wire:target="addComment" wire:loading.attr="disabled">Post</flux:button>
                    </form>
                </div>
            </div>
        @endif
    </x-modal>
</div>
