@props(['user', 'size' => 'md'])

@php
    $dimensions = match ($size) {
        'sm' => 'size-6 text-[10px]',
        'lg' => 'size-8 text-xs',
        default => 'size-7 text-xs',
    };
@endphp

<span
    title="{{ $user->name }}"
    {{ $attributes->class(['flex shrink-0 items-center justify-center rounded-full bg-indigo-100 font-medium text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300', $dimensions]) }}
>{{ $user->initials() }}</span>
