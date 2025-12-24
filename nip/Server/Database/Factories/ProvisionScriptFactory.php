<?php

namespace Nip\Server\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\Server\Enums\ProvisionScriptStatus;
use Nip\Server\Models\ProvisionScript;
use Nip\Server\Models\Server;

class ProvisionScriptFactory extends Factory
{
    protected $model = ProvisionScript::class;

    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'filename' => 'provision-'.time().'_'.uniqid().'.sh',
            'resource_type' => fake()->randomElement(['background_process', 'scheduled_job', 'firewall_rule', 'site']),
            'resource_id' => fake()->randomNumber(5),
            'content' => "#!/bin/bash\necho 'Hello World'",
            'output' => null,
            'exit_code' => null,
            'status' => ProvisionScriptStatus::Pending,
            'executed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProvisionScriptStatus::Completed,
            'exit_code' => 0,
            'output' => 'Script executed successfully.',
            'executed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProvisionScriptStatus::Failed,
            'exit_code' => 1,
            'output' => 'Error: Command failed.',
            'executed_at' => now(),
        ]);
    }

    public function executing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProvisionScriptStatus::Executing,
        ]);
    }
}
