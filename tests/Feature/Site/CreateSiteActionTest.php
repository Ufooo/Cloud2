<?php

use Illuminate\Support\Facades\Queue;
use Nip\Database\Enums\DatabaseStatus;
use Nip\Database\Enums\DatabaseUserStatus;
use Nip\Database\Jobs\CreateDatabaseJob;
use Nip\Database\Jobs\SyncDatabaseUserJob;
use Nip\Database\Models\Database;
use Nip\Database\Models\DatabaseUser;
use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Domain\Enums\DomainRecordType;
use Nip\Server\Models\Server;
use Nip\Site\Actions\CreateSiteAction;
use Nip\Site\Data\SiteCreationData;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Services\SiteProvisioningService;
use Nip\UnixUser\Models\UnixUser;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->server = Server::factory()->connected()->create();
    UnixUser::factory()->for($this->server)->create(['username' => 'testuser']);

    $this->provisioningService = Mockery::mock(SiteProvisioningService::class);
    $this->provisioningService->shouldReceive('dispatch')->once();

    $this->action = new CreateSiteAction($this->provisioningService);
});

it('creates a basic site', function () {
    $data = SiteCreationData::from([
        'server_id' => $this->server->id,
        'domain' => 'example.com',
        'type' => 'laravel',
        'user' => 'testuser',
    ]);

    $site = $this->action->handle($data);

    expect($site->domain)->toBe('example.com')
        ->and($site->type->value)->toBe('laravel')
        ->and($site->user)->toBe('testuser')
        ->and($site->status)->toBe(SiteStatus::Pending)
        ->and($site->deploy_status)->toBe(DeployStatus::NeverDeployed)
        ->and($site->deploy_hook_token)->not->toBeNull();
});

it('creates primary domain record', function () {
    $data = SiteCreationData::from([
        'server_id' => $this->server->id,
        'domain' => 'example.com',
        'type' => 'laravel',
        'user' => 'testuser',
        'www_redirect_type' => 'to_www',
        'allow_wildcard' => true,
    ]);

    $site = $this->action->handle($data);

    $domainRecord = $site->domainRecords()->first();

    expect($domainRecord)->not->toBeNull()
        ->and($domainRecord->name)->toBe('example.com')
        ->and($domainRecord->type)->toBe(DomainRecordType::Primary)
        ->and($domainRecord->status)->toBe(DomainRecordStatus::Pending)
        ->and($domainRecord->allow_wildcard)->toBeTrue();
});

it('applies default values from site type', function () {
    $data = SiteCreationData::from([
        'server_id' => $this->server->id,
        'domain' => 'example.com',
        'type' => 'laravel',
        'user' => 'testuser',
    ]);

    $site = $this->action->handle($data);

    expect($site->web_directory)->toBe('/public');
});

it('creates database when requested', function () {
    Queue::fake();

    $data = SiteCreationData::from([
        'server_id' => $this->server->id,
        'domain' => 'example.com',
        'type' => 'laravel',
        'user' => 'testuser',
        'create_database' => true,
        'database_name' => 'example_db',
        'database_user' => 'example_user',
        'database_password' => 'secret123',
    ]);

    $site = $this->action->handle($data);

    expect($site->database_id)->not->toBeNull()
        ->and($site->database_user_id)->not->toBeNull();

    $database = Database::find($site->database_id);
    expect($database->name)->toBe('example_db')
        ->and($database->site_id)->toBe($site->id)
        ->and($database->status)->toBe(DatabaseStatus::Installing);

    $databaseUser = DatabaseUser::find($site->database_user_id);
    expect($databaseUser->username)->toBe('example_user')
        ->and($databaseUser->status)->toBe(DatabaseUserStatus::Installing);

    Queue::assertPushed(CreateDatabaseJob::class);
    Queue::assertPushed(SyncDatabaseUserJob::class);
});

it('links existing database when provided', function () {
    $existingDatabase = Database::factory()->for($this->server)->create();

    $data = SiteCreationData::from([
        'server_id' => $this->server->id,
        'domain' => 'example.com',
        'type' => 'laravel',
        'user' => 'testuser',
        'database_id' => $existingDatabase->id,
    ]);

    $site = $this->action->handle($data);

    $existingDatabase->refresh();

    expect($existingDatabase->site_id)->toBe($site->id);
});

it('does not create database when not requested', function () {
    Queue::fake();

    $data = SiteCreationData::from([
        'server_id' => $this->server->id,
        'domain' => 'example.com',
        'type' => 'laravel',
        'user' => 'testuser',
    ]);

    $site = $this->action->handle($data);

    expect($site->database_id)->toBeNull()
        ->and($site->database_user_id)->toBeNull();

    Queue::assertNotPushed(CreateDatabaseJob::class);
    Queue::assertNotPushed(SyncDatabaseUserJob::class);
});
