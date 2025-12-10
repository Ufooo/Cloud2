<?php

namespace Nip\UserSshKey\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\UserSshKey\Models\UserSshKey;

class UserSshKeyFactory extends Factory
{
    protected $model = UserSshKey::class;

    public function definition(): array
    {
        // Generate a realistic SSH public key (ed25519 format)
        $keyData = base64_encode(random_bytes(32));

        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['MacBook Pro', 'Work Laptop', 'Home Desktop', 'Development Machine']),
            'public_key' => 'ssh-ed25519 '.$keyData.' '.fake()->email(),
        ];
    }
}
