<?php

use App\Models\User;
use Nip\Php\Enums\PhpVersionStatus;
use Nip\Php\Models\PhpVersion;
use Nip\Server\Models\Server;

it('creates php version for app server type', function () {
    $user = User::factory()->create();

    $serverData = [
        'name' => 'App Server',
        'provider' => 'digitalocean',
        'type' => 'app',
        'ip_address' => '192.168.1.1',
        'ssh_port' => '22',
        'php_version' => 'php83',
        'ubuntu_version' => '24.04',
        'timezone' => 'UTC',
    ];

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('servers.store'), $serverData);

    $response->assertRedirect();

    $server = Server::query()->where('name', 'App Server')->first();

    expect($server)->not->toBeNull();

    $phpVersion = PhpVersion::where('server_id', $server->id)->first();

    expect($phpVersion)->not->toBeNull();
    expect($phpVersion->version)->toBe('8.3');
    expect($phpVersion->is_cli_default)->toBeTrue();
    expect($phpVersion->is_site_default)->toBeTrue();
    expect($phpVersion->status)->toBe(PhpVersionStatus::Installing);
});

it('creates php version for web server type', function () {
    $user = User::factory()->create();

    $serverData = [
        'name' => 'Web Server',
        'provider' => 'digitalocean',
        'type' => 'web',
        'ip_address' => '192.168.1.2',
        'ssh_port' => '22',
        'php_version' => 'php84',
        'ubuntu_version' => '24.04',
        'timezone' => 'UTC',
    ];

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('servers.store'), $serverData);

    $response->assertRedirect();

    $server = Server::query()->where('name', 'Web Server')->first();
    $phpVersion = PhpVersion::where('server_id', $server->id)->first();

    expect($phpVersion)->not->toBeNull();
    expect($phpVersion->version)->toBe('8.4');
    expect($phpVersion->status)->toBe(PhpVersionStatus::Installing);
});

it('creates php version for worker server type', function () {
    $user = User::factory()->create();

    $serverData = [
        'name' => 'Worker Server',
        'provider' => 'digitalocean',
        'type' => 'worker',
        'ip_address' => '192.168.1.3',
        'ssh_port' => '22',
        'php_version' => 'php82',
        'ubuntu_version' => '24.04',
        'timezone' => 'UTC',
    ];

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('servers.store'), $serverData);

    $response->assertRedirect();

    $server = Server::query()->where('name', 'Worker Server')->first();
    $phpVersion = PhpVersion::where('server_id', $server->id)->first();

    expect($phpVersion)->not->toBeNull();
    expect($phpVersion->version)->toBe('8.2');
    expect($phpVersion->status)->toBe(PhpVersionStatus::Installing);
});

it('does not create php version for database server type', function () {
    $user = User::factory()->create();

    $serverData = [
        'name' => 'Database Server',
        'provider' => 'digitalocean',
        'type' => 'database',
        'ip_address' => '192.168.1.4',
        'ssh_port' => '22',
        'database_type' => 'mysql80',
        'ubuntu_version' => '24.04',
        'timezone' => 'UTC',
    ];

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('servers.store'), $serverData);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $server = Server::query()->where('name', 'Database Server')->first();

    expect($server)->not->toBeNull();

    $phpVersionCount = PhpVersion::where('server_id', $server->id)->count();

    expect($phpVersionCount)->toBe(0);
});

it('does not create php version for cache server type', function () {
    $user = User::factory()->create();

    $serverData = [
        'name' => 'Cache Server',
        'provider' => 'digitalocean',
        'type' => 'cache',
        'ip_address' => '192.168.1.5',
        'ssh_port' => '22',
        'ubuntu_version' => '24.04',
        'timezone' => 'UTC',
    ];

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('servers.store'), $serverData);

    $response->assertRedirect();

    $server = Server::query()->where('name', 'Cache Server')->first();
    $phpVersionCount = PhpVersion::where('server_id', $server->id)->count();

    expect($phpVersionCount)->toBe(0);
});

it('marks php version as installed when provisioning completes', function () {
    $server = Server::factory()->create([
        'provision_step' => 1,
    ]);

    $phpVersion = PhpVersion::factory()->create([
        'server_id' => $server->id,
        'status' => PhpVersionStatus::Installing,
    ]);

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->post('/provisioning/callback/status', [
            'server_id' => $server->id,
            'status' => 10,
            'token' => $server->provisioning_token,
        ]);

    $response->assertSuccessful();

    $phpVersion->refresh();

    expect($phpVersion->status)->toBe(PhpVersionStatus::Installed);
});

it('rejects provisioning callback with invalid token', function () {
    $server = Server::factory()->create([
        'provision_step' => 1,
    ]);

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->post('/provisioning/callback/status', [
            'server_id' => $server->id,
            'status' => 10,
            'token' => 'invalid-token',
        ]);

    $response->assertForbidden();

    $server->refresh();
    expect($server->provision_step)->toBe(1);
});
