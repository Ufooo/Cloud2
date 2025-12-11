<?php

namespace Nip\Scheduler\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\Scheduler\Enums\CronFrequency;
use Nip\Scheduler\Enums\GracePeriod;
use Nip\Scheduler\Enums\JobStatus;
use Nip\Scheduler\Models\ScheduledJob;
use Nip\Server\Models\Server;

class ScheduledJobFactory extends Factory
{
    protected $model = ScheduledJob::class;

    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'name' => fake()->words(3, true),
            'command' => '/usr/local/bin/composer self-update',
            'user' => 'netipar',
            'frequency' => CronFrequency::Weekly,
            'cron' => null,
            'heartbeat_enabled' => false,
            'heartbeat_url' => null,
            'grace_period' => null,
            'status' => JobStatus::Installed,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobStatus::Pending,
        ]);
    }

    public function installing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobStatus::Installing,
        ]);
    }

    public function installed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobStatus::Installed,
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobStatus::Paused,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobStatus::Failed,
        ]);
    }

    public function withHeartbeat(): static
    {
        return $this->state(fn (array $attributes) => [
            'heartbeat_enabled' => true,
            'heartbeat_url' => 'https://heartbeat.example.com/'.fake()->uuid(),
            'grace_period' => GracePeriod::FiveMinutes,
        ]);
    }

    public function customFrequency(string $cron = '*/5 * * * *'): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => CronFrequency::Custom,
            'cron' => $cron,
        ]);
    }

    public function artisanSchedule(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Laravel Scheduler',
            'command' => 'php /home/netipar/app/artisan schedule:run',
            'frequency' => CronFrequency::EveryMinute,
        ]);
    }

    public function composerUpdate(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Update Composer',
            'command' => '/usr/local/bin/composer self-update',
            'user' => 'root',
            'frequency' => CronFrequency::Weekly,
        ]);
    }
}
