#!/bin/bash
set -e

# Netipar Cloud - Install Site
# Site: {{ $domain }}
# User: {{ $user }}
# Type: {{ $siteType->value }}

echo "Installing site {{ $domain }}..."

#
# Step 1: Create Site Directory (runs as user)
#
# Note: This step is executed separately as the site user

#
# Step 2: Create Nginx Configuration
#

@include('provisioning.scripts.site.steps.create-nginx-config', [
    'site' => $site,
    'domain' => $domain,
    'nginxConfig' => $nginxConfig,
])

#
# Step 3: Create WWW Redirect
#

@include('provisioning.scripts.site.steps.create-www-redirect', [
    'site' => $site,
    'domain' => $domain,
])

#
# Step 4: Enable Nginx Site
#

@include('provisioning.scripts.site.steps.enable-nginx-site', [
    'domain' => $domain,
])

#
# Step 5: Create Isolated PHP-FPM Pool
#

@include('provisioning.scripts.site.steps.create-isolated-fpm', [
    'domain' => $domain,
    'user' => $user,
    'phpVersion' => $phpVersion,
    'fullPath' => $fullPath,
])

#
# Step 6: Reload Services
#

@include('provisioning.scripts.site.steps.reload-services', [
    'domain' => $domain,
    'installedPhpVersions' => $installedPhpVersions,
])

#
# Step 7: Create Logrotate Configuration
#

@include('provisioning.scripts.site.steps.create-logrotate', [
    'site' => $site,
    'domain' => $domain,
    'user' => $user,
    'fullPath' => $fullPath,
])

@if($siteType === \Nip\Site\Enums\SiteType::WordPress)
#
# Step 8: Install WordPress
#

@include('provisioning.scripts.site.partials.install-wordpress', [
    'site' => $site,
    'user' => $user,
    'fullPath' => $fullPath,
    'webDirectory' => $webDirectory,
    'phpVersion' => $phpVersion,
])
@endif

echo "Site {{ $domain }} installed successfully!"
