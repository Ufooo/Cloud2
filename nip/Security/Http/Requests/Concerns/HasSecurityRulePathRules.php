<?php

namespace Nip\Security\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Nip\Security\Models\SecurityRule;
use Nip\Site\Models\Site;

/**
 * The path of a security rule ends up verbatim inside an nginx location, so it is
 * normalized in exactly one place (here) and validated before it can reach a
 * provisioning script.
 */
trait HasSecurityRulePathRules
{
    /**
     * @return array<int, string>
     */
    protected function pathRules(): array
    {
        return ['nullable', 'string', 'max:255', 'regex:'.SecurityRule::PATH_PATTERN];
    }

    /**
     * @return array<string, string>
     */
    protected function pathMessages(): array
    {
        return [
            'path.regex' => 'The path must look like /admin or /admin/area: every segment is made of letters, numbers and . _ ~ - and may not be empty or start with a dot.',
        ];
    }

    /**
     * Merge repeated slashes (nginx does the same to the request URI) and cut the trailing one,
     * in that order; an empty or root path protects the whole site and is stored as null.
     * Only a submitted path is touched, so a PATCH without one keeps the stored value.
     */
    protected function prepareForValidation(): void
    {
        $path = $this->input('path');

        if ($this->has('path') && is_string($path)) {
            $path = Str::of($path)->trim()->replaceMatches('#/{2,}#', '/')->rtrim('/')->toString();

            $this->merge(['path' => $path === '' ? null : $path]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validatePathIsFree($validator);
        });
    }

    /**
     * A site may have one rule without a path and each path only once, otherwise nginx
     * would get a duplicate auth_basic directive or location and refuse the configuration.
     */
    private function validatePathIsFree(Validator $validator): void
    {
        $rule = $this->route('rule');
        $site = $this->route('site');
        $isUpdate = $rule instanceof SecurityRule;

        $pathIsUntouched = $isUpdate && ! $this->has('path');

        if (! $site instanceof Site || $pathIsUntouched || $validator->errors()->has('path')) {
            return;
        }

        $path = $this->input('path');

        $isTaken = SecurityRule::query()
            ->where('site_id', $site->getKey())
            ->where('path', $path)
            ->when($isUpdate, fn (Builder $query) => $query->whereKeyNot($rule->getKey()))
            ->exists();

        if ($isTaken) {
            $validator->errors()->add('path', $path === null
                ? 'This site already has a rule that protects the whole site.'
                : 'This path is already protected by another rule of this site.');
        }
    }
}
