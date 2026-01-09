<?php

namespace Nip\Shared\Traits;

trait HasSiteValidationMessages
{
    /**
     * @return array<string, string>
     */
    protected function siteValidationMessages(): array
    {
        return [
            'domain.regex' => 'The domain format is invalid.',
            'domain.unique' => 'This domain is already configured on this server.',
            'user.exists' => 'The selected user does not exist on this server.',
            'php_version' => 'The selected PHP version is invalid.',
            'root_directory.regex' => 'The root directory must start with /.',
            'web_directory.regex' => 'The web directory must start with /.',
            'repository.regex' => 'The repository must be a valid git URL (git@ or https://).',
        ];
    }
}
