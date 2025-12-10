<?php

namespace Nip\SshKey\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\Server\Models\Server;
use Nip\SshKey\Models\SshKey;
use Nip\UnixUser\Models\UnixUser;

class SshKeyFactory extends Factory
{
    protected $model = SshKey::class;

    public function definition(): array
    {
        // Generate a realistic SSH public key (ed25519 format)
        $keyData = base64_encode(random_bytes(32));

        return [
            'server_id' => Server::factory(),
            'unix_user_id' => null,
            'name' => fake()->randomElement(['Deploy Key', 'CI/CD Key', 'GitHub Actions', 'GitLab Runner', 'Production Key']),
            'public_key' => 'ssh-ed25519 '.$keyData.' '.fake()->email(),
        ];
    }

    public function forUnixUser(UnixUser $unixUser): static
    {
        return $this->state(fn (array $attributes) => [
            'server_id' => $unixUser->server_id,
            'unix_user_id' => $unixUser->id,
        ]);
    }
}
