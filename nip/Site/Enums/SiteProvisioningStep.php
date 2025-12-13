<?php

namespace Nip\Site\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum SiteProvisioningStep: int
{
    case Initializing = 0;
    case ConfiguringNginx = 1;
    case CloningRepository = 2;
    case CreatingEnvironmentFile = 3;
    case InstallingDependencies = 4;
    case BuildingFrontendAssets = 5;
    case RunningMigrations = 6;
    case FinishingUp = 99;

    public function label(): string
    {
        return match ($this) {
            self::Initializing => 'Initializing site installation',
            self::ConfiguringNginx => 'Configuring Nginx',
            self::CloningRepository => 'Cloning repository',
            self::CreatingEnvironmentFile => 'Creating environment file',
            self::InstallingDependencies => 'Installing dependencies',
            self::BuildingFrontendAssets => 'Building frontend assets',
            self::RunningMigrations => 'Running database migrations',
            self::FinishingUp => 'Finishing up',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Initializing => 'Setting up the site directory structure and preparing for installation.',
            self::ConfiguringNginx => 'Creating and configuring the Nginx web server for your site.',
            self::CloningRepository => 'Cloning your application code from the Git repository.',
            self::CreatingEnvironmentFile => 'Creating the environment configuration file for your application.',
            self::InstallingDependencies => 'Installing Composer and NPM dependencies for your application.',
            self::BuildingFrontendAssets => 'Compiling and building frontend assets for production.',
            self::RunningMigrations => 'Running database migrations to set up your application schema.',
            self::FinishingUp => 'Finalizing the installation and performing cleanup tasks.',
        };
    }
}
