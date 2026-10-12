<?php

namespace Nip\Security\Jobs\Concerns;

use DateTimeInterface;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Every security job rewrites the whole auth.conf and the password files of one site, so two of
 * them for the same site must never run side by side, whichever kind they are. Used by jobs that
 * carry a public SecurityRule $securityRule.
 */
trait PreventsOverlappingSecuritySyncs
{
    /**
     * The lock is shared across the three job classes, otherwise the key is prefixed with the class
     * name and an add, an update and a remove would each hold a lock of their own. It expires on
     * its own (the default is never), so a worker that dies mid-job cannot block the site for good;
     * the expiry is longer than the SSH timeout and than the queue's retry_after of 610 seconds.
     * A job that finds the site busy is tried again shortly, a sync takes seconds.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('security-sync:site:'.$this->securityRule->site_id))
                ->shared()
                ->releaseAfter(15)
                ->expireAfter($this->getSshTimeout() + 300),
        ];
    }

    /**
     * A release caused by an overlap counts as an attempt, so with $tries alone a job that has to
     * wait a few times would be failed by the worker without ever having run. With a deadline the
     * worker no longer looks at the attempts. The deadline is fixed once, when the job is queued.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(2);
    }
}
