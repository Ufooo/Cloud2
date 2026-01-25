<?php

namespace Nip\Domain\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Domain\Enums\DomainRecordType;
use Nip\Domain\Http\Requests\StoreDomainRecordRequest;
use Nip\Domain\Http\Requests\UpdateDomainRecordRequest;
use Nip\Domain\Http\Resources\DomainRecordResource;
use Nip\Domain\Jobs\AddDomainJob;
use Nip\Domain\Jobs\DisableDomainJob;
use Nip\Domain\Jobs\EnableDomainJob;
use Nip\Domain\Jobs\RemoveDomainJob;
use Nip\Domain\Models\DomainRecord;
use Nip\Domain\Services\CloudflareService;
use Nip\Site\Data\SiteData;
use Nip\Site\Enums\WwwRedirectType;
use Nip\Site\Models\Site;

class DomainRecordController extends Controller
{
    public function index(Site $site): Response
    {
        Gate::authorize('view', $site->server);

        $domainRecords = $site->domainRecords()
            ->with(['certificate', 'site.server'])
            ->orderByRaw("FIELD(type, 'primary', 'alias', 'reverb')")
            ->orderBy('name')
            ->paginate(15);

        // Get certificates: site's own + linked via domain_records
        $linkedCertificateIds = $site->domainRecords()
            ->whereNotNull('certificate_id')
            ->pluck('certificate_id')
            ->unique();

        $certificates = \Nip\Domain\Models\Certificate::query()
            ->with('site.server')
            ->where(function ($query) use ($site, $linkedCertificateIds) {
                $query->where('site_id', $site->id)
                    ->orWhereIn('id', $linkedCertificateIds);
            })
            ->orderBy('active', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // Collect domains covered by any certificates (single query, no N+1)
        $allCertificateDomains = $site->certificates()
            ->pluck('domains')
            ->flatten()
            ->unique()
            ->values()
            ->all();

        // Store in request for DomainRecordResource to access
        request()->attributes->set('allCertificateDomains', $allCertificateDomains);

        $canUpdate = request()->user()?->can('update', $site->server);

        return Inertia::render('sites/domains/Index', [
            'site' => SiteData::fromModel($site->load('server')),
            'domainRecords' => DomainRecordResource::collection($domainRecords),
            'certificates' => \Nip\Domain\Http\Resources\CertificateResource::collection($certificates),
            'wwwRedirectTypes' => WwwRedirectType::options(),
            'certificateTypes' => \Nip\Domain\Enums\CertificateType::options(),
            'countries' => $this->getCountries(),
            'can' => [
                'domains' => ['create' => $canUpdate],
                'certificates' => ['create' => $canUpdate],
            ],
        ]);
    }

    public function store(StoreDomainRecordRequest $request, Site $site): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        $data = $request->validated();

        $domainRecord = $site->domainRecords()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'www_redirect_type' => $data['www_redirect_type'] ?? WwwRedirectType::FromWww,
            'allow_wildcard' => $data['allow_wildcard'] ?? false,
            'status' => DomainRecordStatus::Creating,
        ]);

        AddDomainJob::dispatch($domainRecord);

        return redirect()->route('sites.domains.index', $site)->with('success', "Domain {$domainRecord->name} has been added and is being configured.");
    }

    public function update(UpdateDomainRecordRequest $request, Site $site, DomainRecord $domainRecord): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($domainRecord->site_id === $site->id, 403);

        $data = $request->validated();

        $domainRecord->update([
            'www_redirect_type' => $data['www_redirect_type'] ?? $domainRecord->www_redirect_type,
            'allow_wildcard' => $data['allow_wildcard'] ?? $domainRecord->allow_wildcard,
        ]);

        return redirect()->route('sites.domains.index', $site)->with('success', "Domain {$domainRecord->name} has been updated.");
    }

    public function destroy(Site $site, DomainRecord $domainRecord): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($domainRecord->site_id === $site->id, 403);

        if ($domainRecord->isPrimary()) {
            return redirect()->route('sites.domains.index', $site)->with('error', 'Cannot delete the primary domain.');
        }

        $domainName = $domainRecord->name;
        $domainRecord->update(['status' => DomainRecordStatus::Removing]);

        RemoveDomainJob::dispatch($domainRecord);

        return redirect()->route('sites.domains.index', $site)->with('success', "Domain {$domainName} is being removed.");
    }

    public function markAsPrimary(Site $site, DomainRecord $domainRecord): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($domainRecord->site_id === $site->id, 403);

        if ($domainRecord->status !== DomainRecordStatus::Enabled) {
            return redirect()->route('sites.domains.index', $site)->with('error', 'Only enabled domains can be set as primary.');
        }

        $site->domainRecords()
            ->where('type', DomainRecordType::Primary)
            ->update(['type' => DomainRecordType::Alias]);

        $domainRecord->update(['type' => DomainRecordType::Primary]);

        return redirect()->route('sites.domains.index', $site)->with('success', "Domain {$domainRecord->name} is now the primary domain.");
    }

    public function enable(Site $site, DomainRecord $domainRecord): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($domainRecord->site_id === $site->id, 403);

        if ($domainRecord->status !== DomainRecordStatus::Disabled) {
            return redirect()->route('sites.domains.index', $site)->with('error', 'Only disabled domains can be enabled.');
        }

        $domainName = $domainRecord->name;
        $domainRecord->update(['status' => DomainRecordStatus::Enabling]);

        EnableDomainJob::dispatch($domainRecord);

        return redirect()->route('sites.domains.index', $site)->with('success', "Domain {$domainName} is being enabled.");
    }

    public function disable(Site $site, DomainRecord $domainRecord): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($domainRecord->site_id === $site->id, 403);

        if ($domainRecord->isPrimary()) {
            return redirect()->route('sites.domains.index', $site)->with('error', 'Cannot disable the primary domain.');
        }

        if ($domainRecord->status !== DomainRecordStatus::Enabled) {
            return redirect()->route('sites.domains.index', $site)->with('error', 'Only enabled domains can be disabled.');
        }

        $domainName = $domainRecord->name;
        $domainRecord->update(['status' => DomainRecordStatus::Disabling]);

        DisableDomainJob::dispatch($domainRecord);

        return redirect()->route('sites.domains.index', $site)->with('success', "Domain {$domainName} is being disabled.");
    }

    public function verifyDns(Site $site, DomainRecord $domainRecord): JsonResponse
    {
        Gate::authorize('view', $site->server);

        abort_unless($domainRecord->site_id === $site->id, 403);

        $cloudflare = new CloudflareService;
        $subdomains = $domainRecord->getOrCreateAcmeSubdomains();

        // Check each domain's DNS verification status
        $verifiedStatus = [];
        foreach ($subdomains as $domain => $subdomain) {
            $expectedTarget = "{$subdomain}.{$cloudflare->getAcmeDnsDomain()}";
            $verifiedStatus[$domain] = $cloudflare->verifyCnameRecord($domain, $expectedTarget);
        }

        $records = $domainRecord->buildVerificationRecords($subdomains, $verifiedStatus);

        return response()->json([
            'records' => $records,
            'allVerified' => collect($verifiedStatus)->every(fn ($v) => $v),
        ]);
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    private function getCountries(): array
    {
        return [
            ['code' => 'US', 'name' => 'United States'],
            ['code' => 'GB', 'name' => 'United Kingdom'],
            ['code' => 'DE', 'name' => 'Germany'],
            ['code' => 'FR', 'name' => 'France'],
            ['code' => 'NL', 'name' => 'Netherlands'],
            ['code' => 'AU', 'name' => 'Australia'],
            ['code' => 'CA', 'name' => 'Canada'],
            ['code' => 'HU', 'name' => 'Hungary'],
            ['code' => 'AT', 'name' => 'Austria'],
            ['code' => 'BE', 'name' => 'Belgium'],
            ['code' => 'CH', 'name' => 'Switzerland'],
            ['code' => 'CZ', 'name' => 'Czech Republic'],
            ['code' => 'DK', 'name' => 'Denmark'],
            ['code' => 'ES', 'name' => 'Spain'],
            ['code' => 'FI', 'name' => 'Finland'],
            ['code' => 'IE', 'name' => 'Ireland'],
            ['code' => 'IT', 'name' => 'Italy'],
            ['code' => 'JP', 'name' => 'Japan'],
            ['code' => 'NO', 'name' => 'Norway'],
            ['code' => 'PL', 'name' => 'Poland'],
            ['code' => 'PT', 'name' => 'Portugal'],
            ['code' => 'RO', 'name' => 'Romania'],
            ['code' => 'SE', 'name' => 'Sweden'],
            ['code' => 'SK', 'name' => 'Slovakia'],
            ['code' => 'SI', 'name' => 'Slovenia'],
        ];
    }
}
