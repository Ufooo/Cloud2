<?php

namespace Nip\Site\Policies;

use App\Models\User;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Models\Site;

class SitePolicy
{
    public function update(?User $user, Site $site): bool
    {
        return $user?->can('update', $site->server)
            && $site->status === SiteStatus::Installed;
    }

    public function delete(?User $user, Site $site): bool
    {
        return $user?->can('update', $site->server)
            && $site->status !== SiteStatus::Installing;
    }

    public function deploy(?User $user, Site $site): bool
    {
        return $user?->can('update', $site->server)
            && $site->status === SiteStatus::Installed
            && (bool) $site->repository;
    }
}
