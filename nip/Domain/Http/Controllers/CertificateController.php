<?php

namespace Nip\Domain\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Enums\CertificateType;
use Nip\Domain\Http\Requests\StoreCertificateRequest;
use Nip\Domain\Jobs\DeleteCertificateJob;
use Nip\Domain\Jobs\DisableSslJob;
use Nip\Domain\Jobs\EnableSslJob;
use Nip\Domain\Jobs\ObtainCertificateJob;
use Nip\Domain\Jobs\RenewCertificateJob;
use Nip\Domain\Models\Certificate;
use Nip\Domain\Services\CloudflareService;
use Nip\Site\Models\Site;

class CertificateController extends Controller
{
    public function store(StoreCertificateRequest $request, Site $site): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        $data = $request->validated();
        $type = CertificateType::from($data['type']);

        $certificateData = [
            'type' => $type,
            'status' => CertificateStatus::Pending,
            'active' => false,
        ];

        // Handle domains based on type
        if ($type === CertificateType::LetsEncrypt) {
            $certificateData['verification_method'] = $data['verification_method'];
            $certificateData['key_algorithm'] = $data['key_algorithm'];
            $certificateData['isrg_root_chain'] = $data['isrg_root_chain'] ?? false;

            // DNS-01 requires verification records
            if ($data['verification_method'] === 'dns') {
                // Use the pre-generated subdomains from frontend (domain => subdomain)
                $acmeSubdomains = $data['acme_subdomains'];
                $certificateData['status'] = CertificateStatus::PendingVerification;
                $certificateData['acme_subdomains'] = $acmeSubdomains;
                $certificateData['verification_records'] = $this->generateVerificationRecordsFromSubdomains($acmeSubdomains);
                // Use all domains from acme_subdomains (includes www if redirect is set)
                $certificateData['domains'] = array_keys($acmeSubdomains);
            } else {
                // HTTP-01 only verifies the main domain
                $certificateData['domains'] = [$data['domain']];
            }
        } else {
            // For other types, single domain (plus SANs for CSR)
            $domains = [$data['domain']];
            if (! empty($data['sans'])) {
                $sanDomains = array_filter(array_map('trim', explode("\n", $data['sans'])));
                $domains = array_merge($domains, $sanDomains);
            }
            $certificateData['domains'] = array_unique($domains);
        }

        // Existing certificate specific
        if ($type === CertificateType::Existing) {
            $certificateData['certificate'] = $data['certificate'];
            $certificateData['private_key'] = $data['private_key'];
            $certificateData['status'] = CertificateStatus::Installing;

            // Auto-activate if requested
            if ($data['auto_activate'] ?? true) {
                $certificateData['active'] = true;
            }
        }

        // CSR specific
        if ($type === CertificateType::Csr) {
            $certificateData['csr_country'] = $data['csr_country'];
            $certificateData['csr_state'] = $data['csr_state'];
            $certificateData['csr_city'] = $data['csr_city'];
            $certificateData['csr_organization'] = $data['csr_organization'];
            $certificateData['csr_department'] = $data['csr_department'];
        }

        // Clone specific
        if ($type === CertificateType::Clone) {
            $sourceCert = Certificate::findOrFail($data['source_certificate_id']);
            $certificateData['source_certificate_id'] = $sourceCert->id;
            $certificateData['certificate'] = $sourceCert->certificate;
            $certificateData['private_key'] = $sourceCert->private_key;
            $certificateData['status'] = CertificateStatus::Installing;
        }

        $certificate = $site->certificates()->create($certificateData);

        // Dispatch job for Let's Encrypt HTTP-01 verification
        if ($type === CertificateType::LetsEncrypt && ($data['verification_method'] ?? 'http') === 'http') {
            $certificate->update(['status' => CertificateStatus::Installing]);
            ObtainCertificateJob::dispatch($certificate);
        }

        $successMessage = match ($type) {
            CertificateType::LetsEncrypt => ($data['verification_method'] ?? 'http') === 'dns'
                ? 'Certificate created. Please add the DNS records to verify domain ownership.'
                : 'Certificate is being installed.',
            CertificateType::Existing => 'Certificate has been installed.',
            CertificateType::Csr => 'Certificate signing request has been created.',
            CertificateType::Clone => 'Certificate is being cloned.',
        };

