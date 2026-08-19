<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Domain\Enums\DomainRecordType;
use Nip\Domain\Jobs\AddDomainJob;
use Nip\Domain\Models\DomainRecord;
use Nip\Server\Models\Server;
use Nip\Site\Models\Site;

beforeEach(function () {
    Queue::fake();
});

it('stores a redirect domain with target', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create(['domain' => 'example.com']);

    $response = $this->actingAs($user)->post("/sites/{$site->slug}/domains", [
        'name' => 'old-example.com',
        'type' => DomainRecordType::Redirect->value,
        'redirect_target' => 'example.com',
    ]);

    $response->assertRedirect();

    $domainRecord = DomainRecord::query()
        ->where('site_id', $site->id)
        ->where('name', 'old-example.com')
        ->first();

    expect($domainRecord)->not->toBeNull();
    expect($domainRecord->type)->toBe(DomainRecordType::Redirect);
    expect($domainRecord->redirect_target)->toBe('example.com');
    expect($domainRecord->isRedirect())->toBeTrue();

    Queue::assertPushed(AddDomainJob::class);
});

it('rejects a redirect domain without a target', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create(['domain' => 'example.com']);

    $response = $this->actingAs($user)->post("/sites/{$site->slug}/domains", [
        'name' => 'old-example.com',
        'type' => DomainRecordType::Redirect->value,
    ]);

    $response->assertSessionHasErrors('redirect_target');
});

it('rejects a redirect domain pointing to itself', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create(['domain' => 'example.com']);

    $response = $this->actingAs($user)->post("/sites/{$site->slug}/domains", [
        'name' => 'old-example.com',
        'type' => DomainRecordType::Redirect->value,
        'redirect_target' => 'old-example.com',
    ]);

    $response->assertSessionHasErrors('redirect_target');
});

it('ignores redirect_target for non-redirect domain types', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create(['domain' => 'example.com']);

    $response = $this->actingAs($user)->post("/sites/{$site->slug}/domains", [
        'name' => 'alias.example.com',
        'type' => DomainRecordType::Alias->value,
        'redirect_target' => 'somewhere-else.com',
    ]);

    $response->assertSessionHasNoErrors();

    $domainRecord = DomainRecord::query()
        ->where('site_id', $site->id)
        ->where('name', 'alias.example.com')
        ->first();

    expect($domainRecord)->not->toBeNull();
    expect($domainRecord->redirect_target)->toBeNull();
});

it('AddDomainJob renders redirect server block for redirect type', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create(['domain' => 'example.com', 'user' => 'deploy']);

    $domainRecord = DomainRecord::factory()->for($site)->create([
        'name' => 'old-example.com',
        'type' => DomainRecordType::Redirect,
        'redirect_target' => 'example.com',
        'status' => DomainRecordStatus::Creating,
    ]);

    $job = new AddDomainJob($domainRecord);

    $reflection = new ReflectionMethod($job, 'generateNginxConfig');
    $config = $reflection->invoke($job, $site, null);

    expect($config)->toContain('return 301 https://example.com$request_uri');
    expect($config)->toContain('server_name old-example.com');
    expect($config)->not->toContain('fastcgi_pass');
});
