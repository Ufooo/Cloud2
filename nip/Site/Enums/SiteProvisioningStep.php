<?php

namespace Nip\Site\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum SiteProvisioningStep: int
{
    // System-level steps (root)
    case Initializing = 0;
    case CreatingSiteConfigDirectory = 1;
    case CreatingNginxServerBlock = 2;
    case ConfiguringWwwRedirect = 3;
    case EnablingNginxSite = 4;
    case CreatingPhpFpmPool = 5;
    case RestartingServices = 6;
    case CreatingLogrotateConfig = 7;

    // Application-level steps (user)
    case CreatingSiteDirectory = 10;
    case CloningRepository = 11;
    case ConfiguringEnvironment = 12;
    case InstallingComposerDependencies = 13;
    case BuildingFrontendAssets = 14;
    case RunningMigrations = 15;
    case FinalizingSite = 99;

    public function label(): string
    {
        return match ($this) {
            self::Initializing => 'Initializing',
            self::CreatingSiteConfigDirectory => 'Creating config directory',
            self::CreatingNginxServerBlock => 'Creating Nginx server block',
            self::ConfiguringWwwRedirect => 'Configuring www redirect',
            self::EnablingNginxSite => 'Enabling Nginx site',
            self::CreatingPhpFpmPool => 'Creating PHP-FPM pool',
            self::RestartingServices => 'Restarting services',
            self::CreatingLogrotateConfig => 'Creating logrotate config',
            self::CreatingSiteDirectory => 'Creating site directory',
            self::CloningRepository => 'Cloning repository',
            self::ConfiguringEnvironment => 'Configuring environment',
            self::InstallingComposerDependencies => 'Installing Composer dependencies',
            self::BuildingFrontendAssets => 'Building frontend assets',
            self::RunningMigrations => 'Running migrations',
            self::FinalizingSite => 'Finalizing site',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Initializing => 'Preparing for site installation.',
            self::CreatingSiteConfigDirectory => 'Creating the Nginx configuration directory structure.',
            self::CreatingNginxServerBlock => 'Creating the Nginx server block configuration.',
            self::ConfiguringWwwRedirect => 'Configuring www to non-www redirect rules.',
            self::EnablingNginxSite => 'Enabling the site in Nginx.',
            self::CreatingPhpFpmPool => 'Creating an isolated PHP-FPM pool for the site.',
            self::RestartingServices => 'Restarting Nginx and PHP-FPM services.',
            self::CreatingLogrotateConfig => 'Creating logrotate configuration for log files.',
            self::CreatingSiteDirectory => 'Creating the site directory structure.',
            self::CloningRepository => 'Cloning your application code from the Git repository.',
            self::ConfiguringEnvironment => 'Creating the environment configuration file.',
            self::InstallingComposerDependencies => 'Installing Composer dependencies.',
            self::BuildingFrontendAssets => 'Building frontend assets for production.',
            self::RunningMigrations => 'Running database migrations.',
            self::FinalizingSite => 'Finalizing the installation and activating the site.',
        };
    }
}
