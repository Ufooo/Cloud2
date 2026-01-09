<?php

namespace Nip\Scheduler\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Nip\Scheduler\Enums\JobStatus;
use Nip\Scheduler\Models\ScheduledJob;
use Nip\Site\Models\Site;

class DeleteSiteScheduledJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Site $site */
        $site = $this->route('site');

        /** @var ScheduledJob $job */
        $job = $this->route('job');

        if ($job->site_id !== $site->id) {
            return false;
        }

        return Gate::allows('update', $site->server);
    }

    protected function prepareForValidation(): void
    {
        /** @var ScheduledJob $job */
        $job = $this->route('job');

        if ($job->status === JobStatus::Installing) {
            abort(403, 'Cannot delete a job while installation is in progress.');
        }

        if ($job->status === JobStatus::Deleting) {
            abort(403, 'Job deletion is already in progress.');
        }
    }

    public function rules(): array
    {
        return [];
    }
}
