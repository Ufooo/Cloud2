<?php

namespace Nip\Site\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Nip\Site\Data\SitePermissionsData;

trait HasSitePermissions
{
    public function canBeUpdated(?User $user = null): bool
    {
        return Gate::forUser($user ?? request()->user())->allows('update', $this);
    }

    public function canBeDeleted(?User $user = null): bool
    {
        return Gate::forUser($user ?? request()->user())->allows('delete', $this);
    }

    public function canBeDeployed(?User $user = null): bool
    {
        return Gate::forUser($user ?? request()->user())->allows('deploy', $this);
    }

    public function getPermissions(?User $user = null): SitePermissionsData
    {
        return new SitePermissionsData(
            update: $this->canBeUpdated($user),
            delete: $this->canBeDeleted($user),
            deploy: $this->canBeDeployed($user),
        );
    }
}
