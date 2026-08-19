<?php

use Nip\Site\Models\Site;

it('repairs a plain file standing in for the sites-enabled symlink', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    $script = view('provisioning.scripts.site.steps.enable-nginx-site', [
        'site' => $site,
        'domain' => 'example.com',
    ])->render();

    // `ln -s` behind a `[ ! -L ]` guard cannot repair a regular file sitting at
    // the target: the guard passes, ln fails because the file exists, and with
    // `set -e` the whole step aborts. 35 production sites froze exactly so, and
    // every generated nginx change silently stopped reaching them.
    expect($script)
        ->toContain('ln -sfn /etc/nginx/sites-available/example.com /etc/nginx/sites-enabled/example.com')
        ->not->toContain('[ ! -L');
});
