<?php

namespace Nip\Domain\Jobs\Concerns;

use Nip\Server\Models\Server;

trait HandlesCertificateProvision
{
    protected function getResourceType(): string
    {
        return 'certificate';
    }

    protected function getResourceId(): ?int
    {
        return $this->certificate->id;
    }

    protected function getServer(): Server
    {
        return $this->certificate->site->server;
    }

    protected function parseCertificateExpiry(?string $output): ?\DateTime
    {
        if (! $output) {
            return now()->addDays(90);
        }

        if (preg_match('/CERT_EXPIRES:(\d{4}-\d{2}-\d{2})/', $output, $matches)) {
            return new \DateTime($matches[1]);
        }

        // Default Let's Encrypt validity is 90 days
        return now()->addDays(90);
    }

    protected function getCertificateViewData(): array
    {
        $site = $this->certificate->site;

        return [
            'site' => $site,
            'certificate' => $this->certificate,
            'domains' => $this->certificate->domains,
            'keyAlgorithm' => $this->certificate->key_algorithm ?? 'ecdsa',
            'siteUser' => $site->user,
            'certPath' => $this->certificate->getCertPath(),
            'siteConfDir' => $this->certificate->getSiteConfDir(),
            'wellknown' => "/home/{$site->user}/.letsencrypt",
        ];
    }

    protected function getBaseTags(): array
    {
        return [
            'provision',
            'certificate',
            'certificate:'.$this->certificate->id,
            'site:'.$this->certificate->site_id,
            'server:'.$this->certificate->site->server_id,
        ];
    }
}
