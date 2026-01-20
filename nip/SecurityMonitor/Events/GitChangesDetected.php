<?php

namespace Nip\SecurityMonitor\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Nip\SecurityMonitor\Models\SecurityScan;
use Nip\Site\Models\Site;

class GitChangesDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SecurityScan $scan,
        public Site $site,
        public int $newChangesCount,
    ) {}
}
