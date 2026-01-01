<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('servers.{serverId}', function ($user, $serverId) {
    return $user !== null;
});

Broadcast::channel('sites.{siteId}', function ($user, $siteId) {
    return $user !== null;
});

Broadcast::channel('deployments.{deploymentId}', function ($user, $deploymentId) {
    return $user !== null;
});
