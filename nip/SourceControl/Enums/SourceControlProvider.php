<?php

namespace Nip\SourceControl\Enums;

enum SourceControlProvider: string
{
    case GitHub = 'github';
    case GitLab = 'gitlab';
    case Bitbucket = 'bitbucket';

    public function label(): string
    {
        return match ($this) {
            self::GitHub => 'GitHub',
            self::GitLab => 'GitLab',
            self::Bitbucket => 'Bitbucket',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::GitHub => 'github',
            self::GitLab => 'gitlab',
            self::Bitbucket => 'bitbucket',
        };
    }

    public function oauthUrl(): string
    {
        return match ($this) {
            self::GitHub => 'https://github.com/login/oauth/authorize',
            self::GitLab => 'https://gitlab.com/oauth/authorize',
            self::Bitbucket => 'https://bitbucket.org/site/oauth2/authorize',
        };
    }

    public function tokenUrl(): string
    {
        return match ($this) {
            self::GitHub => 'https://github.com/login/oauth/access_token',
            self::GitLab => 'https://gitlab.com/oauth/token',
            self::Bitbucket => 'https://bitbucket.org/site/oauth2/access_token',
        };
    }

    public function apiBaseUrl(): string
    {
        return match ($this) {
            self::GitHub => 'https://api.github.com',
            self::GitLab => 'https://gitlab.com/api/v4',
            self::Bitbucket => 'https://api.bitbucket.org/2.0',
        };
    }
}
