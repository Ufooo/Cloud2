<?php

namespace Nip\Scheduler\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Nip\Scheduler\Http\Requests\Concerns\HasScheduledJobRules;
use Nip\Server\Models\Server;

class StoreScheduledJobRequest extends FormRequest
{
    use HasScheduledJobRules;

    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('server'));
    }

    protected function getServer(): Server
    {
        /** @var Server */
        return $this->route('server');
    }
}
