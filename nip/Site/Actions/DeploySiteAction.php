<?php

namespace Nip\Site\Actions;

use Nip\Deployment\Enums\DeploymentStatus;
use Nip\Deployment\Models\Deployment;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Jobs\DeploySiteJob;
use Nip\Site\Models\Site;
use Nip\SourceControl\Services\GitHubService;

class DeploySiteAction
{
    public function handle(Site $site, ?int $userId = null): Deployment
    {
        $branch = $site->branch ?? 'main';
        $commitInfo = $this->fetchCommitInfo($site, $branch);

        $deployment = Deployment::create([
            'site_id' => $site->id,
            'user_id' => $userId ?? auth()->id(),
            'status' => DeploymentStatus::Deploying,
            'branch' => $branch,
            'commit_hash' => $commitInfo['sha'] ?? null,
            'commit_message' => $commitInfo['message'] ?? null,
            'commit_author' => $commitInfo['author'] ?? null,
            'callback_token' => bin2hex(random_bytes(32)),
            'started_at' => now(),
        ]);

        $site->update(['deploy_status' => DeployStatus::Deploying]);

        DeploySiteJob::dispatch($site, $deployment);

        return $deployment;
    }

    /**
     * @return array{sha?: string, message?: string, author?: string}|null
     */
    private function fetchCommitInfo(Site $site, string $branch): ?array
    {
        if (! $site->sourceControl || ! $site->repository) {
            return null;
        }

        $github = new GitHubService($site->sourceControl);

        return $github->getLatestCommit($site->repository, $branch);
    }
}
