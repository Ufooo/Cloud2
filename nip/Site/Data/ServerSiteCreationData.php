<?php

namespace Nip\Site\Data;

use Illuminate\Support\Collection;
use Nip\Server\Models\Server;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ServerSiteCreationData extends Data
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        /** @var array<int, PhpVersionOptionData> */
        public array $phpVersions,
        /** @var array<int, SelectOptionData> */
        public array $unixUsers,
        /** @var array<int, DatabaseOptionData> */
        public array $databases,
        /** @var array<int, SelectOptionData> */
        public array $databaseUsers,
    ) {}

    public static function fromModel(Server $server): self
    {
        return new self(
            id: $server->id,
            slug: $server->slug,
            name: $server->name,
            phpVersions: $server->relationLoaded('phpVersions')
                ? $server->phpVersions->map(fn ($pv) => PhpVersionOptionData::fromModel($pv))->values()->all()
                : [],
            unixUsers: $server->relationLoaded('unixUsers')
                ? $server->unixUsers->map(fn ($u) => new SelectOptionData(
                    value: $u->username,
                    label: $u->username,
                ))->values()->all()
                : [],
            databases: $server->relationLoaded('databases')
                ? $server->databases->map(fn ($db) => DatabaseOptionData::fromModel($db))->values()->all()
                : [],
            databaseUsers: $server->relationLoaded('databaseUsers')
                ? $server->databaseUsers->map(fn ($dbu) => new SelectOptionData(
                    value: $dbu->id,
                    label: $dbu->username,
                ))->values()->all()
                : [],
        );
    }

    /**
     * @param  Collection<int, Server>  $servers
     * @return array<int, self>
     */
    public static function fromCollection(Collection $servers): array
    {
        return $servers->map(fn (Server $server) => self::fromModel($server))->all();
    }
}
