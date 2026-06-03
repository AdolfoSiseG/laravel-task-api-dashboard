<?php

namespace App\Enums\Concerns;

/**
 * Shared helpers for backed enums: a flat list of values and a
 * value => label map for <select> options and validation rules.
 */
trait EnumHelpers
{
    /**
     * All backing values of the enum.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * A value => human label map, handy for select inputs and Rule::in().
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $options, self $case): array => $options + [$case->value => $case->label()],
            [],
        );
    }
}
