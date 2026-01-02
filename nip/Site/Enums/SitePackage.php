<?php

namespace Nip\Site\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum SitePackage: string
{
    // Composer packages
    case Laravel = 'laravel';
    case Horizon = 'horizon';
    case Octane = 'octane';
    case Pulse = 'pulse';
    case Reverb = 'reverb';
    case Inertia = 'inertia';
    case Nightwatch = 'nightwatch';

    // Features/Daemons (not just composer packages)
    case InertiaSsr = 'inertia_ssr';
    case Scheduler = 'scheduler';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Laravel => 'Laravel',
            self::Horizon => 'Horizon',
            self::Octane => 'Octane',
            self::Pulse => 'Pulse',
            self::Reverb => 'Reverb',
            self::Inertia => 'Inertia',
            self::InertiaSsr => 'Inertia SSR',
            self::Nightwatch => 'Nightwatch',
            self::Scheduler => 'Scheduler',
            self::Maintenance => 'Maintenance',
        };
    }

    public function composerPackage(): ?string
    {
        return match ($this) {
            self::Laravel => 'laravel/framework',
            self::Horizon => 'laravel/horizon',
            self::Octane => 'laravel/octane',
            self::Pulse => 'laravel/pulse',
            self::Reverb => 'laravel/reverb',
            self::Inertia => 'inertiajs/inertia-laravel',
            self::Nightwatch => 'laravel/nightwatch',
            self::InertiaSsr, self::Scheduler, self::Maintenance => null,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Laravel => 'Laravel Framework',
            self::Horizon => 'Queue Monitoring',
            self::Octane => 'High-Performance Server',
            self::Pulse => 'Application Monitoring',
            self::Reverb => 'WebSocket Server',
            self::Inertia => 'Modern Monolith Framework',
            self::InertiaSsr => 'Server-Side Rendering',
            self::Nightwatch => 'Browser Testing',
            self::Scheduler => 'Scheduled Tasks',
            self::Maintenance => 'Maintenance Mode',
        };
    }

    public function hasDaemon(): bool
    {
        return match ($this) {
            self::Horizon, self::Octane, self::Reverb, self::InertiaSsr => true,
            default => false,
        };
    }

    public function isFeature(): bool
    {
        return match ($this) {
            self::InertiaSsr, self::Scheduler, self::Maintenance => true,
            default => false,
        };
    }

    /**
     * Packages detectable from composer.lock
     *
     * @return array<self>
     */
    public static function detectable(): array
    {
        return [
            self::Laravel,
            self::Horizon,
            self::Octane,
            self::Pulse,
            self::Reverb,
            self::Inertia,
            self::Nightwatch,
        ];
    }

    /**
     * All displayable items (packages + features)
     *
     * @return array<self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
