<?php

namespace Nip\BackgroundProcess\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\BackgroundProcess\Enums\ProcessStatus;
use Nip\BackgroundProcess\Enums\StopSignal;
use Nip\BackgroundProcess\Enums\SupervisorProcessStatus;
use Nip\BackgroundProcess\Models\BackgroundProcess;
use Nip\Server\Models\Server;

class BackgroundProcessFactory extends Factory
{
    protected $model = BackgroundProcess::class;

    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'name' => fake()->words(2, true),
            'command' => 'php artisan queue:work',
            'directory' => '/home/netipar/'.fake()->domainWord(),
            'user' => 'netipar',
            'processes' => fake()->numberBetween(1, 5),
            'startsecs' => 1,
            'stopwaitsecs' => 15,
            'stopsignal' => StopSignal::TERM,
            'status' => ProcessStatus::Installed,
            'supervisor_process_status' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProcessStatus::Pending,
            'supervisor_process_status' => null,
        ]);
    }

    public function installing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProcessStatus::Installing,
            'supervisor_process_status' => null,
        ]);
    }

    public function installed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProcessStatus::Installed,
            'supervisor_process_status' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProcessStatus::Failed,
            'supervisor_process_status' => SupervisorProcessStatus::Fatal,
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProcessStatus::Installed,
            'supervisor_process_status' => SupervisorProcessStatus::Running,
        ]);
    }

    public function stopped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProcessStatus::Installed,
            'supervisor_process_status' => SupervisorProcessStatus::Stopped,
        ]);
    }

    public function queueWorker(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Queue Worker',
            'command' => 'php artisan queue:work --sleep=3 --tries=3 --max-time=3600',
        ]);
    }

    public function scheduler(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Scheduler',
            'command' => 'php artisan schedule:work',
        ]);
    }

    public function horizonWorker(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Horizon',
            'command' => 'php artisan horizon',
        ]);
    }
}
