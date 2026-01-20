<?php

namespace Nip\SecurityMonitor\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nip\SecurityMonitor\Actions\CleanupOldScans;

class CleanupOldScansJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * Execute the job.
     *
     * Cleans up old security scan records based on site retention settings.
     * Uses CleanupOldScans action to handle the deletion logic.
     */
    public function handle(CleanupOldScans $action): void
    {
        $action->handle();
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['security-cleanup'];
    }
}
