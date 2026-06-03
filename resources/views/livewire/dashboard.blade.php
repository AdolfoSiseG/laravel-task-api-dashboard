<?php

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Dashboard')] class extends Component
{
    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $projectIds = Project::query()->accessibleBy($user)->pluck('id');

        $statusCounts = Task::query()
            ->whereIn('project_id', $projectIds)
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->toBase()
            ->pluck('total', 'status');

        $todo = (int) ($statusCounts[TaskStatus::Todo->value] ?? 0);
        $inProgress = (int) ($statusCounts[TaskStatus::InProgress->value] ?? 0);
        $done = (int) ($statusCounts[TaskStatus::Done->value] ?? 0);
        $totalTasks = $todo + $inProgress + $done;

        return [
            'activeProjects' => Project::query()->accessibleBy($user)->status(ProjectStatus::Active)->count(),
            'totalTasks' => $totalTasks,
            'todo' => $todo,
            'inProgress' => $inProgress,
            'done' => $done,
            'completionRate' => $totalTasks > 0 ? (int) round($done / $totalTasks * 100) : 0,
            'overdue' => Task::query()->whereIn('project_id', $projectIds)->overdue()->count(),
            'projects' => Project::query()
                ->accessibleBy($user)
                ->withProgress()
                ->withCount('members')
                ->with('owner:id,name')
                ->orderByDesc('updated_at')
                ->take(5)
                ->get(),
            'upcoming' => Task::query()
                ->whereIn('project_id', $projectIds)
                ->whereNotNull('due_date')
                ->where('status', '!=', TaskStatus::Done->value)
                ->with(['project:id,name', 'assignee:id,name'])
                ->orderBy('due_date')
                ->take(5)
                ->get(),
        ];
    }
}; ?>

@use('Illuminate\Support\Str')

<div class="space-y-6">
    <div>
        <flux:heading size="xl">Dashboard</flux:heading>
        <flux:subheading>Welcome back, {{ auth()->user()->name }}.</flux:subheading>
    </div>

    {{-- Stat tiles --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Active projects</span>
                <span class="flex size-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                    <flux:icon.folder variant="mini" />
                </span>
            </div>
            <div class="mt-3 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $activeProjects }}</div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total tasks</span>
                <span class="flex size-9 items-center justify-center rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400">
                    <flux:icon.rectangle-stack variant="mini" />
                </span>
            </div>
            <div class="mt-3 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $totalTasks }}</div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">In progress</span>
                <span class="flex size-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                    <flux:icon.clock variant="mini" />
                </span>
            </div>
            <div class="mt-3 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $inProgress }}</div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Overdue</span>
                <span class="flex size-9 items-center justify-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
                    <flux:icon.exclamation-triangle variant="mini" />
                </span>
            </div>
            <div @class([
                'mt-3 text-3xl font-bold tracking-tight',
                'text-rose-600 dark:text-rose-400' => $overdue > 0,
                'text-zinc-900 dark:text-white' => $overdue === 0,
            ])>{{ $overdue }}</div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Task breakdown --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">Task breakdown</flux:heading>
                <flux:badge color="indigo" size="sm">{{ $completionRate }}% done</flux:badge>
            </div>

            <div class="mt-5 space-y-4">
                @php
                    $rows = [
                        ['label' => 'To Do', 'value' => $todo, 'bar' => 'bg-zinc-400'],
                        ['label' => 'In Progress', 'value' => $inProgress, 'bar' => 'bg-amber-500'],
                        ['label' => 'Done', 'value' => $done, 'bar' => 'bg-emerald-500'],
                    ];
                @endphp
                @foreach ($rows as $row)
                    <div>
                        <div class="mb-1.5 flex items-center justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-300">{{ $row['label'] }}</span>
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $row['value'] }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div class="h-full rounded-full {{ $row['bar'] }}"
                                 style="width: {{ $totalTasks > 0 ? round($row['value'] / $totalTasks * 100) : 0 }}%"></div>
                        </div>
                    </div>
                @endforeach

                @if ($totalTasks === 0)
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">No tasks yet.</p>
                @endif
            </div>
        </div>

        {{-- Projects --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6 lg:col-span-2 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">Projects</flux:heading>

            <div class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($projects as $project)
                    <div class="flex items-center gap-4 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate font-medium text-zinc-900 dark:text-white">{{ $project->name }}</span>
                                <flux:badge :color="$project->status->color()" size="sm">{{ $project->status->label() }}</flux:badge>
                            </div>
                            <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $project->tasks_count }} {{ Str::plural('task', $project->tasks_count) }}
                                · {{ $project->members_count }} {{ Str::plural('member', $project->members_count) }}
                                · owned by {{ $project->owner->name }}
                            </p>
                        </div>
                        <div class="flex w-32 shrink-0 items-center gap-2">
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div class="h-full rounded-full bg-indigo-500" style="width: {{ $project->progress() }}%"></div>
                            </div>
                            <span class="w-9 text-right text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ $project->progress() }}%</span>
                        </div>
                    </div>
                @empty
                    <p class="py-3 text-sm text-zinc-500 dark:text-zinc-400">You don't have any projects yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Upcoming deadlines --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg">Upcoming deadlines</flux:heading>

        <div class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($upcoming as $task)
                <div class="flex items-center gap-4 py-3">
                    <flux:icon.calendar-days variant="mini" class="shrink-0 text-zinc-400" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-zinc-900 dark:text-white">{{ $task->title }}</p>
                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $task->project->name }}</p>
                    </div>
                    <flux:badge :color="$task->priority->color()" size="sm">{{ $task->priority->label() }}</flux:badge>
                    <span @class([
                        'w-28 shrink-0 text-right text-sm',
                        'font-medium text-rose-600 dark:text-rose-400' => $task->isOverdue(),
                        'text-zinc-600 dark:text-zinc-300' => ! $task->isOverdue(),
                    ])>
                        {{ $task->due_date->isoFormat('MMM D') }}
                        @if ($task->isOverdue())
                            <span class="block text-xs">overdue</span>
                        @endif
                    </span>
                </div>
            @empty
                <p class="py-3 text-sm text-zinc-500 dark:text-zinc-400">No upcoming deadlines. Nice and clear.</p>
            @endforelse
        </div>
    </div>
</div>
