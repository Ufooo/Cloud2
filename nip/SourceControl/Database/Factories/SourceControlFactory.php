<?php

namespace Nip\SourceControl\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\SourceControl\Enums\SourceControlProvider;
use Nip\SourceControl\Models\SourceControl;

class SourceControlFactory extends Factory
{
    protected $model = SourceControl::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => SourceControlProvider::GitHub,
            'name' => $this->faker->userName(),
            'provider_user_id' => (string) $this->faker->randomNumber(8),
            'token' => $this->faker->sha256(),
            'refresh_token' => null,
            'token_expires_at' => null,
        ];
    }

    public function github(): static
    {
        return $this->state(fn () => [
            'provider' => SourceControlProvider::GitHub,
        ]);
    }

    public function gitlab(): static
    {
        return $this->state(fn () => [
            'provider' => SourceControlProvider::GitLab,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'token_expires_at' => now()->subDay(),
        ]);
    }
}
