<?php

namespace Nip\Server\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\Php\Enums\PhpVersion;
use Nip\Server\Enums\DatabaseType;
use Nip\Server\Enums\IdentityColor;
use Nip\Server\Enums\ServerProvider;
use Nip\Server\Enums\ServerStatus;
use Nip\Server\Enums\ServerType;
use Nip\Server\Enums\UbuntuVersion;
use Nip\Server\Models\Server;
use Nip\UnixUser\Enums\UserStatus;
use Nip\UnixUser\Models\UnixUser;

class ServerFactory extends Factory
{
    protected $model = Server::class;

    public function definition(): array
    {
        $name = fake()->company().' Server';

        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name).'-'.fake()->randomLetter().fake()->randomLetter().fake()->randomLetter(),
            'provider' => fake()->randomElement(ServerProvider::cases()),
            'provider_server_id' => fake()->uuid(),
            'type' => fake()->randomElement(ServerType::cases()),
            'status' => fake()->randomElement([
                ServerStatus::Provisioning,
                ServerStatus::Connected,
                ServerStatus::Disconnected,
            ]),
            'ip_address' => fake()->ipv4(),
            'private_ip_address' => fake()->localIpv4(),
            'ssh_port' => '22',
            'php_version' => fake()->randomElement(PhpVersion::cases()),
            'database_type' => fake()->randomElement([...DatabaseType::cases(), null]),
            'db_status' => fake()->randomElement(['installed', 'not_installed', null]),
            'ubuntu_version' => fake()->randomElement(UbuntuVersion::cases()),
            'timezone' => 'UTC',
            'notes' => fake()->sentence(),
            'avatar_color' => fake()->randomElement(IdentityColor::cases()),
            'services' => [
                'nginx' => true,
                'mysql' => fake()->boolean(),
                'redis' => fake()->boolean(),
            ],
            'cloud_provider_url' => fake()->url(),
            'is_ready' => fake()->boolean(70),
            'last_connected_at' => fake()->optional()->dateTimeBetween('-7 days', 'now'),
        ];
    }

    public function connected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ServerStatus::Connected,
            'is_ready' => true,
        ]);
    }

    public function provisioning(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ServerStatus::Provisioning,
            'is_ready' => false,
        ]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Server $server) {
            UnixUser::factory()->create([
                'server_id' => $server->id,
                'username' => 'root',
                'status' => UserStatus::Installed,
            ]);

            UnixUser::factory()->create([
                'server_id' => $server->id,
                'username' => 'netipar',
                'status' => UserStatus::Installed,
            ]);
        });
    }
}
