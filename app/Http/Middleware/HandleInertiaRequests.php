<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use Nip\SecurityMonitor\Models\SecurityScan;
use Nip\Site\Models\Site;
use Nip\Support\CacheKeys;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'counts' => [
                'sites' => fn () => Cache::remember(CacheKeys::SITES_COUNT, config('cache.ttl.counts'), fn () => Site::count()),
                'securityIssues' => fn () => Cache::remember(
                    CacheKeys::SECURITY_ISSUES_COUNT,
                    config('cache.ttl.counts'),
                    fn () => SecurityScan::query()
                        ->join('sites', 'security_scans.site_id', '=', 'sites.id')
                        ->whereIn('security_scans.id', function ($query) {
                            $query->selectRaw('MAX(id)')
                                ->from('security_scans')
                                ->groupBy('site_id');
                        })
                        ->where('sites.git_monitor_enabled', true)
                        ->where('security_scans.git_new_count', '>', 0)
                        ->count()
                ),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
