<?php

namespace Nip\SecurityMonitor\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Site\Models\Site;

/** @mixin Site */
class SecuritySiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'domain' => $this->domain,
            'server' => [
                'id' => $this->server->id,
                'name' => $this->server->name,
                'slug' => $this->server->slug,
            ],
            'lastScan' => $this->latestSecurityScan
                ? SecurityScanResource::make($this->latestSecurityScan)
                : null,
            'gitMonitorEnabled' => $this->git_monitor_enabled,
            'securityScanIntervalMinutes' => $this->security_scan_interval_minutes,
            'securityScanRetentionDays' => $this->security_scan_retention_days,
        ];
    }
}
