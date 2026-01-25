<?php

namespace Nip\Domain\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Domain\Models\DomainRecord;

/**
 * @mixin DomainRecord
 */
class DomainRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canUpdate = $request->user()?->can('update', $this->site->server);
        $isEnabled = $this->status === DomainRecordStatus::Enabled;

        // Use pre-computed certificate domains from request attributes (no N+1)
        $allCertificateDomains = $request->attributes->get('allCertificateDomains', []);
        $hasCertificate = in_array($this->name, $allCertificateDomains);

        return [
            'id' => $this->id,
            'siteId' => $this->site_id,
            'certificateId' => $this->certificate_id,
            'hasCertificate' => $hasCertificate,
            'isSecured' => $this->certificate_id && $this->certificate?->status === CertificateStatus::Installed,
            'certificateType' => $this->certificate?->type?->label(),
            'name' => $this->name,
            'type' => $this->type->value,
            'displayableType' => $this->type->label(),
            'status' => $this->status->value,
            'displayableStatus' => $this->status->label(),
            'statusBadgeVariant' => $this->status->badgeVariant(),
            'wwwRedirectType' => $this->www_redirect_type->value,
            'wwwRedirectTypeLabel' => $this->www_redirect_type->label(),
            'allowWildcard' => $this->allow_wildcard,
            'acmeSubdomains' => $this->acme_subdomains ?? [],
            'verificationRecords' => $this->getVerificationRecords(),
            'isPrimary' => $this->isPrimary(),
            'url' => $this->getUrl(),
            'certificate' => $this->whenLoaded('certificate', fn () => new CertificateResource($this->certificate)),
            'createdAt' => $this->created_at?->toISOString(),
            'can' => [
                'update' => $canUpdate && $isEnabled,
                'delete' => $canUpdate && ! $this->isPrimary(),
                'makePrimary' => $canUpdate && $isEnabled && ! $this->isPrimary(),
            ],
        ];
    }
}
