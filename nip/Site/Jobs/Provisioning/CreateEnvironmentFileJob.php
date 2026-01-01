<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Site\Enums\SiteProvisioningStep;

class CreateEnvironmentFileJob extends BaseSiteProvisionJob
{
    protected function getRunAsUser(): ?string
    {
        return $this->site->user;
    }

    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::ConfiguringEnvironment;
    }

    protected function generateScript(): string
    {
        $database = $this->buildDatabaseConfig();

        return view('provisioning.scripts.site.steps.create-environment-file', [
            'site' => $this->site,
            'fullPath' => $this->site->getFullPath(),
            'user' => $this->site->user,
            'environment' => $this->site->environment ?? '',
            'database' => $database,
        ])->render();
    }

    /**
     * @return object{type: string, host: string, port: int, name: string, username: string, password: string}|null
     */
    protected function buildDatabaseConfig(): ?object
    {
        $this->site->load(['database', 'databaseUser']);

        if (! $this->site->database || ! $this->site->databaseUser) {
            return null;
        }

        $dbType = $this->site->server->database_type;

        return (object) [
            'type' => $dbType?->type() ?? 'mysql',
            'host' => '127.0.0.1',
            'port' => match ($dbType?->type()) {
                'postgresql' => 5432,
                default => 3306,
            },
            'name' => $this->site->database->name,
            'username' => $this->site->databaseUser->username,
            'password' => $this->site->databaseUser->password,
        ];
    }
}
