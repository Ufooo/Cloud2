<?php

namespace Nip\Site\Enums;

use App\Enums\Contracts\HasStatusBadge;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum DeployStatus: string implements HasStatusBadge
{
    case NeverDeployed = 'never_deployed';
    case Deploying = 'deploying';
    case Deployed = 'deployed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::NeverDeployed => 'Never deployed',
            self::Deploying => 'Deploying',
            self::Deployed => 'Deployed',
            self::Failed => 'Deployment failed',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Deployed => 'default',
            self::Deploying => 'secondary',
            self::NeverDeployed => 'outline',
            self::Failed => 'destructive',
        };
    }
}
