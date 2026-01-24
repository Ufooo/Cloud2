<?php

namespace Nip\Site\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class SiteCreationData extends Data
{
    public function __construct(
        public int $server_id,
        public string $domain,
        public string $type,
        public string $user,
        public ?string $www_redirect_type = null,
        public ?bool $allow_wildcard = null,
        public ?string $root_directory = null,
        public ?string $web_directory = null,
        public ?string $php_version = null,
        public ?string $package_manager = null,
        public ?string $build_command = null,
        public ?string $deploy_script = null,
        public ?int $source_control_id = null,
        public ?string $repository = null,
        public ?string $branch = null,
        public ?bool $zero_downtime = null,
        public ?int $database_id = null,
        public ?int $database_user_id = null,
        #[MapInputName('create_database')]
        public ?bool $createDatabase = null,
        #[MapInputName('database_name')]
        public ?string $databaseName = null,
        #[MapInputName('database_user')]
        public ?string $databaseUser = null,
        #[MapInputName('database_password')]
        public ?string $databasePassword = null,
    ) {}

    public function getSiteData(): array
    {
        return [
            'server_id' => $this->server_id,
            'domain' => $this->domain,
            'type' => $this->type,
            'user' => $this->user,
            'www_redirect_type' => $this->www_redirect_type,
            'allow_wildcard' => $this->allow_wildcard,
            'root_directory' => $this->root_directory,
            'web_directory' => $this->web_directory,
            'php_version' => $this->php_version,
            'package_manager' => $this->package_manager,
            'build_command' => $this->build_command,
            'deploy_script' => $this->deploy_script,
            'source_control_id' => $this->source_control_id,
            'repository' => $this->repository,
            'branch' => $this->branch,
            'zero_downtime' => $this->zero_downtime,
            'database_id' => $this->database_id,
            'database_user_id' => $this->database_user_id,
        ];
    }

    public function hasDatabaseCreation(): bool
    {
        return $this->createDatabase === true;
    }

    public function getDatabaseName(): ?string
    {
        return $this->databaseName;
    }

    public function getDatabaseUser(): ?string
    {
        return $this->databaseUser;
    }

    public function getDatabasePassword(): ?string
    {
        return $this->databasePassword;
    }

    public function hasExistingDatabase(): bool
    {
        return $this->database_id !== null;
    }
}
