<?php

namespace Nip\Deployment\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\Deployment\Enums\DeploymentStatus;
use Nip\Deployment\Models\Deployment;
use Nip\Site\Models\Site;

/**
 * @extends Factory<Deployment>
 */
class DeploymentFactory extends Factory
{
    protected $model = Deployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = $this->faker->dateTimeBetween('-6 months', 'now');
        $endedAt = (clone $startedAt)->modify('+'.rand(10, 120).' seconds');

        return [
            'site_id' => Site::factory(),
            'user_id' => User::factory(),
            'status' => DeploymentStatus::Finished,
            'commit_hash' => $this->faker->sha1(),
            'commit_message' => $this->faker->sentence(),
            'commit_author' => $this->faker->name(),
            'branch' => $this->faker->randomElement(['main', 'master', 'develop', 'staging']),
            'output' => null,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeploymentStatus::Pending,
            'started_at' => null,
            'ended_at' => null,
        ]);
    }

    public function deploying(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeploymentStatus::Deploying,
            'started_at' => now(),
            'ended_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeploymentStatus::Failed,
        ]);
    }
}
