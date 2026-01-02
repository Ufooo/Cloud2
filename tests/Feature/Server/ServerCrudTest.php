<?php

use App\Models\User;
use Nip\Database\Models\Database;
use Nip\Database\Models\DatabaseUser;
use Nip\Server\Models\Server;
use Nip\Site\Models\Site;
use Nip\UnixUser\Models\UnixUser;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\assertSoftDeleted;

it('can list servers', function () {
    $user = User::factory()->create();
    Server::factory()->count(3)->create();

    $response = actingAs($user)
        ->get(route('servers.index'));

    $response->assertSuccessful();
});

it('can show a specific server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create();

    $response = actingAs($user)
        ->get(route('servers.show', $server));

    $response->assertSuccessful();
});

it('can create a server', function () {
    $user = User::factory()->create();

    $serverData = [
        'name' => 'Production Server',
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
    assertDatabaseHas('servers', [
        'name' => 'Production Server',
        'provider' => 'digitalocean',
        'status' => 'provisioning',
    ]);
});

it('validates server creation data', function () {
    $user = User::factory()->create();

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('servers.store'), [
            'name' => '',
            'provider' => 'invalid',
            'type' => 'invalid',
        ]);

    $response->assertSessionHasErrors(['name', 'provider', 'type']);
});

it('can delete a server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create();

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->delete(route('servers.destroy', $server));

    $response->assertRedirect(route('servers.index'));
    assertSoftDeleted('servers', ['id' => $server->id]);
});

it('deletes related records when server is deleted', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create();

    // Create related records
    $database = Database::factory()->create(['server_id' => $server->id]);
    $databaseUser = DatabaseUser::factory()->create(['server_id' => $server->id]);
    $site = Site::factory()->create(['server_id' => $server->id]);
    $unixUser = UnixUser::factory()->create(['server_id' => $server->id]);

    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->delete(route('servers.destroy', $server));

    assertSoftDeleted('servers', ['id' => $server->id]);
    assertDatabaseMissing('databases', ['id' => $database->id]);
    assertDatabaseMissing('database_users', ['id' => $databaseUser->id]);
    assertDatabaseMissing('sites', ['id' => $site->id]);
    assertDatabaseMissing('unix_users', ['id' => $unixUser->id]);
});
