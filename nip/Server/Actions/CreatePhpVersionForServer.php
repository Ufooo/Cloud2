<?php

namespace Nip\Server\Actions;

use Nip\Php\Actions\CreatePhpVersion;
use Nip\Php\Enums\PhpVersion;
use Nip\Php\Enums\PhpVersionStatus;
use Nip\Server\Enums\ServerType;
use Nip\Server\Models\Server;

class CreatePhpVersionForServer
{
    public function __construct(
        private CreatePhpVersion $createPhpVersion,
    ) {}

    public function handle(Server $server): void
    {
        if (! in_array($server->type, [ServerType::App, ServerType::Web, ServerType::Worker])) {
            return;
        }

        $phpVersion = $server->php_version instanceof PhpVersion
            ? $server->php_version
            : PhpVersion::tryFrom($server->php_version);

        $version = $phpVersion?->version() ?? '8.4';

        $this->createPhpVersion->handle(
            $server,
            $version,
            PhpVersionStatus::Installing,
            isCliDefault: true,
            isSiteDefault: true,
        );
    }
}
