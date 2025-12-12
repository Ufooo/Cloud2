<?php

namespace Nip\Site\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PackageManager: string
{
    case Npm = 'npm';
    case Yarn = 'yarn';
    case Pnpm = 'pnpm';
    case Bun = 'bun';

    public function label(): string
    {
        return match ($this) {
            self::Npm => 'npm',
            self::Yarn => 'Yarn',
            self::Pnpm => 'pnpm',
            self::Bun => 'Bun',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $manager) => [
                'value' => $manager->value,
                'label' => $manager->label(),
            ],
            self::cases()
        );
    }
}
