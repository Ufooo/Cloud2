<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    Nip\BackgroundProcess\Providers\BackgroundProcessServiceProvider::class,
    Nip\Domain\Providers\DomainServiceProvider::class,
    Nip\Network\Providers\NetworkServiceProvider::class,
    Nip\Php\Providers\PhpServiceProvider::class,
    Nip\Process\Providers\ProcessServiceProvider::class,
    Nip\Scheduler\Providers\SchedulerServiceProvider::class,
    Nip\Security\Providers\SecurityServiceProvider::class,
    Nip\Server\Providers\ServerServiceProvider::class,
    Nip\Site\Providers\SiteServiceProvider::class,
    Nip\SshKey\Providers\SshKeyServiceProvider::class,
    Nip\UnixUser\Providers\UnixUserServiceProvider::class,
];
