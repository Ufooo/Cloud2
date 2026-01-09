<?php

namespace App\Models\Concerns;

trait HasEnumDisplayAttributes
{
    /**
     * Get the displayable label for an enum property.
     *
     * Returns the label() of the enum if it exists, null otherwise.
     */
    protected function getEnumLabel(string $property): ?string
    {
        return $this->{$property}?->label();
    }

    /**
     * Get the displayable label for an enum property that might need tryFrom conversion.
     *
     * Useful for properties stored as strings that need to be converted to enums first.
     *
     * @param  class-string  $enumClass  The enum class to use for conversion
     */
    protected function getEnumLabelFrom(string $property, string $enumClass): ?string
    {
        if (! $this->{$property}) {
            return null;
        }

        return $enumClass::tryFrom($this->{$property})?->label();
    }
}
