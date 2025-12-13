<?php

namespace Nip\Domain\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum CertificateType: string
{
    case LetsEncrypt = 'letsencrypt';
    case Existing = 'existing';
    case Csr = 'csr';
    case Clone = 'clone';

    public function label(): string
    {
        return match ($this) {
            self::LetsEncrypt => "Let's Encrypt",
            self::Existing => 'Existing Certificate',
            self::Csr => 'Certificate Signing Request',
            self::Clone => 'Clone Certificate',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::LetsEncrypt => 'Free, automated certificate from Let\'s Encrypt',
            self::Existing => 'Upload your own SSL certificate and private key',
            self::Csr => 'Generate a signing request for external certificate authority',
            self::Clone => 'Copy a certificate from another site',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
            ],
            self::cases()
        );
    }
}
