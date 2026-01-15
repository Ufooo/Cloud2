<?php

namespace App\Enums\Concerns;

trait HasOptions
{
    /**
     * Get all enum cases as options array for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $case) => [
                'value' => $case->value,
                'label' => method_exists($case, 'label') ? $case->label() : $case->name,
            ])
            ->values()
            ->all();
    }
}