        return redirect()->route('sites.domains.index', $site)->with('success', $successMessage);
    }

    public function destroy(Site $site, Certificate $certificate): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        if ($certificate->active) {
            return redirect()->route('sites.domains.index', $site)->with('error', 'Cannot delete an active certificate. Please deactivate it first.');
        }

        $certificate->update(['status' => CertificateStatus::Removing]);

        DeleteCertificateJob::dispatch($certificate);

        return redirect()->route('sites.domains.index', $site)->with('success', 'Certificate is being removed.');
    }

    public function activate(Site $site, Certificate $certificate): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        if ($certificate->status !== CertificateStatus::Installed) {
            return redirect()->route('sites.domains.index', $site)->with('error', 'Only installed certificates can be activated.');
        }

        // Dispatch job to enable SSL on the server
        EnableSslJob::dispatch($certificate);

        return redirect()->route('sites.domains.index', $site)->with('success', 'Certificate is being activated.');
    }

    public function deactivate(Site $site, Certificate $certificate): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        if (! $certificate->active) {
            return redirect()->route('sites.domains.index', $site)->with('error', 'Certificate is already inactive.');
        }

        // Dispatch job to disable SSL on the server
        DisableSslJob::dispatch($certificate);

        return redirect()->route('sites.domains.index', $site)->with('success', 'Certificate is being deactivated.');
    }

    public function renew(Site $site, Certificate $certificate): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        if ($certificate->status !== CertificateStatus::Installed) {
            return redirect()->route('sites.domains.index', $site)->with('error', 'Only installed certificates can be renewed.');
        }

        $certificate->update(['status' => CertificateStatus::Renewing]);

        RenewCertificateJob::dispatch($certificate);

        return redirect()->route('sites.domains.index', $site)->with('success', 'Certificate is being renewed.');
    }

    public function verifyDns(Site $site, Certificate $certificate): JsonResponse
    {
        Gate::authorize('view', $site->server);

        $acmeSubdomains = $certificate->acme_subdomains ?? [];

        if (empty($acmeSubdomains)) {
            return response()->json([
                'verified' => false,
                'message' => 'Certificate does not require DNS verification.',
            ]);
        }

        $cloudflare = new CloudflareService;
        $acmeDnsDomain = config('services.cloudflare.acme_dns_domain');

        // Check each domain's DNS verification status
        $verifiedStatus = [];
        foreach ($acmeSubdomains as $domain => $subdomain) {
            $expectedTarget = "{$subdomain}.{$acmeDnsDomain}";
            $verifiedStatus[$domain] = $cloudflare->verifyCnameRecord($domain, $expectedTarget);
        }

        $allVerified = collect($verifiedStatus)->every(fn ($v) => $v);

        return response()->json([
            'verified' => $allVerified,
            'domains' => $verifiedStatus,
        ]);
    }

    public function obtainAfterVerification(Site $site, Certificate $certificate): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        if ($certificate->status !== CertificateStatus::PendingVerification) {
            return redirect()->route('sites.domains.index', $site)->with('error', 'Certificate is not pending verification.');
        }

        // Verify all DNS records before proceeding
        $acmeSubdomains = $certificate->acme_subdomains ?? [];

        if (empty($acmeSubdomains)) {
            return redirect()->route('sites.domains.index', $site)->with('error', 'No ACME subdomains configured for verification.');
        }

        $cloudflare = new CloudflareService;
        $acmeDnsDomain = config('services.cloudflare.acme_dns_domain');
        $failedDomains = [];

        foreach ($acmeSubdomains as $domain => $subdomain) {
            $expectedTarget = "{$subdomain}.{$acmeDnsDomain}";
            if (! $cloudflare->verifyCnameRecord($domain, $expectedTarget)) {
                $failedDomains[] = $domain;
            }
        }

        if (! empty($failedDomains)) {
            return redirect()->route('sites.domains.index', $site)->with('error', 'DNS verification failed for: '.implode(', ', $failedDomains));
        }

        $certificate->update(['status' => CertificateStatus::Installing]);
        ObtainCertificateJob::dispatch($certificate);

        return redirect()->route('sites.domains.index', $site)->with('success', 'DNS verified. Certificate is being installed.');
    }

    /**
     * Generate verification records for DNS-01 challenge from subdomains map.
     *
     * @param  array<string, string>  $acmeSubdomains  Map of domain => acme_subdomain
     * @return array<int, array{type: string, name: string, value: string, ttl: string, verified: bool}>
     */
    private function generateVerificationRecordsFromSubdomains(array $acmeSubdomains): array
    {
        $acmeDnsDomain = config('services.cloudflare.acme_dns_domain');
        $records = [];

        foreach ($acmeSubdomains as $domain => $subdomain) {
            $records[] = [
                'type' => 'CNAME',
                'name' => "_acme-challenge.{$domain}",
                'value' => "{$subdomain}.{$acmeDnsDomain}",
                'ttl' => '60 seconds',
                'verified' => false,
            ];
        }

        return $records;
    }
}
