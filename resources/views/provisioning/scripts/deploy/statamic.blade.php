#
# Zero-Downtime Deployment for Statamic
#

@include('provisioning.scripts.deploy.partials.clone-repository')

@include('provisioning.scripts.deploy.partials.setup-env')

@include('provisioning.scripts.deploy.partials.install-composer')

#
# Statamic Optimizations
#

echo "Running Statamic optimizations..."
$NIP_PHP artisan optimize
$NIP_PHP artisan storage:link 2>/dev/null || true
$NIP_PHP artisan migrate --force
$NIP_PHP artisan statamic:stache:warm

@include('provisioning.scripts.deploy.partials.build-frontend')

@include('provisioning.scripts.deploy.partials.activate-release')

echo "Statamic deployment completed!"
