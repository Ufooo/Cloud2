<?php

namespace Nip\Site\Observers;

use Nip\Support\CacheKeys;
use Nip\Support\Observers\CacheInvalidatingObserver;

class SiteObserver extends CacheInvalidatingObserver
{
    /**
     * @return array<string>
     */
    protected function getCacheKeys(): array
    {
        return [CacheKeys::SITES_COUNT];
    }
}
