<?php

use App\Models\User;

use function Pest\Laravel\assertDatabaseHas;

it('server slug is auto-generated from name', function () {
    $user = User::factory()->create();

    $serverData = [
        'name' => 'My Production Server',
        'provider' => 'digitalocean',
        'type' => 'app',
        'ip_address' => '192.168.1.1',
        'ssh_port' => '22',
        'php_version' => 'php83',
        'ubuntu_version' => '24.04',
        'timezone' => 'UTC',
    ];

    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('servers.store'), $serverData);

    assertDatabaseHas('servers', [
        'name' => 'My Production Server',
        'slug' => 'my-production-server',
    ]);
});
