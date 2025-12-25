<?php

namespace Nip\Site\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum SiteType: string
{
    case Laravel = 'laravel';
    case Symfony = 'symfony';
    case Statamic = 'statamic';
    case WordPress = 'wordpress';
    case PhpMyAdmin = 'phpmyadmin';
    case Php = 'php';
    case NextJs = 'nextjs';
    case NuxtJs = 'nuxtjs';
    case Html = 'html';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Laravel => 'Laravel',
            self::Symfony => 'Symfony',
            self::Statamic => 'Statamic',
            self::WordPress => 'WordPress',
            self::PhpMyAdmin => 'phpMyAdmin',
            self::Php => 'PHP',
            self::NextJs => 'Next.js',
            self::NuxtJs => 'Nuxt.js',
            self::Html => 'HTML',
            self::Other => 'Other',
        };
    }

    public function defaultWebDirectory(): string
    {
        return match ($this) {
            self::Laravel, self::Statamic => '/public',
            self::Symfony => '/public',
            self::WordPress, self::PhpMyAdmin, self::Php, self::Html, self::Other => '/',
            self::NextJs, self::NuxtJs => '/',
        };
    }

    public function defaultBuildCommand(): ?string
    {
        return match ($this) {
            self::Laravel, self::Statamic => 'npm run build',
            self::NextJs => 'npm run build',
            self::NuxtJs => 'npm run build',
            default => null,
        };
    }

    public function isPhpBased(): bool
    {
        return match ($this) {
            self::Laravel, self::Symfony, self::Statamic, self::WordPress, self::PhpMyAdmin, self::Php => true,
            default => false,
        };
    }

    public function hasMigrations(): bool
    {
        return match ($this) {
            self::Laravel, self::Statamic => true,
            default => false,
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            self::cases()
        );
    }
}
