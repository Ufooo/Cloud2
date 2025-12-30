<?php

namespace Nip\Site\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\Server\Models\Server;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Enums\PackageManager;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Enums\SiteType;
use Nip\Site\Enums\WwwRedirectType;
use Nip\Site\Models\Site;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(SiteType::cases());
        $domain = fake()->unique()->domainName();

        return [
            'server_id' => Server::factory(),
            'domain' => $domain,
            'slug' => \Illuminate\Support\Str::slug($domain),
            'type' => $type,
            'status' => SiteStatus::Installed,
            'deploy_status' => DeployStatus::NeverDeployed,
            'user' => 'netipar',
            'root_directory' => '/',
            'web_directory' => $type->defaultWebDirectory(),
            'php_version' => $type->isPhpBased() ? '8.2' : null,
            'package_manager' => PackageManager::Npm,
            'build_command' => $type->defaultBuildCommand(),
            'repository' => null,
            'branch' => null,
            'allow_wildcard' => false,
            'www_redirect_type' => WwwRedirectType::FromWww,
        ];
    }

    public function laravel(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => SiteType::Laravel,
            'web_directory' => '/public',
            'php_version' => '8.2',
            'build_command' => 'npm run build',
        ]);
    }

    public function wordpress(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => SiteType::WordPress,
            'web_directory' => '/',
            'php_version' => '8.2',
            'build_command' => null,
        ]);
    }

    public function static(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => SiteType::Html,
            'web_directory' => '/',
            'php_version' => null,
            'build_command' => null,
        ]);
    }

    public function deployed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deploy_status' => DeployStatus::Deployed,
            'last_deployed_at' => now(),
        ]);
    }

    public function withRepository(string $repository, string $branch = 'main'): static
    {
        return $this->state(fn (array $attributes) => [
            'repository' => $repository,
            'branch' => $branch,
        ]);
    }
}
