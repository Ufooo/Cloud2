<?php

namespace Nip\Database\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\Database\Enums\DatabaseStatus;
use Nip\Database\Models\Database;
use Nip\Server\Models\Server;
use Nip\Site\Models\Site;

/**
 * @extends Factory<Database>
 */
class DatabaseFactory extends Factory
{
    protected $model = Database::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'site_id' => null,
            'name' => fake()->unique()->word().'_db',
            'size' => fake()->optional(0.7)->numberBetween(1024, 10737418240),
            'status' => DatabaseStatus::Installed,
        ];
    }

    public function forSite(Site $site): static
    {
        return $this->state(fn (array $attributes) => [
            'server_id' => $site->server_id,
            'site_id' => $site->id,
        ]);
    }

    public function forServer(Server $server): static
    {
        return $this->state(fn (array $attributes) => [
            'server_id' => $server->id,
        ]);
    }

    public function installing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DatabaseStatus::Installing,
        ]);
    }
}
