<?php

namespace Nip\Domain\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum WildcardBehavior: string
{
    case Serve = 'serve';
    case Redirect = 'redirect';

    public function label(): string
    {
        return match ($this) {
            self::Serve => 'Serve subdomains from the site',
            self::Redirect => 'Redirect unconfigured subdomains',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Serve => 'The application answers every subdomain, e.g. a tenant per subdomain',
            self::Redirect => 'Subdomains without their own configuration go to the main domain',
        };
    }

    public function redirectsUnconfiguredSubdomains(): bool
    {
        return $this === self::Redirect;
    }

    /**
     * @return array<array{value: string, label: string, description: string, isDefault: bool}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $behavior) => [
                'value' => $behavior->value,
                'label' => $behavior->label(),
                'description' => $behavior->description(),
                'isDefault' => $behavior === self::Serve,
            ],
            self::cases()
        );
    }
}
