<?php

namespace Nip\Domain\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Http\Requests\StoreCertificateRequest;
use Nip\Domain\Models\Certificate;
use Nip\Site\Models\Site;

class CertificateController extends Controller
{
    public function store(StoreCertificateRequest $request, Site $site): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        $data = $request->validated();

        $certificate = $site->certificates()->create([
            'type' => $data['type'],
            'domains' => $data['domains'],
            'certificate' => $data['certificate'] ?? null,
            'private_key' => $data['private_key'] ?? null,
            'status' => CertificateStatus::Pending,
            'active' => false,
        ]);

        return redirect()->route('sites.domains.index', $site)
            ->with('success', 'Certificate is being installed.');
    }

    public function destroy(Site $site, Certificate $certificate): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        if ($certificate->active) {
            return redirect()->route('sites.domains.index', $site)
                ->with('error', 'Cannot delete an active certificate. Please deactivate it first.');
        }

        $certificate->update(['status' => CertificateStatus::Removing]);

        return redirect()->route('sites.domains.index', $site)
            ->with('success', 'Certificate is being removed.');
    }

    public function activate(Site $site, Certificate $certificate): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        if ($certificate->status !== CertificateStatus::Installed) {
            return redirect()->route('sites.domains.index', $site)
                ->with('error', 'Only installed certificates can be activated.');
        }

        $site->certificates()->update(['active' => false]);

        $certificate->update(['active' => true]);

        return redirect()->route('sites.domains.index', $site)
            ->with('success', 'Certificate has been activated.');
    }

    public function deactivate(Site $site, Certificate $certificate): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        $certificate->update(['active' => false]);

        return redirect()->route('sites.domains.index', $site)
            ->with('success', 'Certificate has been deactivated.');
    }

    public function renew(Site $site, Certificate $certificate): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        if ($certificate->status !== CertificateStatus::Installed || ! $certificate->active) {
            return redirect()->route('sites.domains.index', $site)
                ->with('error', 'Only active, installed certificates can be renewed.');
        }

        $certificate->update(['status' => CertificateStatus::Renewing]);

        return redirect()->route('sites.domains.index', $site)
            ->with('success', 'Certificate is being renewed.');
    }
}
