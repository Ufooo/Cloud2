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
        $oldVersion = $site->php_version;
        $jobs = [];

        // Create new PHP-FPM pool if no other sites use this user with the new version
        if (! $this->hasOtherSiteWithPhpVersion($site, $newVersion)) {
            $jobs[] = new CreateIsolatedPhpFpmPoolJob($site->server, $site->user, $newVersion);
        }

        $jobs[] = new UpdateSitePhpVersionJob($site, $newVersion);

        // Delete old PHP-FPM pool if no other sites use this user with the old version
        if (! $this->hasOtherSiteWithPhpVersion($site, $oldVersion)) {
            $jobs[] = new DeleteIsolatedPhpFpmPoolJob($site->server, $site->user, $oldVersion);
        }

        Bus::chain($jobs)->onQueue('provisioning')->dispatch();
    }

    protected function hasOtherSiteWithPhpVersion(Site $site, string $phpVersion): bool
    {
        return Site::query()
            ->where('server_id', $site->server_id)
            ->where('user', $site->user)
            ->where('php_version', $phpVersion)
            ->whereNot('id', $site->id)
            ->exists();
    }
}
