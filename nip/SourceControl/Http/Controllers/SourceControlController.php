<?php

namespace Nip\SourceControl\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Nip\SourceControl\Enums\SourceControlProvider;
use Nip\SourceControl\Models\SourceControl;
use Nip\SourceControl\Services\GitHubService;

class SourceControlController extends Controller
{
    public function index(): Response
    {
        $sourceControls = SourceControl::query()
            ->where('user_id', auth()->id())
            ->get()
            ->map(fn (SourceControl $sc) => [
                'id' => $sc->id,
                'provider' => $sc->provider->value,
                'providerLabel' => $sc->provider->label(),
                'name' => $sc->name,
                'connectedAt' => $sc->created_at->toISOString(),
            ]);

        return Inertia::render('source-control/Index', [
            'sourceControls' => $sourceControls,
            'providers' => collect(SourceControlProvider::cases())->map(fn ($p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
        ]);
    }

    public function redirect(string $provider): RedirectResponse
    {
        $providerEnum = SourceControlProvider::tryFrom($provider);

        abort_if($providerEnum === null, 404, 'Unknown provider');

        $state = Str::random(40);
        session(['source_control_state' => $state]);

        $url = match ($providerEnum) {
            SourceControlProvider::GitHub => GitHubService::getAuthorizationUrl($state),
            default => abort(501, 'Provider not implemented yet'),
        };

        return redirect()->away($url);
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $providerEnum = SourceControlProvider::tryFrom($provider);

        abort_if($providerEnum === null, 404, 'Unknown provider');

        // Verify state
        $state = $request->query('state');
        $sessionState = session('source_control_state');
        session()->forget('source_control_state');

        if (! $state || $state !== $sessionState) {
            return redirect()
                ->route('source-control.index')
                ->with('error', 'Invalid state. Please try again.');
        }

        $code = $request->query('code');

        if (! $code) {
            return redirect()
                ->route('source-control.index')
                ->with('error', 'Authorization was cancelled.');
        }

        try {
            $tokenData = match ($providerEnum) {
                SourceControlProvider::GitHub => GitHubService::exchangeCodeForToken($code),
                default => abort(501, 'Provider not implemented yet'),
            };

            // Create temporary model to use service
            $tempSourceControl = new SourceControl(['token' => $tokenData['access_token']]);

            // Get user info from provider
            $userInfo = match ($providerEnum) {
                SourceControlProvider::GitHub => (new GitHubService($tempSourceControl))->getUser(),
                default => null,
            };

            if (! $userInfo) {
                return redirect()
                    ->route('source-control.index')
                    ->with('error', 'Failed to fetch user info from provider.');
            }

            // Create or update source control record
            SourceControl::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'provider' => $providerEnum,
                ],
                [
                    'name' => $userInfo['login'] ?? $userInfo['username'] ?? 'Unknown',
                    'provider_user_id' => (string) ($userInfo['id'] ?? ''),
                    'token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? null,
                    'token_expires_at' => isset($tokenData['expires_in'])
                        ? now()->addSeconds($tokenData['expires_in'])
                        : null,
                ]
            );

            return redirect()
                ->route('source-control.index')
                ->with('success', "{$providerEnum->label()} connected successfully!");

        } catch (\Exception $e) {
            report($e);

            return redirect()
                ->route('source-control.index')
                ->with('error', 'Failed to connect. Please try again.');
        }
    }

    public function destroy(SourceControl $sourceControl): RedirectResponse
    {
        abort_if($sourceControl->user_id !== auth()->id(), 403);

        $providerLabel = $sourceControl->provider->label();
        $sourceControl->delete();

        return redirect()
            ->route('source-control.index')
            ->with('success', "{$providerLabel} disconnected successfully.");
    }

    public function repositories(SourceControl $sourceControl): JsonResponse
    {
        abort_if($sourceControl->user_id !== auth()->id(), 403);

        $service = new GitHubService($sourceControl);
        $repositories = $service->getRepositories();

        return response()->json($repositories);
    }

    public function branches(SourceControl $sourceControl, string $repository): JsonResponse
    {
        abort_if($sourceControl->user_id !== auth()->id(), 403);

        $service = new GitHubService($sourceControl);
        $branches = $service->getBranches($repository);

        return response()->json($branches);
    }
}
