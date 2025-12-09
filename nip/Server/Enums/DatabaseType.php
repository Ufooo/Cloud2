<?php

namespace Nip\Server\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum DatabaseType: string
{
    case Mysql = 'mysql';
    case Mariadb = 'mariadb';
    case Postgresql = 'postgresql';

    public function label(): string
    {
        return match ($this) {
            self::Mysql => 'MySQL 8.0',
            self::Mariadb => 'MariaDB 11',
            self::Postgresql => 'PostgreSQL 16',
        };
    }

    /**
     * @return array<int, array{value: string|null, label: string}>
     */
    public static function options(): array
    {
        $options = [['value' => null, 'label' => 'None']];

        foreach (self::cases() as $type) {
            $options[] = [
                'value' => $type->value,
                'label' => $type->label(),
            ];
        }

        return $options;
    }
}
