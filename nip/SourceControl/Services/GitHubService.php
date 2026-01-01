<?php

namespace Nip\SourceControl\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Nip\SourceControl\Models\SourceControl;

class GitHubService
{
    private const API_BASE_URL = 'https://api.github.com';

    private const API_VERSION = '2022-11-28';

    private const DEFAULT_PER_PAGE = 100;

    public function __construct(
        protected SourceControl $sourceControl,
    ) {}

    protected function client(): PendingRequest
    {
        return Http::withToken($this->sourceControl->token)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => self::API_VERSION,
            ])
            ->baseUrl(self::API_BASE_URL);
    }

    /**
     * @return array{login: string, id: int, name: ?string}|null
     */
    public function getUser(): ?array
    {
        $response = $this->client()->get('/user');

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * @return array<int, array{id: int, full_name: string, name: string, private: bool, default_branch: string, clone_url: string}>
     */
    public function getRepositories(int $perPage = self::DEFAULT_PER_PAGE): array
    {
        $repositories = [];
        $page = 1;

        do {
            $response = $this->client()->get('/user/repos', [
                'per_page' => $perPage,
                'page' => $page,
                'sort' => 'updated',
                'direction' => 'desc',
                'affiliation' => 'owner,collaborator,organization_member',
                'visibility' => 'all',
            ]);

            if (! $response->successful()) {
                break;
            }

            $data = $response->json();

            if (empty($data)) {
                break;
            }

            foreach ($data as $repo) {
                $repositories[] = [
                    'id' => $repo['id'],
                    'full_name' => $repo['full_name'],
                    'name' => $repo['name'],
                    'private' => $repo['private'],
                    'default_branch' => $repo['default_branch'],
                    'clone_url' => $repo['clone_url'],
                ];
            }

            $page++;
        } while (count($data) === $perPage);

        return $repositories;
    }

    /**
     * @return array<int, string>
     */
    public function getBranches(string $repository): array
    {
        $response = $this->client()->get("/repos/{$repository}/branches", [
            'per_page' => self::DEFAULT_PER_PAGE,
        ]);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json())
            ->pluck('name')
            ->toArray();
    }

    public function getCloneUrl(string $repository): string
    {
        $token = $this->sourceControl->token;

        return "https://oauth2:{$token}@github.com/{$repository}.git";
    }

    /**
     * Get the latest commit from a branch.
     *
     * @return array{sha: string, message: string, author: string}|null
     */
    public function getLatestCommit(string $repository, string $branch = 'main'): ?array
    {
        $response = $this->client()->get("/repos/{$repository}/commits/{$branch}");

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return [
            'sha' => $data['sha'] ?? null,
            'message' => $data['commit']['message'] ?? null,
            'author' => $data['commit']['author']['name'] ?? $data['author']['login'] ?? null,
        ];
    }

    public static function exchangeCodeForToken(string $code): array
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->post('https://github.com/login/oauth/access_token', [
            'client_id' => config('services.github.client_id'),
            'client_secret' => config('services.github.client_secret'),
            'code' => $code,
        ]);

        return $response->json();
    }

    public static function getAuthorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => config('services.github.client_id'),
            'redirect_uri' => url(config('services.github.redirect_uri')),
            'scope' => 'repo read:user read:org',
            'state' => $state,
        ]);

        return "https://github.com/login/oauth/authorize?{$params}";
    }
}
