<?php

use App\Models\User;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Server\Services\SSH\SSHService;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Models\Site;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->server = Server::factory()->create();
});

describe('show page', function () {
    it('can view environment page for installed site', function () {
        $site = Site::factory()
            ->for($this->server)
            ->laravel()
            ->installed()
            ->create();

        $this->actingAs($this->user)
            ->get("/sites/{$site->slug}/environment")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('sites/Environment')
                ->has('site')
            );
    });

    it('cannot view environment page for uninstalled site', function () {
        $site = Site::factory()
            ->for($this->server)
            ->laravel()
            ->create(['status' => SiteStatus::Pending]);

        $this->actingAs($this->user)
            ->get("/sites/{$site->slug}/environment")
            ->assertForbidden();
    });

    it('requires authentication to view environment page', function () {
        $site = Site::factory()
            ->for($this->server)
            ->laravel()
            ->installed()
            ->create();

        $this->get("/sites/{$site->slug}/environment")
            ->assertRedirect(route('login'));
    });
});

describe('get content', function () {
    it('can fetch environment content via ajax', function () {
        $site = Site::factory()
            ->for($this->server)
            ->laravel()
            ->installed()
            ->create();

        $envContent = "APP_NAME=Laravel\nAPP_ENV=production\nAPP_DEBUG=false";

        $sshMock = Mockery::mock(SSHService::class);
        $sshMock->shouldReceive('connect')->once()->andReturnSelf();
        $sshMock->shouldReceive('getFileContent')->once()->andReturn($envContent);
        $sshMock->shouldReceive('disconnect')->once();

        $this->app->instance(SSHService::class, $sshMock);

        $this->actingAs($this->user)
            ->getJson("/sites/{$site->slug}/environment/content")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'content' => $envContent,
            ]);
    });

    it('returns error when environment file not found', function () {
        $site = Site::factory()
            ->for($this->server)
            ->laravel()
            ->installed()
            ->create();

        $sshMock = Mockery::mock(SSHService::class);
        $sshMock->shouldReceive('connect')->once()->andReturnSelf();
        $sshMock->shouldReceive('getFileContent')->once()->andReturn(null);
        $sshMock->shouldReceive('disconnect')->once();

        $this->app->instance(SSHService::class, $sshMock);

        $this->actingAs($this->user)
            ->getJson("/sites/{$site->slug}/environment/content")
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    });

    it('cannot fetch environment content for uninstalled site', function () {
        $site = Site::factory()
            ->for($this->server)
            ->laravel()
            ->create(['status' => SiteStatus::Installing]);

        $this->actingAs($this->user)
            ->getJson("/sites/{$site->slug}/environment/content")
            ->assertForbidden();
    });
});

