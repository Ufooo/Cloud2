<?php

namespace Nip\Domain\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Enums\CertificateType;
use Nip\Domain\Models\Certificate;

/**
 * @mixin Certificate
 */
class ExpiringCertificateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $daysUntilExpiry = null;
        if ($this->expires_at) {
            $daysUntilExpiry = (int) now()->diffInDays($this->expires_at, false);
        }

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'displayableType' => $this->type->label(),
            'siteId' => $this->site_id,
            'siteSlug' => $this->site?->slug,
            'siteDomain' => $this->site?->domain,
            'domains' => $this->domains,
            'domainsFormatted' => $this->formatDomains($this->domains),
            'expiresAt' => $this->expires_at?->toISOString(),
            'expiresAtHuman' => $this->expires_at ? 'in '.$this->expires_at->diffForHumans(syntax: true) : null,
            'daysUntilExpiry' => $daysUntilExpiry,
            'isRenewing' => $this->status === CertificateStatus::Renewing,
            'canRenew' => $this->type === CertificateType::LetsEncrypt && $this->status !== CertificateStatus::Renewing,
        ];
    }

    /**
     * @param  array<string>  $domains
     */
    private function formatDomains(array $domains): string
    {
        if (count($domains) <= 2) {
            return implode(', ', $domains);
        }

        return $domains[0].', +'.(count($domains) - 1);
    }
}
