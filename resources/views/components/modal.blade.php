@props(['title' => null, 'size' => 'max-w-lg'])

{{--
    Accessible modal dialog: role/aria semantics, focus trap + scroll lock (x-trap,
    provided by the Flux/Alpine focus plugin) and focus return on close. Controlled by a
    Livewire boolean passed via wire:model, e.g. <x-modal wire:model="showForm" title="...">.
--}}
<div
    x-data="{ open: @entangle($attributes->wire('model')) }"
    x-show="open"
    x-cloak
    x-on:keydown.escape.window="open = false"
    role="dialog"
    aria-modal="true"
    @if ($title) aria-label="{{ $title }}" @endif
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    style="display: none;"
>
    <div
        x-show="open"
        x-transition.opacity.duration.200ms
        x-on:click="open = false"
        aria-hidden="true"
        class="absolute inset-0 bg-zinc-900/50 backdrop-blur-sm"
    ></div>

    <div
        x-show="open"
        x-transition
        x-trap.noscroll="open"
        class="relative max-h-[90vh] w-full {{ $size }} overflow-y-auto rounded-xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
    >
        @isset($title)
            <flux:heading size="lg">{{ $title }}</flux:heading>
            <div class="mt-4">{{ $slot }}</div>
        @else
            {{ $slot }}
        @endisset
    </div>
</div>
