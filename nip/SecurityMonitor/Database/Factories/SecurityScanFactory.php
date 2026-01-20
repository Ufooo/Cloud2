<?php

namespace Nip\SecurityMonitor\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\SecurityMonitor\Enums\ScanStatus;
use Nip\SecurityMonitor\Models\SecurityScan;
use Nip\Server\Models\Server;
use Nip\Site\Models\Site;

class SecurityScanFactory extends Factory
{
    protected $model = SecurityScan::class;

    public function definition(): array
    {
        $status = fake()->randomElement(ScanStatus::cases());
        $startedAt = fake()->dateTimeBetween('-7 days', 'now');
        $completedAt = in_array($status, [ScanStatus::Clean, ScanStatus::IssuesDetected, ScanStatus::Error])
            ? fake()->dateTimeBetween($startedAt, 'now')
            : null;

        return [
            'site_id' => Site::factory(),
            'server_id' => Server::factory(),
            'status' => $status,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'git_modified_count' => 0,
            'git_untracked_count' => 0,
            'git_deleted_count' => 0,
            'git_whitelisted_count' => 0,
            'git_new_count' => 0,
            'error_message' => $status === ScanStatus::Error ? fake()->sentence() : null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScanStatus::Pending,
            'started_at' => null,
            'completed_at' => null,
            'error_message' => null,
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScanStatus::Running,
            'started_at' => now(),
            'completed_at' => null,
            'error_message' => null,
        ]);
    }

    public function clean(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScanStatus::Clean,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'error_message' => null,
        ]);
    }

    public function withIssues(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScanStatus::IssuesDetected,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
            'git_modified_count' => fake()->numberBetween(1, 10),
            'git_untracked_count' => fake()->numberBetween(0, 5),
            'git_new_count' => fake()->numberBetween(1, 15),
            'error_message' => null,
        ]);
    }
}
