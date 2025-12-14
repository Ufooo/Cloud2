<?php

namespace Nip\Server\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum DatabaseType: string
{
    // Versioned cases
    case Mysql80 = 'mysql80';
    case Mariadb1011 = 'mariadb1011';
    case Mariadb114 = 'mariadb114';
    case Postgresql16 = 'postgresql16';
    case Postgresql17 = 'postgresql17';
    case Postgresql18 = 'postgresql18';

    // Legacy cases (backwards compatibility)
    case Mysql = 'mysql';
    case Mariadb = 'mariadb';
    case Postgresql = 'postgresql';

    public function label(): string
    {
        return match ($this) {
            self::Mysql80, self::Mysql => 'MySQL 8.0',
            self::Mariadb1011 => 'MariaDB 10.11',
            self::Mariadb114, self::Mariadb => 'MariaDB 11.4',
            self::Postgresql16, self::Postgresql => 'PostgreSQL 16',
            self::Postgresql17 => 'PostgreSQL 17',
            self::Postgresql18 => 'PostgreSQL 18',
        };
    }

    public function type(): string
    {
        return match ($this) {
            self::Mysql80, self::Mysql => 'mysql',
            self::Mariadb1011, self::Mariadb114, self::Mariadb => 'mariadb',
            self::Postgresql16, self::Postgresql17, self::Postgresql18, self::Postgresql => 'postgresql',
        };
    }

    public function version(): string
    {
        return match ($this) {
            self::Mysql80, self::Mysql => '8.0',
            self::Mariadb1011 => '10.11',
            self::Mariadb114, self::Mariadb => '11.4',
            self::Postgresql16, self::Postgresql => '16',
            self::Postgresql17 => '17',
            self::Postgresql18 => '18',
        };
    }

    /**
     * @return array<int, array{value: string|null, label: string}>
     */
    public static function options(): array
    {
        $options = [['value' => null, 'label' => 'None']];

        $versionedCases = [
            self::Mysql80,
            self::Mariadb1011,
            self::Mariadb114,
            self::Postgresql16,
            self::Postgresql17,
            self::Postgresql18,
        ];

        foreach ($versionedCases as $type) {
            $options[] = [
                'value' => $type->value,
                'label' => $type->label(),
            ];
        }

        return $options;
    }

    public function isLegacy(): bool
    {
        return in_array($this, [self::Mysql, self::Mariadb, self::Postgresql]);
    }
}
