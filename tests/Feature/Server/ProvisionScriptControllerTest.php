<?php

use App\Models\User;
use Nip\Server\Enums\ProvisionScriptStatus;
use Nip\Server\Models\ProvisionScript;
use Nip\Server\Models\Server;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->server = Server::factory()->create();
});

it('can fetch all failed scripts', function () {
    ProvisionScript::factory()->count(2)->create([
        'server_id' => $this->server->id,
        'status' => ProvisionScriptStatus::Failed,
    ]);

    $otherServer = Server::factory()->create();
    ProvisionScript::factory()->create([
        'server_id' => $otherServer->id,
        'status' => ProvisionScriptStatus::Failed,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/failed-scripts');

    $response->assertOk()
        ->assertJsonCount(3);
});

it('can fetch failed scripts for a server', function () {
    ProvisionScript::factory()->count(3)->create([
        'server_id' => $this->server->id,
        'status' => ProvisionScriptStatus::Failed,
    ]);

    ProvisionScript::factory()->create([
        'server_id' => $this->server->id,
        'status' => ProvisionScriptStatus::Completed,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/servers/{$this->server->slug}/failed-scripts");

    $response->assertOk()
        ->assertJsonCount(3);
});

it('can filter failed scripts by resource types', function () {
    ProvisionScript::factory()->count(2)->create([
        'server_id' => $this->server->id,
        'status' => ProvisionScriptStatus::Failed,
        'resource_type' => 'database',
    ]);

    ProvisionScript::factory()->create([
        'server_id' => $this->server->id,
        'status' => ProvisionScriptStatus::Failed,
        'resource_type' => 'database_user',
    ]);

    ProvisionScript::factory()->create([
        'server_id' => $this->server->id,
        'status' => ProvisionScriptStatus::Failed,
        'resource_type' => 'certificate',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/servers/{$this->server->slug}/failed-scripts?types=database,database_user");

    $response->assertOk()
        ->assertJsonCount(3);
});

it('can view a provision script', function () {
    $script = ProvisionScript::factory()->create([
        'server_id' => $this->server->id,
        'status' => ProvisionScriptStatus::Failed,
        'output' => 'Error output here',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/provision-scripts/{$script->id}");

    $response->assertOk()
        ->assertJsonPath('id', $script->id)
        ->assertJsonPath('output', 'Error output here');
});

it('can dismiss a failed provision script', function () {
    $script = ProvisionScript::factory()->create([
        'server_id' => $this->server->id,
        'status' => ProvisionScriptStatus::Failed,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/provision-scripts/{$script->id}/dismiss");

    $response->assertOk()
        ->assertJsonPath('success', true);

    expect($script->fresh()->status)->toBe(ProvisionScriptStatus::Completed);
});
