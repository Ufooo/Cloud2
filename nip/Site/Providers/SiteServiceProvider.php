<?php

namespace Nip\Site\Providers;

use Illuminate\Support\Facades\Gate;
use Nip\Site\Models\Site;
use Nip\Site\Observers\SiteObserver;
use Nip\Site\Policies\SitePolicy;
use Nip\Support\Providers\NipServiceProvider;

class SiteServiceProvider extends NipServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Site::observe(SiteObserver::class);

        Gate::policy(Site::class, SitePolicy::class);
    }

    protected function modulePath(): string
    {
        return __DIR__.'/..';
    }
}
