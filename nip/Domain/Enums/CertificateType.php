<?php

namespace Nip\Domain\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum CertificateType: string
{
    case LetsEncrypt = 'letsencrypt';
    case Existing = 'existing';
    case Csr = 'csr';

    public function label(): string
    {
        return match ($this) {
            self::LetsEncrypt => "Let's Encrypt",
            self::Existing => 'Existing Certificate',
            self::Csr => 'CSR',
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
