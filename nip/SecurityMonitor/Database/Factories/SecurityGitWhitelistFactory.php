<?php

namespace Nip\SecurityMonitor\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\SecurityMonitor\Enums\GitChangeType;
use Nip\SecurityMonitor\Models\SecurityGitWhitelist;
use Nip\Site\Models\Site;

class SecurityGitWhitelistFactory extends Factory
{
    protected $model = SecurityGitWhitelist::class;

    public function definition(): array
    {
        $paths = [
            'vendor/autoload.php',
            'node_modules/.package-lock.json',
            'storage/logs/laravel.log',
            'public/build/manifest.json',
            'composer.lock',
        ];

        return [
            'site_id' => Site::factory(),
            'file_path' => fake()->randomElement($paths),
            'change_type' => fake()->randomElement([
                GitChangeType::Modified,
                GitChangeType::Untracked,
                GitChangeType::Deleted,
                GitChangeType::Added,
                GitChangeType::Renamed,
                GitChangeType::Copied,
                GitChangeType::Any,
            ]),
            'reason' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
