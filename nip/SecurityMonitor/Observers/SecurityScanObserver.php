<?php

namespace Nip\SecurityMonitor\Observers;

use Nip\Support\CacheKeys;
use Nip\Support\Observers\CacheInvalidatingObserver;

class SecurityScanObserver extends CacheInvalidatingObserver
{
    /**
     * @return array<string>
     */
    protected function getCacheKeys(): array
    {
        return [CacheKeys::SECURITY_ISSUES_COUNT];
    }
}
