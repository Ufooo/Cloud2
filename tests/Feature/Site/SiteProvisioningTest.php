<?php

use Illuminate\Support\Facades\Bus;
use Nip\Server\Models\Server;
use Nip\Site\Enums\SiteProvisioningStep;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Enums\SiteType;
use Nip\Site\Jobs\Provisioning\BuildFrontendAssetsJob;
use Nip\Site\Jobs\Provisioning\CloneRepositoryJob;
use Nip\Site\Jobs\Provisioning\ConfigureNginxJob;
use Nip\Site\Jobs\Provisioning\CreateEnvironmentFileJob;
use Nip\Site\Jobs\Provisioning\FinalizeSiteJob;
use Nip\Site\Jobs\Provisioning\InstallComposerDependenciesJob;
use Nip\Site\Jobs\Provisioning\RunMigrationsJob;
use Nip\Site\Models\Site;
use Nip\Site\Services\SiteProvisioningService;

beforeEach(function () {
    Bus::fake();
});

it('dispatches site provisioning batch with all jobs for laravel site with repository', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->withRepository('git@github.com:example/repo.git', 'main')
        ->create([
            'status' => SiteStatus::Pending,
        ]);

    $service = new SiteProvisioningService;
    $batch = $service->dispatch($site);

    expect($batch->name)->toBe("Site Provisioning: {$site->domain}");

    $site->refresh();

    expect($site->status)->toBe(SiteStatus::Installing)
        ->and($site->provisioning_step)->toBe(SiteProvisioningStep::Initializing)
        ->and($site->batch_id)->toBe($batch->id);

    Bus::assertBatched(function ($batch) {
        return count($batch->jobs) === 7;
    });
});

it('includes all required jobs for laravel site with repository', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->withRepository('git@github.com:example/repo.git')
        ->create();

    $service = new SiteProvisioningService;
    $service->dispatch($site);

    Bus::assertBatched(function ($batch) {
        $jobTypes = collect($batch->jobs)->map(fn ($job) => get_class($job))->toArray();

        return in_array(ConfigureNginxJob::class, $jobTypes)
            && in_array(CloneRepositoryJob::class, $jobTypes)
            && in_array(CreateEnvironmentFileJob::class, $jobTypes)
            && in_array(InstallComposerDependenciesJob::class, $jobTypes)
            && in_array(BuildFrontendAssetsJob::class, $jobTypes)
            && in_array(RunMigrationsJob::class, $jobTypes)
            && in_array(FinalizeSiteJob::class, $jobTypes);
    });
});

it('excludes dependency jobs when site has no repository', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->static()
        ->create([
            'repository' => null,
        ]);

    $service = new SiteProvisioningService;
    $service->dispatch($site);

    Bus::assertBatched(function ($batch) {
        $jobTypes = collect($batch->jobs)->map(fn ($job) => get_class($job))->toArray();

        return in_array(ConfigureNginxJob::class, $jobTypes)
            && in_array(CloneRepositoryJob::class, $jobTypes)
            && in_array(CreateEnvironmentFileJob::class, $jobTypes)
            && in_array(FinalizeSiteJob::class, $jobTypes)
            && ! in_array(InstallComposerDependenciesJob::class, $jobTypes)
            && ! in_array(BuildFrontendAssetsJob::class, $jobTypes)
            && ! in_array(RunMigrationsJob::class, $jobTypes);
    });
});

it('excludes build job when site has no build command', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->withRepository('git@github.com:example/repo.git')
        ->create([
            'type' => SiteType::Php,
            'build_command' => null,
        ]);

    $service = new SiteProvisioningService;
    $service->dispatch($site);

    Bus::assertBatched(function ($batch) {
        $jobTypes = collect($batch->jobs)->map(fn ($job) => get_class($job))->toArray();

        return in_array(InstallComposerDependenciesJob::class, $jobTypes)
            && ! in_array(BuildFrontendAssetsJob::class, $jobTypes);
    });
});

it('excludes migration job for non-laravel sites', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->wordpress()
        ->withRepository('git@github.com:example/wordpress.git')
        ->create();

    $service = new SiteProvisioningService;
    $service->dispatch($site);

    Bus::assertBatched(function ($batch) {
        $jobTypes = collect($batch->jobs)->map(fn ($job) => get_class($job))->toArray();

        return in_array(InstallComposerDependenciesJob::class, $jobTypes)
            && ! in_array(RunMigrationsJob::class, $jobTypes);
    });
});

it('always includes configure nginx and finalize jobs', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->static()
        ->create([
            'repository' => null,
            'build_command' => null,
        ]);

    $service = new SiteProvisioningService;
    $service->dispatch($site);

    Bus::assertBatched(function ($batch) {
        $jobTypes = collect($batch->jobs)->map(fn ($job) => get_class($job))->toArray();

        return in_array(ConfigureNginxJob::class, $jobTypes)
            && in_array(FinalizeSiteJob::class, $jobTypes);
    });
});

it('sets batch callbacks correctly', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create();

    $service = new SiteProvisioningService;
    $service->dispatch($site);

    Bus::assertBatched(function ($batch) use ($site) {
        return $batch->name === "Site Provisioning: {$site->domain}";
    });
});

it('updates site status to installing when provisioning starts', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->create([
            'status' => SiteStatus::Pending,
        ]);

    $service = new SiteProvisioningService;
    $service->dispatch($site);

    $site->refresh();

    expect($site->status)->toBe(SiteStatus::Installing)
        ->and($site->provisioning_step)->toBe(SiteProvisioningStep::Initializing);
});

it('includes migrations job only for laravel and statamic sites', function () {
    $server = Server::factory()->create();

    // Test Laravel
    $laravelSite = Site::factory()
        ->for($server)
        ->laravel()
        ->withRepository('git@github.com:example/laravel.git')
        ->create();

    $service = new SiteProvisioningService;
    $service->dispatch($laravelSite);

    Bus::assertBatched(function ($batch) {
        $jobTypes = collect($batch->jobs)->map(fn ($job) => get_class($job))->toArray();

        return in_array(RunMigrationsJob::class, $jobTypes);
    });

    Bus::fake();

    // Test Statamic
    $statamicSite = Site::factory()
        ->for($server)
        ->withRepository('git@github.com:example/statamic.git')
        ->create([
            'type' => SiteType::Statamic,
        ]);

    $service->dispatch($statamicSite);

    Bus::assertBatched(function ($batch) {
        $jobTypes = collect($batch->jobs)->map(fn ($job) => get_class($job))->toArray();

        return in_array(RunMigrationsJob::class, $jobTypes);
    });
});
