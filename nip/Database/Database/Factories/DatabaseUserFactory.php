<?php

namespace Nip\Database\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\Database\Models\DatabaseUser;
use Nip\Server\Models\Server;

/**
 * @extends Factory<DatabaseUser>
 */
class DatabaseUserFactory extends Factory
{
    protected $model = DatabaseUser::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'username' => fake()->unique()->userName(),
        ];
    }

    public function forServer(Server $server): static
    {
        return $this->state(fn (array $attributes) => [
            'server_id' => $server->id,
        ]);
    }
}
