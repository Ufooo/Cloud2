<?php

namespace Nip\Server\Enums;

use Nip\Server\Data\ServerTypeOptionData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ServerType: string
{
    case App = 'app';
    case Web = 'web';
    case LoadBalancer = 'loadbalancer';
    case Database = 'database';
    case Cache = 'cache';
    case Worker = 'worker';
    case Meilisearch = 'meilisearch';

    public function label(): string
    {
        return match ($this) {
            self::App => 'App Server',
            self::Web => 'Web Server',
            self::LoadBalancer => 'Load Balancer',
            self::Database => 'Database Server',
            self::Cache => 'Cache Server',
            self::Worker => 'Worker Server',
            self::Meilisearch => 'Meilisearch',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::App => 'Full stack application server with PHP, database, and queue support',
            self::Web => 'Web server optimized for serving static content and PHP applications',
            self::LoadBalancer => 'HAProxy or Nginx load balancer',
            self::Database => 'Dedicated MySQL, PostgreSQL, or MariaDB server',
            self::Cache => 'Redis or Memcached caching server',
            self::Worker => 'Queue worker server for background job processing',
            self::Meilisearch => 'Fast, typo-tolerant search engine',
        };
    }

    /**
     * @return array<int, ServerTypeOptionData>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => new ServerTypeOptionData(
                value: $type,
                label: $type->label(),
                description: $type->description(),
            ),
            self::cases()
        );
    }
}
