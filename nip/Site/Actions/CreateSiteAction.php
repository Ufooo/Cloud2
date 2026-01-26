<?php

namespace Nip\Site\Actions;

use Nip\Database\Enums\DatabaseStatus;
use Nip\Database\Enums\DatabaseUserStatus;
use Nip\Database\Jobs\CreateDatabaseJob;
use Nip\Database\Jobs\SyncDatabaseUserJob;
use Nip\Database\Models\Database;
use Nip\Database\Models\DatabaseUser;
use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Domain\Enums\DomainRecordType;
use Nip\Server\Models\Server;
use Nip\Site\Data\SiteCreationData;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Enums\SiteType;
use Nip\Site\Models\Site;
use Nip\Site\Services\SiteProvisioningService;

class CreateSiteAction
{
    public function __construct(
        private SiteProvisioningService $provisioningService,
    ) {}

    public function handle(SiteCreationData $data): Site
    {
        $server = Server::findOrFail($data->server_id);
        $siteType = SiteType::from($data->type);

        $site = $this->createSite($server, $data, $siteType);

        $this->handleDatabaseSetup($site, $server, $data);

        $this->createPrimaryDomainRecord($site);

        $this->provisioningService->dispatch($site);

        return $site;
    }

    private function createSite(Server $server, SiteCreationData $data, SiteType $siteType): Site
    {
        $siteData = array_filter($data->getSiteData(), fn ($value) => $value !== null);

        $siteData['web_directory'] ??= $siteType->defaultWebDirectory();
        $siteData['build_command'] ??= $siteType->defaultBuildCommand();
        $siteData['deploy_script'] ??= $siteType->defaultDeployScript();

        $site = $server->sites()->create([
            ...$siteData,
            'status' => SiteStatus::Pending,
            'deploy_status' => DeployStatus::NeverDeployed,
            'deploy_hook_token' => bin2hex(random_bytes(32)),
        ]);

        $site->refresh();

        return $site;
    }

    private function handleDatabaseSetup(Site $site, Server $server, SiteCreationData $data): void
    {
        if ($data->hasDatabaseCreation()) {
            $this->createNewDatabase($site, $server, $data);
        } elseif ($data->hasExistingDatabase()) {
            $this->linkExistingDatabase($site, $data);
        }
    }

    private function createNewDatabase(Site $site, Server $server, SiteCreationData $data): void
    {
        $database = Database::create([
            'server_id' => $server->id,
            'site_id' => $site->id,
            'name' => $data->getDatabaseName(),
            'status' => DatabaseStatus::Installing,
        ]);
        CreateDatabaseJob::dispatch($database);

        $databaseUser = DatabaseUser::create([
            'server_id' => $server->id,
            'username' => $data->getDatabaseUser(),
            'password' => $data->getDatabasePassword(),
            'status' => DatabaseUserStatus::Installing,
        ]);
        $databaseUser->databases()->attach($database->id);
        SyncDatabaseUserJob::dispatch($databaseUser);

        $site->update([
            'database_id' => $database->id,
            'database_user_id' => $databaseUser->id,
        ]);
    }

    private function linkExistingDatabase(Site $site, SiteCreationData $data): void
    {
        Database::where('id', $data->database_id)->update(['site_id' => $site->id]);
    }

    private function createPrimaryDomainRecord(Site $site): void
    {
        $site->domainRecords()->create([
            'name' => $site->domain,
            'type' => DomainRecordType::Primary,
            'status' => DomainRecordStatus::Pending,
            'www_redirect_type' => $site->www_redirect_type,
            'allow_wildcard' => $site->allow_wildcard,
        ]);
    }
}
