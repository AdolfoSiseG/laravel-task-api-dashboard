<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum TaskStatus: string
{
    use EnumHelpers;

    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To Do',
            self::InProgress => 'In Progress',
            self::Done => 'Done',
        };
    }

    /** Flux/Tailwind color token used for status badges and board columns. */
    public function color(): string
    {
        return match ($this) {
            self::Todo => 'zinc',
            self::InProgress => 'amber',
            self::Done => 'emerald',
        };
    }
}
