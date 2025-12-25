<?php

use App\Models\User;
use Nip\Database\Enums\DatabaseStatus;
use Nip\Database\Enums\DatabaseUserStatus;
use Nip\Database\Models\Database;
use Nip\Database\Models\DatabaseUser;
use Nip\Server\Models\Server;
use Nip\Site\Models\Site;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->server = Server::factory()->connected()->create();
});

it('can view global databases index', function () {
    $this->actingAs($this->user)
        ->get(route('databases'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('databases/Index')
            ->has('databases')
            ->has('databaseUsers')
        );
});

it('can view server databases index', function () {
    $this->actingAs($this->user)
        ->get(route('servers.databases', $this->server))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('databases/Index')
            ->has('databases')
            ->has('databaseUsers')
            ->has('server')
        );
});

it('can create a database on a server', function () {
    $this->actingAs($this->user)
        ->post(route('servers.databases.store', $this->server), [
            'name' => 'test_database',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('databases', [
        'server_id' => $this->server->id,
        'name' => 'test_database',
    ]);
});

it('can delete a database', function () {
    $database = Database::factory()->create([
        'server_id' => $this->server->id,
    ]);

    $this->actingAs($this->user)
        ->delete(route('servers.databases.destroy', [$this->server, $database]))
        ->assertRedirect();

    expect($database->fresh()->status)->toBe(DatabaseStatus::Deleting);
});

it('can create a database user on a server', function () {
    $this->actingAs($this->user)
        ->post(route('servers.databases.users.store', $this->server), [
            'username' => 'test_user',
            'password' => 'securepassword123',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('database_users', [
        'server_id' => $this->server->id,
        'username' => 'test_user',
    ]);
});

it('can delete a database user', function () {
    $databaseUser = DatabaseUser::factory()->create([
        'server_id' => $this->server->id,
    ]);

    $this->actingAs($this->user)
        ->delete(route('servers.databases.users.destroy', [$this->server, $databaseUser]))
        ->assertRedirect();

    expect($databaseUser->fresh()->status)->toBe(DatabaseUserStatus::Deleting);
});

it('can view site databases', function () {
    $site = Site::factory()->create([
        'server_id' => $this->server->id,
    ]);

    Database::factory()->create([
        'server_id' => $this->server->id,
        'site_id' => $site->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('sites.databases', $site))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('databases/Index')
            ->has('databases.data', 1)
            ->has('site')
        );
});

it('can update a database user with database assignments', function () {
    $database1 = Database::factory()->create([
        'server_id' => $this->server->id,
    ]);
    $database2 = Database::factory()->create([
        'server_id' => $this->server->id,
    ]);

    $databaseUser = DatabaseUser::factory()->create([
        'server_id' => $this->server->id,
    ]);

    $this->actingAs($this->user)
        ->put(route('servers.databases.users.update', [$this->server, $databaseUser]), [
            'databases' => [$database1->id, $database2->id],
        ])
        ->assertRedirect();

    expect($databaseUser->databases()->pluck('id')->toArray())
        ->toContain($database1->id)
        ->toContain($database2->id);
});

it('can remove database assignments from a user', function () {
    $database = Database::factory()->create([
        'server_id' => $this->server->id,
    ]);

    $databaseUser = DatabaseUser::factory()->create([
        'server_id' => $this->server->id,
    ]);

    $databaseUser->databases()->attach($database->id);

    $this->actingAs($this->user)
        ->put(route('servers.databases.users.update', [$this->server, $databaseUser]), [
            'databases' => [],
        ])
        ->assertRedirect();

    expect($databaseUser->databases()->count())->toBe(0);
});

it('can create a database user with readonly access', function () {
    $this->actingAs($this->user)
        ->post(route('servers.databases.users.store', $this->server), [
            'username' => 'readonly_user',
            'password' => 'securepassword123',
            'readonly' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('database_users', [
        'server_id' => $this->server->id,
        'username' => 'readonly_user',
        'readonly' => true,
    ]);
});

it('can update a database user readonly status', function () {
    $databaseUser = DatabaseUser::factory()->create([
        'server_id' => $this->server->id,
        'readonly' => false,
    ]);

    $this->actingAs($this->user)
        ->put(route('servers.databases.users.update', [$this->server, $databaseUser]), [
            'readonly' => true,
        ])
        ->assertRedirect();

    expect($databaseUser->fresh()->readonly)->toBeTrue();
});
