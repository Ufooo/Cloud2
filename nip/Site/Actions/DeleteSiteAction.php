<?php

namespace Nip\Site\Actions;

use Illuminate\Support\Facades\Bus;
use Nip\Database\Enums\DatabaseStatus;
use Nip\Database\Enums\DatabaseUserStatus;
use Nip\Database\Jobs\DeleteDatabaseJob;
use Nip\Database\Jobs\DeleteDatabaseUserJob;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Jobs\DeleteSiteJob;
use Nip\Site\Models\Site;

class DeleteSiteAction
{
    public function handle(Site $site, bool $deleteDatabase = false, bool $deleteDatabaseUser = false): void
    {
        $site->refresh();
        $site->load(['database', 'databaseUser']);

        $site->update(['status' => SiteStatus::Deleting]);

        $jobs = $this->buildJobChain($site, $deleteDatabase, $deleteDatabaseUser);

        Bus::chain($jobs)
            ->onQueue('provisioning')
            ->dispatch();
    }

    /**
     * @return array<object>
     */
    private function buildJobChain(Site $site, bool $deleteDatabase, bool $deleteDatabaseUser): array
    {
        $jobs = [];

        if ($deleteDatabase && $site->database) {
            $site->database->update(['status' => DatabaseStatus::Deleting]);
            $jobs[] = new DeleteDatabaseJob($site->database);
        }

        if ($deleteDatabaseUser && $site->databaseUser) {
            $site->databaseUser->update(['status' => DatabaseUserStatus::Deleting]);
            $jobs[] = new DeleteDatabaseUserJob($site->databaseUser);
        }

        $jobs[] = new DeleteSiteJob($site);

        return $jobs;
    }
}
