<?php

use Nip\Site\Enums\SiteType;

it('generates default deploy script for laravel site', function () {
    $script = SiteType::Laravel->defaultDeployScript();

    expect($script)
        ->toContain('Zero-Downtime Deployment for Laravel')
        ->toContain('$NIP_NEW_RELEASE_PATH')
        ->toContain('$NIP_SITE_ROOT')
        ->toContain('$NIP_SITE_BRANCH')
        ->toContain('git clone')
        ->toContain('$NIP_COMPOSER install')
        ->toContain('$NIP_PHP artisan optimize')
        ->toContain('$NIP_PHP artisan migrate --force')
        ->toContain('npm run build')
        ->toContain('ln -s "$NIP_NEW_RELEASE_PATH" "$NIP_SITE_ROOT/current-temp" && mv -Tf "$NIP_SITE_ROOT/current-temp" "$NIP_SITE_ROOT/current"');
});

it('generates default deploy script for statamic site', function () {
    $script = SiteType::Statamic->defaultDeployScript();

    expect($script)
        ->toContain('Zero-Downtime Deployment for Statamic')
        ->toContain('$NIP_PHP artisan statamic:stache:warm')
        ->toContain('$NIP_PHP artisan migrate --force')
        ->toContain('ln -s "$NIP_NEW_RELEASE_PATH" "$NIP_SITE_ROOT/current-temp" && mv -Tf "$NIP_SITE_ROOT/current-temp" "$NIP_SITE_ROOT/current"');
});

it('generates default deploy script for symfony site', function () {
    $script = SiteType::Symfony->defaultDeployScript();

    expect($script)
        ->toContain('Zero-Downtime Deployment for Symfony')
        ->toContain('bin/console cache:clear')
        ->toContain('doctrine:migrations:migrate')
        ->toContain('ln -s "$NIP_NEW_RELEASE_PATH" "$NIP_SITE_ROOT/current-temp" && mv -Tf "$NIP_SITE_ROOT/current-temp" "$NIP_SITE_ROOT/current"');
});

it('generates default deploy script for nextjs site', function () {
    $script = SiteType::NextJs->defaultDeployScript();

    expect($script)
        ->toContain('npm ci || npm install')
        ->toContain('npm run build')
        ->not->toContain('$NIP_PHP');
});

it('generates basic deploy script for other site types', function () {
    $script = SiteType::Other->defaultDeployScript();

    expect($script)
        ->toContain('cd $NIP_SITE_PATH')
        ->toContain('git pull origin $NIP_SITE_BRANCH')
        ->not->toContain('composer')
        ->not->toContain('npm');
});

it('all site types have a default deploy script', function () {
    $typesWithoutGitDeploy = [SiteType::WordPress, SiteType::PhpMyAdmin];
    $zeroDowntimeTypes = [SiteType::Laravel, SiteType::Statamic, SiteType::Symfony];

    foreach (SiteType::cases() as $type) {
        $script = $type->defaultDeployScript();

        expect($script)->toBeString();

        // WordPress and phpMyAdmin don't use git-based deployments by default
        if (in_array($type, $typesWithoutGitDeploy)) {
            expect($script)->toBeEmpty();
        } elseif (in_array($type, $zeroDowntimeTypes)) {
            expect($script)
                ->not->toBeEmpty()
                ->toContain('$NIP_NEW_RELEASE_PATH');
        } else {
            expect($script)
                ->not->toBeEmpty()
                ->toContain('$NIP_SITE_PATH');
        }
    }
});

it('wordpress deploy script is empty by default', function () {
    $script = SiteType::WordPress->defaultDeployScript();

    expect($script)
        ->toBeString()
        ->toBeEmpty();
});
