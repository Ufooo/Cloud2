<?php

use App\Models\User;
use Nip\Server\Models\Server;
use Nip\Site\Models\Site;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->server = Server::factory()->create();
});

describe('Path Methods', function () {
    it('returns correct site root path', function () {
        $site = Site::factory()->create([
            'server_id' => $this->server->id,
            'domain' => 'example.com',
            'user' => 'john',
        ]);

        expect($site->getSiteRoot())->toBe('/home/john/example.com');
    });

    it('returns correct application path for non-zero-downtime site', function () {
        $site = Site::factory()->create([
            'server_id' => $this->server->id,
            'domain' => 'example.com',
            'user' => 'john',
            'root_directory' => '/',
            'zero_downtime' => false,
        ]);

        expect($site->getApplicationPath())->toBe('/home/john/example.com');
    });

    it('returns correct application path for non-zero-downtime site with subdirectory', function () {
        $site = Site::factory()->create([
            'server_id' => $this->server->id,
            'domain' => 'example.com',
            'user' => 'john',
            'root_directory' => '/frontend',
            'zero_downtime' => false,
        ]);

        expect($site->getApplicationPath())->toBe('/home/john/example.com/frontend');
    });

    it('returns correct application path for zero-downtime site', function () {
        $site = Site::factory()->create([
            'server_id' => $this->server->id,
            'domain' => 'example.com',
            'user' => 'john',
            'root_directory' => '/',
            'zero_downtime' => true,
        ]);

        expect($site->getApplicationPath())->toBe('/home/john/example.com/current');
    });

    it('returns correct document root with web directory', function () {
        $site = Site::factory()->create([
            'server_id' => $this->server->id,
            'domain' => 'example.com',
            'user' => 'john',
            'root_directory' => '/',
            'web_directory' => '/public',
            'zero_downtime' => false,
        ]);

        expect($site->getDocumentRoot())->toBe('/home/john/example.com/public');
    });

    it('returns correct release path for zero-downtime deployment', function () {
        $site = Site::factory()->create([
            'server_id' => $this->server->id,
            'domain' => 'example.com',
            'user' => 'john',
            'root_directory' => '/backend',
            'zero_downtime' => true,
        ]);

        $timestamp = '20260126120000';
        expect($site->getReleasePath($timestamp))->toBe('/home/john/example.com/releases/20260126120000/backend');
    });
});

describe('HasSitePermissions Trait', function () {
    it('checks if site can be updated', function () {
        $site = Site::factory()->create([
            'server_id' => $this->server->id,
        ]);

        $result = $site->canBeUpdated($this->user);

        expect($result)->toBeBool();
    });

    it('checks if site can be deleted', function () {
        $site = Site::factory()->create([
            'server_id' => $this->server->id,
        ]);

        $result = $site->canBeDeleted($this->user);

        expect($result)->toBeBool();
    });

    it('checks if site can be deployed', function () {
        $site = Site::factory()->create([
            'server_id' => $this->server->id,
        ]);

        $result = $site->canBeDeployed($this->user);

        expect($result)->toBeBool();
    });

    it('returns permissions data object', function () {
        $site = Site::factory()->create([
            'server_id' => $this->server->id,
        ]);

        $permissions = $site->getPermissions($this->user);

        expect($permissions)->toBeInstanceOf(\Nip\Site\Data\SitePermissionsData::class)
            ->and($permissions->update)->toBeBool()
            ->and($permissions->delete)->toBeBool()
            ->and($permissions->deploy)->toBeBool();
    });
});

describe('Deprecated Methods Removed', function () {
    it('does not have deprecated getProjectPath method', function () {
        $site = Site::factory()->create([
            'server_id' => $this->server->id,
        ]);

        expect(method_exists($site, 'getProjectPath'))->toBeFalse();
    });

    it('does not have deprecated getBasePath method', function () {
        $site = Site::factory()->create([
            'server_id' => $this->server->id,
        ]);

        expect(method_exists($site, 'getBasePath'))->toBeFalse();
    });

    it('does not have deprecated getWebPath method', function () {
        $site = Site::factory()->create([
            'server_id' => $this->server->id,
        ]);

        expect(method_exists($site, 'getWebPath'))->toBeFalse();
    });

    it('does not have deprecated getFullPath method', function () {
        $site = Site::factory()->create([
            'server_id' => $this->server->id,
        ]);

        expect(method_exists($site, 'getFullPath'))->toBeFalse();
    });

    it('does not have deprecated getCurrentPath method', function () {
        $site = Site::factory()->create([
            'server_id' => $this->server->id,
        ]);

        expect(method_exists($site, 'getCurrentPath'))->toBeFalse();
    });

    it('does not have deprecated getRootPath method', function () {
        $site = Site::factory()->create([
            'server_id' => $this->server->id,
        ]);

        expect(method_exists($site, 'getRootPath'))->toBeFalse();
    });
});
