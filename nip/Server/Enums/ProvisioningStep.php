<?php

namespace Nip\Server\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ProvisioningStep: int
{
    case WaitingForServer = 0;
    case PreparingServer = 1;
    case ConfiguringSwap = 2;
    case InstallingBaseDependencies = 3;
    case InstallingPhp = 4;
    case InstallingNginx = 5;
    case InstallingDatabase = 6;
    case InstallingRedis = 7;
    case MakingFinalTouches = 10;

    public function label(): string
    {
        return match ($this) {
            self::WaitingForServer => 'Waiting on your server to become ready',
            self::PreparingServer => 'Preparing your server',
            self::ConfiguringSwap => 'Configuring swap',
            self::InstallingBaseDependencies => 'Installing base dependencies',
            self::InstallingPhp => 'Installing PHP',
            self::InstallingNginx => 'Installing Nginx',
            self::InstallingDatabase => 'Installing database',
            self::InstallingRedis => 'Installing Redis',
            self::MakingFinalTouches => 'Making final touches',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::WaitingForServer => 'We are waiting to hear from your server to confirm the provisioning process has started.',
            self::PreparingServer => 'We\'re connecting to your server and ensuring that the required dependencies are ready.',
            self::ConfiguringSwap => 'Configuring your server\'s disk to have swap space.',
            self::InstallingBaseDependencies => 'Installing the basic dependencies required to provision your server.',
            self::InstallingPhp => 'PHP and its extensions will be installed.',
            self::InstallingNginx => 'Nginx will be installed and configured.',
            self::InstallingDatabase => 'Your database will be installed.',
            self::InstallingRedis => 'Redis will be installed for caching and queues.',
            self::MakingFinalTouches => 'Your server is almost ready. Just putting the final touches.',
        };
    }
}
