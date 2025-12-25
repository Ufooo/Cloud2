<?php

namespace Nip\Scheduler\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Nip\Scheduler\Http\Requests\Concerns\HasScheduledJobRules;
use Nip\Server\Models\Server;
use Nip\Site\Models\Site;

class StoreSiteScheduledJobRequest extends FormRequest
{
    use HasScheduledJobRules;

    public function authorize(): bool
    {
        /** @var Site $site */
        $site = $this->route('site');

        return Gate::allows('update', $site->server);
    }

    protected function getServer(): Server
    {
        /** @var Site $site */
        $site = $this->route('site');

        return $site->server;
    }
}
