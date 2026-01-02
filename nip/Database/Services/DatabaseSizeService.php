<?php

namespace Nip\Database\Services;

use Exception;
use Nip\Database\Models\Database;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\SSHService;

class DatabaseSizeService
{
    public function __construct(
        protected SSHService $ssh,
    ) {}

    /**
     * Refresh database sizes for a server.
     *
     * @return array<string, int>
     */
    public function refreshSizes(Server $server): array
    {
        $password = $server->database_password;

        if (! $password) {
            return [];
        }

        $query = "SELECT table_schema AS 'name', SUM(data_length + index_length) AS 'size' FROM information_schema.tables WHERE table_schema NOT IN ('mysql', 'information_schema', 'performance_schema', 'sys') GROUP BY table_schema;";

        $command = sprintf(
            'mysql --user="root" --password="%s" -N -e "%s" 2>/dev/null',
            $password,
            $query
        );

        try {
            $this->ssh->connect($server, 'root');
            $result = $this->ssh->executeScript($command);
            $this->ssh->disconnect();

            if (! $result->isSuccessful()) {
                return [];
            }

            $sizes = $this->parseOutput($result->output);

            $this->updateDatabaseSizes($server, $sizes);

            return $sizes;
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Parse MySQL output into name => size array.
     *
     * @return array<string, int>
     */
    protected function parseOutput(string $output): array
    {
        $sizes = [];
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2) {
                $name = $parts[0];
                $size = (int) $parts[1];
                $sizes[$name] = $size;
            }
        }

        return $sizes;
    }

    /**
     * Update database sizes in the database.
     *
     * @param  array<string, int>  $sizes
     */
    protected function updateDatabaseSizes(Server $server, array $sizes): void
    {
        foreach ($sizes as $name => $size) {
            Database::where('server_id', $server->id)
                ->where('name', $name)
                ->update(['size' => $size]);
        }
    }
}
