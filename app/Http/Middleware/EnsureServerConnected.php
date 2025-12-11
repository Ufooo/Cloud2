<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nip\Server\Enums\ServerStatus;
use Nip\Server\Models\Server;
use Symfony\Component\HttpFoundation\Response;

class EnsureServerConnected
{
    public function handle(Request $request, Closure $next): Response
    {
        $server = $request->route('server');

        if ($server instanceof Server && $server->status !== ServerStatus::Connected) {
            abort(403);
        }

        return $next($request);
    }
}
