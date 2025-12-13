<?php

namespace Nip\Domain\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Enums\CertificateType;
use Nip\Domain\Models\Certificate;
use Nip\Site\Models\Site;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'type' => CertificateType::LetsEncrypt,
            'status' => CertificateStatus::Installed,
            'domains' => [fake()->domainName()],
            'active' => true,
            'path' => '/etc/nginx/ssl/'.fake()->domainName(),
            'issued_at' => now(),
            'expires_at' => now()->addMonths(3),
        ];
    }

    public function letsEncrypt(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CertificateType::LetsEncrypt,
        ]);
    }

    public function existing(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CertificateType::Existing,
        ]);
    }

    public function csr(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CertificateType::Csr,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CertificateStatus::Pending,
            'active' => false,
            'issued_at' => null,
            'expires_at' => null,
        ]);
    }

    public function installed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CertificateStatus::Installed,
            'active' => true,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CertificateStatus::Failed,
            'active' => false,
        ]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addDays(15),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDays(1),
            'active' => false,
        ]);
    }
}
