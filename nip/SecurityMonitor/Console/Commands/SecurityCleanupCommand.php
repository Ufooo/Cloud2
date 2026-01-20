<?php

namespace Nip\SecurityMonitor\Console\Commands;

use Illuminate\Console\Command;
use Nip\SecurityMonitor\Actions\CleanupOldScans;

class SecurityCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'security:cleanup';

    /**
     * The console command description.
     */
    protected $description = 'Cleanup old security scan records based on retention settings';

    /**
     * Execute the console command.
     */
    public function handle(CleanupOldScans $action): int
    {
        $this->info('Starting security scan cleanup...');

        $deletedCount = $action->handle();

        $this->info("Deleted {$deletedCount} old scan records.");

        return self::SUCCESS;
    }
}
