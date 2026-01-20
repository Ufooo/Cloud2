<?php

namespace Nip\SecurityMonitor\Data;

use Nip\Site\Models\Site;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MapOutputName(CamelCaseMapper::class)]
class SecuritySettingsData extends Data
{
    public function __construct(
        public bool $git_monitor_enabled,
        public int $security_scan_interval_minutes,
        public int $security_scan_retention_days,
    ) {}

    public static function fromSite(Site $site): self
    {
        return new self(
            git_monitor_enabled: $site->git_monitor_enabled,
            security_scan_interval_minutes: $site->security_scan_interval_minutes,
            security_scan_retention_days: $site->security_scan_retention_days,
        );
    }
}
