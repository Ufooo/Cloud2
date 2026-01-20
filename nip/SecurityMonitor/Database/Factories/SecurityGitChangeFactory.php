<?php

namespace Nip\SecurityMonitor\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\SecurityMonitor\Enums\GitChangeType;
use Nip\SecurityMonitor\Models\SecurityGitChange;
use Nip\SecurityMonitor\Models\SecurityScan;
use Nip\Site\Models\Site;

class SecurityGitChangeFactory extends Factory
{
    protected $model = SecurityGitChange::class;

    public function definition(): array
    {
        $changeType = fake()->randomElement(GitChangeType::cases());
        $filePath = $this->generateFilePath();

        return [
            'scan_id' => SecurityScan::factory(),
            'site_id' => Site::factory(),
            'file_path' => $filePath,
            'change_type' => $changeType,
            'git_status_code' => $this->getGitStatusCode($changeType),
            'is_whitelisted' => fake()->boolean(20),
            'whitelisted_by' => null,
            'whitelisted_at' => null,
            'whitelist_reason' => null,
        ];
    }

    public function whitelisted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_whitelisted' => true,
            'whitelisted_by' => User::factory(),
            'whitelisted_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'whitelist_reason' => fake()->sentence(),
        ]);
    }

    private function generateFilePath(): string
    {
        $directories = [
            'app/Http/Controllers',
            'app/Models',
            'resources/views',
            'public/js',
            'config',
        ];

        $extensions = ['php', 'js', 'json', 'env', 'blade.php'];

        return fake()->randomElement($directories).'/'.fake()->word().'.'.fake()->randomElement($extensions);
    }

    private function getGitStatusCode(GitChangeType $changeType): string
    {
        return match ($changeType) {
            GitChangeType::Modified => ' M',
            GitChangeType::Added => 'A ',
            GitChangeType::Deleted => ' D',
            GitChangeType::Untracked => '??',
            GitChangeType::Renamed => 'R ',
            GitChangeType::Copied => 'C ',
            default => '??',
        };
    }
}
