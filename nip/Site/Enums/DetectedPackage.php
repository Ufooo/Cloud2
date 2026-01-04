<?php

namespace Nip\Site\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum DetectedPackage: string
{
    case Laravel = 'laravel';
    case Horizon = 'horizon';
    case Inertia = 'inertia';
    case Octane = 'octane';
    case Reverb = 'reverb';
    case Pulse = 'pulse';
    case Telescope = 'telescope';
    case Nova = 'nova';
    case Cashier = 'cashier';
    case Pennant = 'pennant';
    case Sanctum = 'sanctum';
    case Passport = 'passport';
    case Socialite = 'socialite';
    case Scout = 'scout';
    case Breeze = 'breeze';
    case Jetstream = 'jetstream';
    case Folio = 'folio';
    case Livewire = 'livewire';

    public function label(): string
    {
        return match ($this) {
            self::Laravel => 'Laravel',
            self::Horizon => 'Horizon',
            self::Inertia => 'Inertia',
            self::Octane => 'Octane',
            self::Reverb => 'Reverb',
            self::Pulse => 'Pulse',
            self::Telescope => 'Telescope',
            self::Nova => 'Nova',
            self::Cashier => 'Cashier',
            self::Pennant => 'Pennant',
            self::Sanctum => 'Sanctum',
            self::Passport => 'Passport',
            self::Socialite => 'Socialite',
            self::Scout => 'Scout',
            self::Breeze => 'Breeze',
            self::Jetstream => 'Jetstream',
            self::Folio => 'Folio',
            self::Livewire => 'Livewire',
        };
    }

    public function composerPackage(): string
    {
        return match ($this) {
            self::Laravel => 'laravel/framework',
            self::Horizon => 'laravel/horizon',
            self::Inertia => 'inertiajs/inertia-laravel',
            self::Octane => 'laravel/octane',
            self::Reverb => 'laravel/reverb',
            self::Pulse => 'laravel/pulse',
            self::Telescope => 'laravel/telescope',
            self::Nova => 'laravel/nova',
            self::Cashier => 'laravel/cashier',
            self::Pennant => 'laravel/pennant',
            self::Sanctum => 'laravel/sanctum',
            self::Passport => 'laravel/passport',
            self::Socialite => 'laravel/socialite',
            self::Scout => 'laravel/scout',
            self::Breeze => 'laravel/breeze',
            self::Jetstream => 'laravel/jetstream',
            self::Folio => 'laravel/folio',
            self::Livewire => 'livewire/livewire',
        };
    }

    public function hasEnableAction(): bool
    {
        return match ($this) {
            self::Horizon, self::Inertia, self::Octane, self::Reverb => true,
            default => false,
        };
    }

    public function enableActionLabel(): ?string
    {
        return match ($this) {
            self::Horizon => 'Start Horizon',
            self::Inertia => 'Enable SSR',
            self::Octane => 'Start Octane',
            self::Reverb => 'Start Reverb',
            default => null,
        };
    }

    public function disableActionLabel(): ?string
    {
        return match ($this) {
            self::Horizon => 'Stop Horizon',
            self::Inertia => 'Disable SSR',
            self::Octane => 'Stop Octane',
            self::Reverb => 'Stop Reverb',
            default => null,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Laravel => 'The Laravel PHP Framework',
            self::Horizon => 'Queue dashboard and monitoring',
            self::Inertia => 'Modern monolith SPA framework',
            self::Octane => 'High-performance application server',
            self::Reverb => 'WebSocket server for Laravel',
            self::Pulse => 'Application performance monitoring',
            self::Telescope => 'Debug assistant for Laravel',
            self::Nova => 'Administration panel',
            self::Cashier => 'Subscription billing integration',
            self::Pennant => 'Feature flags',
            self::Sanctum => 'API token authentication',
            self::Passport => 'OAuth2 server',
            self::Socialite => 'OAuth authentication',
            self::Scout => 'Full-text search',
            self::Breeze => 'Authentication scaffolding',
            self::Jetstream => 'Application starter kit',
            self::Folio => 'Page-based routing',
            self::Livewire => 'Full-stack framework',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function composerPackageMap(): array
    {
        $map = [];
        foreach (self::cases() as $package) {
            $map[$package->composerPackage()] = $package->value;
        }

        return $map;
    }
}
