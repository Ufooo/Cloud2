@php
$createReleasePlaceholder = $site->type->supportsZeroDowntime()
    ? view('provisioning.scripts.deploy.placeholders.create-release')->render()
    : '';

$activateReleasePlaceholder = $site->type->supportsZeroDowntime()
    ? view('provisioning.scripts.deploy.placeholders.activate-release')->render()
    : '';

$restartFpmPlaceholder = view('provisioning.scripts.deploy.placeholders.restart-fpm')->render();

$processedDeployScript = $deployScriptContent;

if ($site->type->supportsZeroDowntime()) {
    $processedDeployScript = str_replace(
        ['$CREATE_RELEASE()', '$ACTIVATE_RELEASE()'],
        [trim($createReleasePlaceholder), trim($activateReleasePlaceholder)],
        $processedDeployScript
    );
}

$processedDeployScript = str_replace(
    '$RESTART_FPM()',
    trim($restartFpmPlaceholder),
    $processedDeployScript
);
@endphp
#!/bin/bash
set -eo pipefail

echo $(date)
export NETIPAR_CLOUD=1

mkdir -p /tmp/site-{{ $site->id }}
ln -sf /usr/bin/php{{ $phpVersion }} /tmp/site-{{ $site->id }}/php
export PATH="/tmp/site-{{ $site->id }}:$PATH"

export NIP_SITE_ROOT="{{ $fullPath }}"
export NIP_RELEASES_PATH="{{ $fullPath }}/releases"
export NIP_SITE_PATH="{{ $currentPath }}"
export NIP_SITE_BRANCH="{{ $branch }}"
export NIP_SITE_REPOSITORY="{{ $site->getCloneUrl() }}"

export NIP_PHP="/usr/bin/php{{ $phpVersion }}"
export NIP_PHP_FPM="php{{ $phpVersion }}-fpm"
export NIP_COMPOSER="php{{ $phpVersion }} /usr/local/bin/composer"

export NIP_DEPLOYMENT_ID="{{ $deployment->id ?? '' }}"
export NIP_DEPLOY_AUTHOR="{{ $deployment->user->name ?? 'System' }}"
export NIP_DEPLOY_COMMIT="{{ $deployment->commit_hash ?? '' }}"
export NIP_DEPLOY_MESSAGE='{{ str_replace("'", "'\\''", $deployment->commit_message ?? '') }}'
export NIP_SERVER_ID="{{ $site->server_id }}"
export NIP_SITE_ID="{{ $site->id }}"
export NIP_SITE_USER="{{ $user }}"
export NIP_CUSTOM_DEPLOY="{{ $site->deploy_script ? '1' : '0' }}"
export NIP_MANUAL_DEPLOY="{{ ($deployment->is_manual ?? true) ? '1' : '0' }}"
export NIP_QUICK_DEPLOY="{{ ($deployment->is_quick ?? false) ? '1' : '0' }}"
export NIP_ROLLBACK="{{ ($deployment->is_rollback ?? false) ? '1' : '0' }}"

echo -e '\e[32m=> Deploying site {{ $domain }}\e[0m'

@if($site->type->supportsZeroDowntime())
{!! $processedDeployScript !!}
@else
cd "$NIP_SITE_PATH"

echo -e '\e[32m=> Pulling latest changes\e[0m'
git pull origin $NIP_SITE_BRANCH

echo -e '\e[32m=> Executing deployment script\e[0m'

{!! $processedDeployScript !!}
@endif

echo -e '\e[32m=> Setting permissions\e[0m'
chown -R {{ $user }}:{{ $user }} "{{ $fullPath }}"

@if($site->type->supportsZeroDowntime())
chmod -R 775 "{{ $fullPath }}/storage"
if [ -n "$NIP_RELEASE_DIRECTORY" ]; then
    chmod -R 775 "$NIP_RELEASE_DIRECTORY/bootstrap/cache" 2>/dev/null || true
fi
@endif

echo -e '\e[32m=> Deployment complete\e[0m'
