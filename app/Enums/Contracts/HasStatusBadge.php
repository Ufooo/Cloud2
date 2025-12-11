<?php

namespace App\Enums\Contracts;

interface HasStatusBadge
{
    public function label(): string;

    public function badgeVariant(): string;
}