describe('update environment', function () {
    it('can update environment file', function () {
        $site = Site::factory()
            ->for($this->server)
            ->laravel()
            ->installed()
            ->create();

        $newEnvContent = "APP_NAME=MyApp\nAPP_ENV=production";

        $sshMock = Mockery::mock(SSHService::class);
        $sshMock->shouldReceive('connect')->once()->andReturnSelf();
        $sshMock->shouldReceive('uploadContent')
            ->once()
            ->with($newEnvContent, Mockery::type('string'));
        $sshMock->shouldReceive('disconnect')->once();

        $this->app->instance(SSHService::class, $sshMock);

        $this->actingAs($this->user)
            ->putJson("/sites/{$site->slug}/environment", [
                'environment' => $newEnvContent,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Environment file updated successfully.',
            ]);
    });

    it('runs config:cache when clear_config_cache is enabled', function () {
        $site = Site::factory()
            ->for($this->server)
            ->laravel()
            ->installed()
            ->create();

        $sshMock = Mockery::mock(SSHService::class);
        $sshMock->shouldReceive('connect')->once()->andReturnSelf();
        $sshMock->shouldReceive('uploadContent')->once();
        $sshMock->shouldReceive('exec')
            ->once()
            ->with(Mockery::pattern('/config:cache/'))
            ->andReturn(new ExecutionResult('Configuration cached successfully.', 0, 1.0));
        $sshMock->shouldReceive('disconnect')->once();

        $this->app->instance(SSHService::class, $sshMock);

        $this->actingAs($this->user)
            ->putJson("/sites/{$site->slug}/environment", [
                'environment' => 'APP_NAME=Test',
                'clear_config_cache' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    });

    it('runs queue:restart when restart_queue is enabled', function () {
        $site = Site::factory()
            ->for($this->server)
            ->laravel()
            ->installed()
            ->create();

        $sshMock = Mockery::mock(SSHService::class);
        $sshMock->shouldReceive('connect')->once()->andReturnSelf();
        $sshMock->shouldReceive('uploadContent')->once();
        $sshMock->shouldReceive('exec')
            ->once()
            ->with(Mockery::pattern('/queue:restart/'))
            ->andReturn(new ExecutionResult('Broadcasting queue restart signal.', 0, 1.0));
        $sshMock->shouldReceive('disconnect')->once();

        $this->app->instance(SSHService::class, $sshMock);

        $this->actingAs($this->user)
            ->putJson("/sites/{$site->slug}/environment", [
                'environment' => 'APP_NAME=Test',
                'restart_queue' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    });

    it('runs both artisan commands when both options enabled', function () {
        $site = Site::factory()
            ->for($this->server)
            ->laravel()
            ->installed()
            ->create();

        $sshMock = Mockery::mock(SSHService::class);
        $sshMock->shouldReceive('connect')->once()->andReturnSelf();
        $sshMock->shouldReceive('uploadContent')->once();
        $sshMock->shouldReceive('exec')
            ->twice()
            ->andReturn(new ExecutionResult('Command executed.', 0, 1.0));
        $sshMock->shouldReceive('disconnect')->once();

        $this->app->instance(SSHService::class, $sshMock);

        $this->actingAs($this->user)
            ->putJson("/sites/{$site->slug}/environment", [
                'environment' => 'APP_NAME=Test',
                'clear_config_cache' => true,
                'restart_queue' => true,
            ])
            ->assertOk();
    });

    it('cannot update environment for uninstalled site', function () {
        $site = Site::factory()
            ->for($this->server)
            ->laravel()
            ->create(['status' => SiteStatus::Pending]);

        $this->actingAs($this->user)
            ->putJson("/sites/{$site->slug}/environment", [
                'environment' => 'APP_NAME=Test',
            ])
            ->assertForbidden();
    });

    it('saves switch preferences without uploading when no environment content is sent', function () {
        $site = Site::factory()
            ->for($this->server)
            ->laravel()
            ->installed()
            ->create([
                'env_clear_config_cache' => false,
                'env_restart_queue' => false,
            ]);

        $sshMock = Mockery::mock(SSHService::class);
        $sshMock->shouldNotReceive('connect');
        $sshMock->shouldNotReceive('uploadContent');

        $this->app->instance(SSHService::class, $sshMock);

        $this->actingAs($this->user)
            ->putJson("/sites/{$site->slug}/environment", [
                'clear_config_cache' => true,
                'restart_queue' => true,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Settings saved successfully.',
            ]);

        expect($site->fresh())
            ->env_clear_config_cache->toBeTrue()
            ->env_restart_queue->toBeTrue();
    });

    it('validates environment content max length', function () {
        $site = Site::factory()
            ->for($this->server)
            ->laravel()
            ->installed()
            ->create();

        $this->actingAs($this->user)
            ->putJson("/sites/{$site->slug}/environment", [
                'environment' => str_repeat('A', 65536),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['environment']);
    });
});

describe('zero-downtime path handling', function () {
    it('uses correct path for zero-downtime site', function () {
        $site = Site::factory()
            ->for($this->server)
            ->laravel()
            ->installed()
            ->create([
                'domain' => 'example.com',
                'user' => 'webuser',
                'zero_downtime' => true,
            ]);

        $expectedPath = '/home/webuser/example.com/.env';

        $sshMock = Mockery::mock(SSHService::class);
        $sshMock->shouldReceive('connect')->once()->andReturnSelf();
        $sshMock->shouldReceive('getFileContent')
            ->once()
            ->with($expectedPath)
            ->andReturn('APP_NAME=Test');
        $sshMock->shouldReceive('disconnect')->once();

        $this->app->instance(SSHService::class, $sshMock);

        $this->actingAs($this->user)
            ->getJson("/sites/{$site->slug}/environment/content")
            ->assertOk();
    });

    it('uses correct path for standard site', function () {
        $site = Site::factory()
            ->for($this->server)
            ->laravel()
            ->installed()
            ->create([
                'domain' => 'example.com',
                'user' => 'webuser',
                'zero_downtime' => false,
                'root_directory' => '/',
            ]);

        $expectedPath = '/home/webuser/example.com/.env';

        $sshMock = Mockery::mock(SSHService::class);
        $sshMock->shouldReceive('connect')->once()->andReturnSelf();
        $sshMock->shouldReceive('getFileContent')
            ->once()
            ->with($expectedPath)
            ->andReturn('APP_NAME=Test');
        $sshMock->shouldReceive('disconnect')->once();

        $this->app->instance(SSHService::class, $sshMock);

        $this->actingAs($this->user)
            ->getJson("/sites/{$site->slug}/environment/content")
            ->assertOk();
    });
});
