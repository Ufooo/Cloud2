<?php

namespace Nip\Site\Services;

use Illuminate\Support\Facades\Bus;
use Nip\Site\Jobs\CreateIsolatedPhpFpmPoolJob;
use Nip\Site\Jobs\DeleteIsolatedPhpFpmPoolJob;
use Nip\Site\Jobs\UpdateSitePhpVersionJob;
use Nip\Site\Models\Site;

class SitePhpVersionService
{
    public function updatePhpVersion(Site $site, string $newVersion): void
    {
        $oldVersion = $site->php_version ?? $site->server->php_version;
        $jobs = [];

        if ($site->is_isolated) {
            if (! $this->hasOtherIsolatedSiteWithPhpVersion($site, $newVersion)) {
                $jobs[] = new CreateIsolatedPhpFpmPoolJob($site->server, $site->user, $newVersion);
            }
        }

        $jobs[] = new UpdateSitePhpVersionJob($site, $newVersion);

        if ($site->is_isolated) {
            if (! $this->hasOtherIsolatedSiteWithPhpVersion($site, $oldVersion)) {
                $jobs[] = new DeleteIsolatedPhpFpmPoolJob($site->server, $site->user, $oldVersion);
            }
        }

        Bus::chain($jobs)->onQueue('provisioning')->dispatch();
    }

    protected function hasOtherIsolatedSiteWithPhpVersion(Site $site, string $phpVersion): bool
    {
        return Site::query()
            ->where('server_id', $site->server_id)
            ->where('user', $site->user)
            ->where('is_isolated', true)
            ->where('php_version', $phpVersion)
            ->whereNot('id', $site->id)
            ->exists();
    }
}
