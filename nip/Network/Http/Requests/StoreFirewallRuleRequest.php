<?php

namespace Nip\Network\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nip\Network\Enums\RuleType;

class StoreFirewallRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('server'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'port' => ['nullable', 'string', 'max:10'],
            'ip_address' => ['nullable', 'string', 'ip'],
            'type' => ['required', Rule::enum(RuleType::class)],
        ];
    }
}
