<?php

namespace Nip\Deployment\Enums;

enum DeploymentStatus: string
{
    case Pending = 'pending';
    case Deploying = 'deploying';
    case Finished = 'finished';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Deploying => 'Deploying',
            self::Finished => 'Finished',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Deploying => 'blue',
            self::Finished => 'green',
            self::Failed => 'red',
        };
    }
}
