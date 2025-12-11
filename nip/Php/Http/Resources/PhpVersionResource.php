<?php

namespace Nip\Php\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Php\Enums\PhpVersionStatus;
use Nip\Php\Models\PhpVersion;

/**
 * @mixin PhpVersion
 */
class PhpVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canUpdate = $request->user()->can('update', $this->server);
        $isInstalled = $this->status === PhpVersionStatus::Installed;

        return [
            'id' => $this->id,
            'version' => $this->version,
            'isCliDefault' => $this->is_cli_default,
            'isSiteDefault' => $this->is_site_default,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'statusBadgeVariant' => $this->status->badgeVariant(),
            'createdAt' => $this->created_at?->format('M j, Y'),
            'can' => [
                'delete' => $canUpdate && $isInstalled && ! $this->is_cli_default && ! $this->is_site_default,
                'setCliDefault' => $canUpdate && $isInstalled && ! $this->is_cli_default,
                'setSiteDefault' => $canUpdate && $isInstalled && ! $this->is_site_default,
            ],
        ];
    }
}
