#
# Zero-Downtime Deployment for Laravel
#

@include('provisioning.scripts.deploy.partials.clone-repository')

@include('provisioning.scripts.deploy.partials.setup-env')

@include('provisioning.scripts.deploy.partials.install-composer')

#
# Laravel Optimizations
#

echo "Running Laravel optimizations..."
$NIP_PHP artisan optimize
$NIP_PHP artisan storage:link 2>/dev/null || true
$NIP_PHP artisan migrate --force

@include('provisioning.scripts.deploy.partials.build-frontend')

@include('provisioning.scripts.deploy.partials.activate-release')

echo "Laravel deployment completed!"
