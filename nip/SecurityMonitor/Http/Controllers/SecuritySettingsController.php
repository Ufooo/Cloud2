<?php

namespace Nip\SecurityMonitor\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Nip\SecurityMonitor\Http\Requests\UpdateSecuritySettingsRequest;
use Nip\Site\Models\Site;

class SecuritySettingsController extends Controller
{
    public function update(UpdateSecuritySettingsRequest $request, Site $site): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        $site->update($request->validated());

        return redirect()->back()->with('success', 'Security settings updated.');
    }
}
