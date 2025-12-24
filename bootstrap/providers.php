<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    Nip\BackgroundProcess\Providers\BackgroundProcessServiceProvider::class,
    Nip\Database\Providers\DatabaseServiceProvider::class,
    Nip\Deployment\Providers\DeploymentServiceProvider::class,
    Nip\Domain\Providers\DomainServiceProvider::class,
    Nip\Network\Providers\NetworkServiceProvider::class,
    Nip\Php\Providers\PhpServiceProvider::class,
    Nip\Process\Providers\ProcessServiceProvider::class,
    Nip\Redirect\Providers\RedirectServiceProvider::class,
    Nip\Scheduler\Providers\SchedulerServiceProvider::class,
    Nip\Security\Providers\SecurityServiceProvider::class,
    Nip\Server\Providers\ServerServiceProvider::class,
    Nip\Site\Providers\SiteServiceProvider::class,
    Nip\SshKey\Providers\SshKeyServiceProvider::class,
    Nip\UnixUser\Providers\UnixUserServiceProvider::class,
];
