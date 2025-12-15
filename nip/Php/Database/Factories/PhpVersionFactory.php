<?php

namespace Nip\Php\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\Php\Enums\PhpVersion as PhpVersionEnum;
use Nip\Php\Enums\PhpVersionStatus;
use Nip\Php\Models\PhpVersion;
use Nip\Server\Models\Server;

/**
 * @extends Factory<PhpVersion>
 */
class PhpVersionFactory extends Factory
{
    protected $model = PhpVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'version' => fake()->randomElement(array_map(fn ($v) => $v->value, PhpVersionEnum::cases())),
            'is_cli_default' => false,
            'is_site_default' => false,
            'status' => PhpVersionStatus::Installed,
        ];
    }

    public function cliDefault(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_cli_default' => true,
        ]);
    }

    public function siteDefault(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_site_default' => true,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PhpVersionStatus::Pending,
        ]);
    }

    public function installing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PhpVersionStatus::Installing,
        ]);
    }

    public function installed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PhpVersionStatus::Installed,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PhpVersionStatus::Failed,
        ]);
    }
}
