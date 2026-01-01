<?php

namespace Nip\SourceControl\Providers;

use Nip\Support\Providers\NipServiceProvider;

class SourceControlServiceProvider extends NipServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__.'/..';
    }
}
