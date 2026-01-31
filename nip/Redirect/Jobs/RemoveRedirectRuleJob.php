<?php

namespace Nip\Redirect\Jobs;

use Nip\Redirect\Enums\RedirectRuleStatus;
use Nip\Redirect\Models\RedirectRule;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;

class RemoveRedirectRuleJob extends BaseProvisionJob
{
    public function __construct(
        public RedirectRule $redirectRule,
    ) {}

    protected function getResourceType(): string
    {
        return 'redirect';
    }

    protected function getResourceId(): ?int
    {
        return $this->redirectRule->id;
    }

    protected function getServer(): Server
    {
        return $this->redirectRule->site->server;
    }

    protected function generateScript(): string
    {
        $site = $this->redirectRule->site;

        // Get remaining rules (excluding the one being removed)
        $remainingRules = $site->redirectRules()
            ->where('id', '!=', $this->redirectRule->id)
            ->where('status', '!=', RedirectRuleStatus::Removing)
            ->get();

        return view('provisioning.scripts.redirect.sync', [
            'site' => $site,
            'rules' => $remainingRules,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->redirectRule->delete();
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->redirectRule->update([
            'status' => RedirectRuleStatus::Failed,
        ]);
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'provision',
            'redirect',
            'redirect:'.$this->redirectRule->id,
            'site:'.$this->redirectRule->site_id,
            'server:'.$this->redirectRule->site->server_id,
            'remove',
        ];
    }
}
