<?php

namespace Nip\Php\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\Php\Models\PhpSetting;
use Nip\Server\Models\Server;

/**
 * @extends Factory<PhpSetting>
 */
class PhpSettingFactory extends Factory
{
    protected $model = PhpSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'max_upload_size' => fake()->numberBetween(1, 2048),
            'max_execution_time' => fake()->numberBetween(0, 3600),
            'opcache_enabled' => fake()->boolean(),
        ];
    }
}
