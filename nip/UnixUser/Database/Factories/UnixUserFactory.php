<?php

namespace Nip\UnixUser\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\Server\Models\Server;
use Nip\UnixUser\Enums\UserStatus;
use Nip\UnixUser\Models\UnixUser;

class UnixUserFactory extends Factory
{
    protected $model = UnixUser::class;

    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'username' => fake()->userName(),
            'status' => UserStatus::Installed,
        ];
    }
}
