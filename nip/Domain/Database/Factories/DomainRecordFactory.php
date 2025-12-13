<?php

namespace Nip\Domain\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Domain\Enums\DomainRecordType;
use Nip\Domain\Models\DomainRecord;
use Nip\Site\Enums\WwwRedirectType;
use Nip\Site\Models\Site;

/**
 * @extends Factory<DomainRecord>
 */
class DomainRecordFactory extends Factory
{
    protected $model = DomainRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'name' => fake()->domainName(),
            'type' => DomainRecordType::Alias,
            'status' => DomainRecordStatus::Enabled,
            'www_redirect_type' => WwwRedirectType::FromWww,
            'allow_wildcard' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DomainRecordType::Primary,
        ]);
    }

    public function alias(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DomainRecordType::Alias,
        ]);
    }

    public function reverb(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DomainRecordType::Reverb,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DomainRecordStatus::Pending,
        ]);
    }

    public function enabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DomainRecordStatus::Enabled,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DomainRecordStatus::Disabled,
        ]);
    }
}
