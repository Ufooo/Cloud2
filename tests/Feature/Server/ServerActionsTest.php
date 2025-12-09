<?php

use App\Models\User;

use function Pest\Laravel\assertDatabaseHas;

it('server slug is auto-generated from name', function () {
    $user = User::factory()->create();

    $serverData = [
        'name' => 'My Production Server',
        'provider' => 'digitalocean',
        'type' => 'app',
    ];

    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('servers.store'), $serverData);

    assertDatabaseHas('servers', [
        'name' => 'My Production Server',
        'slug' => 'my-production-server',
    ]);
});
