<?php

namespace Nip\Php\Actions;

use Nip\Php\Enums\PhpVersionStatus;
use Nip\Php\Models\PhpVersion;
use Nip\Server\Models\Server;

class CreatePhpVersion
{
    public function handle(
        Server $server,
        string $version,
        PhpVersionStatus $status = PhpVersionStatus::Pending,
        bool $isCliDefault = false,
        bool $isSiteDefault = false,
    ): PhpVersion {
        return PhpVersion::create([
            'server_id' => $server->id,
            'version' => $version,
            'status' => $status,
            'is_cli_default' => $isCliDefault,
            'is_site_default' => $isSiteDefault,
        ]);
    }
}
