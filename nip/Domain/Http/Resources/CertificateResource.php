<?php

namespace Nip\Domain\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Models\Certificate;

/**
 * @mixin Certificate
 */
class CertificateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canUpdate = $request->user()?->can('update', $this->site?->server);
        $isInstalled = $this->status === CertificateStatus::Installed;

        $daysUntilExpiry = null;
        if ($this->expires_at) {
            // Positive for future dates, negative for past dates
            $daysUntilExpiry = (int) now()->diffInDays($this->expires_at, false);
        }

        return [
            'id' => $this->id,
            'siteId' => $this->site_id,
            'type' => $this->type->value,
            'displayableType' => $this->type->label(),
            'status' => $this->status->value,
            'displayableStatus' => $this->status->label(),
            'statusBadgeVariant' => $this->status->badgeVariant(),
            'domains' => $this->domains,
            'active' => $this->active,
            'path' => $this->path,
            'verificationMethod' => $this->verification_method,
            'verificationRecords' => $this->verification_records,
            'issuedAt' => $this->issued_at?->toISOString(),
            'issuedAtHuman' => $this->issued_at?->diffForHumans(),
            'expiresAt' => $this->expires_at?->toISOString(),
            'expiresAtHuman' => $this->expires_at ? 'in '.$this->expires_at->diffForHumans(syntax: true) : null,
            'isExpiringSoon' => $this->isExpiringSoon(),
            'daysUntilExpiry' => $daysUntilExpiry,
            'domainRecords' => $this->whenLoaded('domainRecords', fn () => DomainRecordResource::collection($this->domainRecords)),
            'createdAt' => $this->created_at?->toISOString(),
            'createdAtHuman' => $this->created_at?->diffForHumans(),
            'can' => [
                'delete' => $canUpdate && ! $this->active,
                'activate' => $canUpdate && $isInstalled && ! $this->active,
                'deactivate' => $canUpdate && $this->active,
                'renew' => $canUpdate && $isInstalled && $this->active,
            ],
        ];
    }
}
