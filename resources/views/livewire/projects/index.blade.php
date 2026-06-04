<?php

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] #[Title('Projects')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $projectStatus = '';

    public function mount(): void
    {
        $this->projectStatus = ProjectStatus::Active->value;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function newProject(): void
    {
        $this->authorize('create', Project::class);
        $this->reset('editingId', 'name', 'description');
        $this->projectStatus = ProjectStatus::Active->value;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function editProject(int $id): void
    {
        $project = Project::findOrFail($id);
        $this->authorize('update', $project);

        $this->editingId = $project->id;
        $this->name = $project->name;
        $this->description = (string) $project->description;
        $this->projectStatus = $project->status->value;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'projectStatus' => ['required', Rule::enum(ProjectStatus::class)],
        ]);

        if ($this->editingId !== null) {
            $project = Project::findOrFail($this->editingId);
            $this->authorize('update', $project);

            $project->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?: null,
                'status' => $validated['projectStatus'],
            ]);
        } else {
            $this->authorize('create', Project::class);

            $project = $user->projects()->create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?: null,
                'status' => $validated['projectStatus'],
            ]);

            $project->members()->attach($user->id);
        }

        $this->showForm = false;
        $this->reset('editingId', 'name', 'description');
    }

    public function deleteProject(int $id): void
    {
        $project = Project::findOrFail($id);
        $this->authorize('delete', $project);

        $project->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        return [
            'projects' => Project::query()
                ->accessibleBy($user)
                ->withProgress()
                ->withCount('members')
                ->with(['owner:id,name', 'members:id,name'])
                ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
                ->latest('updated_at')
                ->paginate(9),
            'statuses' => ProjectStatus::cases(),
        ];
    }
}; ?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Projects</flux:heading>
            <flux:subheading>Plan, track and collaborate on your work.</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="newProject">New project</flux:button>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input
            class="sm:max-w-xs"
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="Search projects..."
            clearable
        />
        <flux:select class="sm:max-w-[12rem]" wire:model.live="status" placeholder="All statuses">
            <flux:select.option value="">All statuses</flux:select.option>
            @foreach ($statuses as $case)
                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Grid --}}
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($projects as $project)
            <div wire:key="project-{{ $project->id }}"
                 class="flex flex-col rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <a href="{{ route('projects.show', $project) }}" wire:navigate class="truncate font-semibold text-zinc-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400">{{ $project->name }}</a>
                        <flux:badge :color="$project->status->color()" size="sm" class="mt-1">{{ $project->status->label() }}</flux:badge>
                    </div>
                    <flux:dropdown position="bottom" align="end">
                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" aria-label="Project actions" />
                        <flux:menu>
                            <flux:menu.item icon="pencil-square" wire:click="editProject({{ $project->id }})">Edit</flux:menu.item>
                            <flux:menu.separator />
                            <flux:menu.item
                                icon="trash"
                                variant="danger"
                                wire:click="deleteProject({{ $project->id }})"
                                wire:confirm="Delete this project and all of its tasks? This cannot be undone."
                            >Delete</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>

                <p class="mt-3 line-clamp-2 min-h-[2.5rem] text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $project->description ?: 'No description.' }}
                </p>

                <div class="mt-4">
                    <div class="mb-1.5 flex items-center justify-between text-xs">
                        <span class="text-zinc-500 dark:text-zinc-400">Progress</span>
                        <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $project->progress() }}%</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div class="h-full rounded-full bg-indigo-500 transition-all" style="width: {{ $project->progress() }}%"></div>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between border-t border-zinc-100 pt-4 dark:border-zinc-800">
                    <div class="flex -space-x-2">
                        @foreach ($project->members->take(4) as $member)
                            <span
                                title="{{ $member->name }}"
                                class="flex size-7 items-center justify-center rounded-full border-2 border-white bg-indigo-100 text-xs font-medium text-indigo-700 dark:border-zinc-900 dark:bg-indigo-500/20 dark:text-indigo-300"
                            >{{ $member->initials() }}</span>
                        @endforeach
                        @if ($project->members_count > 4)
                            <span class="flex size-7 items-center justify-center rounded-full border-2 border-white bg-zinc-100 text-xs font-medium text-zinc-600 dark:border-zinc-900 dark:bg-zinc-700 dark:text-zinc-300">
                                +{{ $project->members_count - 4 }}
                            </span>
                        @endif
                    </div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $project->tasks_count }} {{ \Illuminate\Support\Str::plural('task', $project->tasks_count) }}
                    </span>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-700">
                <flux:icon.folder-open class="mx-auto text-zinc-400" />
                <p class="mt-3 font-medium text-zinc-900 dark:text-white">No projects found</p>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $search !== '' || $status !== '' ? 'Try adjusting your filters.' : 'Create your first project to get started.' }}
                </p>
            </div>
        @endforelse
    </div>

    <div>
        {{ $projects->links() }}
    </div>

    {{-- Create / edit modal --}}
    <div
        x-data="{ open: @entangle('showForm') }"
        x-show="open"
        x-cloak
        @keydown.escape.window="open = false"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
    >
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-zinc-900/50 backdrop-blur-sm" @click="open = false"></div>

        <div
            x-show="open"
            x-transition
            class="relative w-full max-w-lg rounded-xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
        >
            <flux:heading size="lg">{{ $editingId ? 'Edit project' : 'New project' }}</flux:heading>

            <form wire:submit="save" class="mt-4 space-y-4">
                <flux:input wire:model="name" label="Name" placeholder="e.g. Website Redesign" />
                <flux:textarea wire:model="description" label="Description" rows="3" placeholder="What is this project about?" />
                <flux:select wire:model="projectStatus" label="Status">
                    @foreach ($statuses as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex justify-end gap-2 pt-2">
                    <flux:button type="button" variant="ghost" @click="open = false">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Create project' }}</flux:button>
                </div>
            </form>
        </div>
    </div>
</div>
