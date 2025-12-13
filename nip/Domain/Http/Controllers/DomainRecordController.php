<?php

namespace Nip\Domain\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Domain\Enums\DomainRecordType;
use Nip\Domain\Http\Requests\StoreDomainRecordRequest;
use Nip\Domain\Http\Requests\UpdateDomainRecordRequest;
use Nip\Domain\Http\Resources\DomainRecordResource;
use Nip\Domain\Models\DomainRecord;
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

        $certificates = $site->certificates()
            ->orderBy('active', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $canUpdate = request()->user()?->can('update', $site->server);

        return Inertia::render('sites/domains/Index', [
            'site' => [
                'id' => $site->id,
                'slug' => $site->slug,
                'domain' => $site->domain,
            ],
            'domainRecords' => DomainRecordResource::collection($domainRecords),
            'certificates' => \Nip\Domain\Http\Resources\CertificateResource::collection($certificates),
            'wwwRedirectTypes' => WwwRedirectType::options(),
            'certificateTypes' => \Nip\Domain\Enums\CertificateType::options(),
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
            'status' => DomainRecordStatus::Pending,
        ]);

        return redirect()->route('sites.domains.index', $site)
            ->with('success', "Domain {$domainRecord->name} has been added and is being configured.");
    }

    public function update(UpdateDomainRecordRequest $request, Site $site, DomainRecord $domainRecord): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        $data = $request->validated();

        $domainRecord->update([
            'www_redirect_type' => $data['www_redirect_type'] ?? $domainRecord->www_redirect_type,
            'allow_wildcard' => $data['allow_wildcard'] ?? $domainRecord->allow_wildcard,
        ]);

        return redirect()->route('sites.domains.index', $site)
            ->with('success', "Domain {$domainRecord->name} has been updated.");
    }

    public function destroy(Site $site, DomainRecord $domainRecord): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        if ($domainRecord->isPrimary()) {
            return redirect()->route('sites.domains.index', $site)
                ->with('error', 'Cannot delete the primary domain.');
        }

        $domainName = $domainRecord->name;
        $domainRecord->update(['status' => DomainRecordStatus::Removing]);

        return redirect()->route('sites.domains.index', $site)
            ->with('success', "Domain {$domainName} is being removed.");
    }

    public function markAsPrimary(Site $site, DomainRecord $domainRecord): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        if ($domainRecord->status !== DomainRecordStatus::Enabled) {
            return redirect()->route('sites.domains.index', $site)
                ->with('error', 'Only enabled domains can be set as primary.');
        }

        $site->domainRecords()
            ->where('type', DomainRecordType::Primary)
            ->update(['type' => DomainRecordType::Alias]);

        $domainRecord->update(['type' => DomainRecordType::Primary]);

        return redirect()->route('sites.domains.index', $site)
            ->with('success', "Domain {$domainRecord->name} is now the primary domain.");
    }
}
